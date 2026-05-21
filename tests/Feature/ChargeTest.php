<?php

namespace Tests\Feature;

use App\Billing\Charges\GraduatedCharge;
use App\Billing\ISO4217;
use App\Billing\RenewalCalculator;
use App\Convert\DataObjects\LineItem;
use App\Models\Billing\Charge;
use App\Store\IsPurchasable;
use InvalidArgumentException;
use Money\Currency;
use Money\Money;
use Tests\TestCase;

class ChargeTest extends TestCase
{
    public function testVolumeCharge()
    {
        $org = $this->createOrg();
        $currency = new Currency('USD');
        $charge = Charge::factory()
            ->for($org)
            ->volume()
            ->create([
                'product_id' => 111111,
                'type' => 'volume',
                'currency' => $currency->getCode(),
                'properties' => [
                    ['up_to' => 5, 'unit_amount' => 200],
                    ['up_to' => 10, 'unit_amount' => 150],
                    ['up_to' => null, 'unit_amount' => 100],
                ],
            ]);

        $calculator = new RenewalCalculator($charge, null);
        $lineItem = new LineItem($charge, $calculator, ['quantity' => 15]);

        $result = new Money($lineItem->toArray()['amount_total'], $currency);

        // Expected: 15 * 100 = 1500 (uses the last tier for all units)
        $this->assertEquals(new Money(1500, new Currency('USD')), $result);

    }

    public function test_graduatedCharge()
    {
        $org = $this->createOrg();
        $currency = new Currency('USD');
        $charge = Charge::factory()
            ->for($org)
            ->graduated()
            ->create([
                'product_id' => 222222,
                'type' => 'graduated',
                'currency' => $currency->getCode(),
                'properties' => [
                    ['up_to' => 5, 'flat_amount' => 1000, 'unit_amount' => 200],
                    ['up_to' => 10, 'flat_amount' => 0, 'unit_amount' => 150],
                    ['up_to' => null, 'flat_amount' => 0, 'unit_amount' => 100],
                ],
            ]);

        $calculator = new RenewalCalculator($charge, null);

        $lineItem = new LineItem($charge, $calculator, ['quantity' => 15]);
        $result = new Money($lineItem->toArray()['amount_total'], $currency);
        $this->assertEquals(new Money(3250, new Currency('USD')), $result);

        // Test with quantity in the first tier
        $lineItem = new LineItem($charge, $calculator, ['quantity' => 3]);
        $result = new Money($lineItem->toArray()['amount_total'], $currency);
//        $result = $lineItem->calculateTieredCharge($charge, 3);
        // Expected: 1000 (flat) + (3 * 200) = 1600
        $this->assertEquals(new Money(1600, new Currency('USD')), $result);

        // Test with quantity spanning first and second tiers
        $lineItem = new LineItem($charge, $calculator, ['quantity' => 7]);
        $result = new Money($lineItem->toArray()['amount_total'], $currency);
//        $result = $lineItem->calculateTieredCharge($charge, 7);
        // Expected: 1000 (flat) + (5 * 200) + (2 * 150) = 2300
        $this->assertEquals(new Money(2300, new Currency('USD')), $result);

        // Test with quantity spanning all tiers
        $lineItem = new LineItem($charge, $calculator, ['quantity' => 15]);
        $result = new Money($lineItem->toArray()['amount_total'], $currency);
//        $result = $lineItem->calculateTieredCharge($charge, 15);
        // Expected: 1000 (flat) + (5 * 200) + (5 * 150) + (5 * 100) = 3250
        $this->assertEquals(new Money(3250, new Currency('USD')), $result);

        // Test with large quantity
        $lineItem = new LineItem($charge, $calculator, ['quantity' => 100]);
        $result = new Money($lineItem->toArray()['amount_total'], $currency);
//        $result = $lineItem->calculateTieredCharge($charge, 100);
        // Expected: 1000 (flat) + (5 * 200) + (5 * 150) + (90 * 100) = 11750
        $this->assertEquals(new Money(11750, new Currency('USD')), $result);
    }

    public function test_negative_quantity()
    {
        $org = $this->createOrg();
        $currency = new Currency('USD');
        $charge = GraduatedCharge::factory()
            ->for($org)
            ->graduated()
            ->create([
                'product_id' => 333333,
                'type' => 'graduated',
                'currency' => $currency->getCode(),
                'properties' => [
                    ['up_to' => 5, 'flat_amount' => 1000, 'unit_amount' => 200],
                    ['up_to' => null, 'flat_amount' => 0, 'unit_amount' => 100],
                ],
            ]);

        $calculator = new RenewalCalculator($charge, null);
        $lineItem = new LineItem($charge, $calculator, ['quantity' => -5]);

        $result = new Money($lineItem->toArray()['amount_total'], $currency);
        $this->assertEquals(new Money(0, $currency), $result, "Negative quantity should not charge anything");
    }

    public function test_zero_quantity()
    {
        $org = $this->createOrg();
        $currency = new Currency('USD');
        $charge = Charge::factory()->for($org)->create([
            'product_id' => 444444,
            'type' => 'graduated',
            'currency' => $currency->getCode(),
            'properties' => [
                ['up_to' => 5, 'flat_amount' => 1000, 'unit_amount' => 200],
                ['up_to' => null, 'flat_amount' => 0, 'unit_amount' => 100],
            ],
        ]);

        $calculator = new RenewalCalculator($charge, null);
        $lineItem = new LineItem($charge, $calculator, ['quantity' => 0]);
        $result = new Money($lineItem->toArray()['amount_total'], $currency);

        $this->assertEquals(new Money(0, new Currency('USD')), $result, "Zero quantity should charge zero");
    }

    public function test_extremely_large_quantity()
    {
        $org = $this->createOrg();
        $currency = new Currency('USD');
        $charge = GraduatedCharge::factory()
            ->for($org)
            ->graduated()
            ->create([
                'product_id' => 555555,
                'currency' => $currency->getCode(),
                'properties' => [
                    ['up_to' => 5, 'flat_amount' => 1000, 'unit_amount' => 200],
                    ['up_to' => null, 'flat_amount' => 0, 'unit_amount' => 100],
                ],
            ]);
        $calculator = new RenewalCalculator($charge, null);
        $lineItem = new LineItem($charge, $calculator, ['quantity' => PHP_INT_MAX]);
        $result = new Money($lineItem->toArray()['amount_total'], $currency);

        $this->assertGreaterThan(new Money(0, new Currency('USD')), $result);
        $this->assertLessThanOrEqual(new Money(PHP_INT_MAX, new Currency('USD')), $result);
        $this->assertEquals(100000000002000, $result->getAmount(), "Extremely large quantity should result in maximum possible charge");
    }

    public function test_negative_unit_amount()
    {
        $org = $this->createOrg();
        $currency = new Currency('USD');
        $charge = Charge::factory()
            ->for($org)
            ->graduated()
            ->create([
                'product_id' => 666666,
                'type' => 'graduated',
                'currency' => $currency->getCode(),
                'properties' => [
                    ['up_to' => 5, 'flat_amount' => 1000, 'unit_amount' => -200],
                    ['up_to' => null, 'flat_amount' => 0, 'unit_amount' => 100],
                ],
            ]);

        $calculator = new RenewalCalculator($charge, null);
        $lineItem = new LineItem($charge, $calculator, ['quantity' => 10]);
        $result = new Money($lineItem->toArray()['amount_total'], $currency);

        $this->assertEquals(new Money(1500, $currency), $result, "Negative unit amount should not charge anything");
    }

    public function testDecreasingTiers()
    {
        $org = $this->createOrg();
        $currency = new Currency('USD');
        $charge = Charge::factory()->for($org)->create([
            'product_id' => 777777,
            'type' => 'graduated',
            'currency' => $currency->getCode(),
            'properties' => [
                ['up_to' => 10, 'flat_amount' => 1000, 'unit_amount' => 200],
                ['up_to' => 5, 'flat_amount' => 0, 'unit_amount' => 100],
            ],
        ]);

        $calculator = new RenewalCalculator($charge, null);
        $lineItem = new LineItem($charge, $calculator, ['quantity' => 15]);
        $result = new Money($lineItem->toArray()['amount_total'], $currency);

        $this->assertEquals(new Money(2500, $charge->currency), $result);
    }

    public function test_missing_unit_amount()
    {
        $org = $this->createOrg();
        $currency = new Currency('USD');
        $charge = Charge::factory()
            ->graduated()
            ->for($org)
            ->create([
                'product_id' => 888888,
                'type' => 'graduated',
                'currency' => $currency->getCode(),
                'properties' => [
                    ['up_to' => 5, 'flat_amount' => 1000],
                    ['up_to' => null, 'flat_amount' => 0, 'unit_amount' => 100],
                ],
            ]);

        $calculator = new RenewalCalculator($charge, null);
        $lineItem = new LineItem($charge, $calculator, ['quantity' => 10]);
        $result = new Money($lineItem->toArray()['amount_total'], $currency);

        $this->assertEquals(new Money(1500, $currency), $result, "Missing unit amount should use flat amount");
    }

    public function testEmptyProperties()
    {
        $org = $this->createOrg();
        $currency = new Currency('USD');
        $charge = Charge::factory()->for($org)->create([
            'product_id' => 999999,
            'type' => 'graduated',
            'currency' => $currency->getCode(),
            'properties' => [],
        ]);

        $calculator = new RenewalCalculator($charge, null);

        $this->expectException(InvalidArgumentException::class);
        $calculator->setQuantity(1);
        $calculator->calculate();
    }
}
