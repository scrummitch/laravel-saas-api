<?php

namespace App\Models\Convert;

use App\Billing\CurrencyCast;
use App\Billing\MoneyCast;
use App\Database\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Arr;
use Money\Currency;
use Money\Money;
use Stripe\Invoice;
use Stripe\InvoiceLineItem;

/**
 * @property int $id
 * @property int $session_id
 * @property int $variant_id
 * @property Currency $currency
 * @property string $type - [upgrade, expansion, order]
 * @property Money $proceeds_amount_gross
 */
class Attribution extends Model
{
    protected $table = 'convert_attributions';

    protected $casts = [
        'currency'   => CurrencyCast::class,
        'proceeds_amount_gross' => MoneyCast::class,
        'proceeds_amount_net' => MoneyCast::class,
        'processed_invoices' => 'array',
    ];

//    public function session()
//    {
//        return $this->belongsTo(PaywallSession::class);
//    }

    public function producer(): MorphTo
    {
        return $this->morphTo();
    }

    public function purchase(): MorphTo
    {
        return $this->morphTo();
    }

    public function ingestStripeInvoice(InvoiceLineItem $line)
    {
        $amount = new Money($line->amount, new Currency($line->currency));

        $newAmount = $this->proceeds_amount_gross->add($amount);

        $this->proceeds_amount_gross = $newAmount;

        $this->save();
    }

    // expansion, contraction, upgrade, downgrade, order, refund, adjustment, cancellation

    public function calculateGross(): ?Money
    {
        // store all attributed invoices here?
        // currency?
//        $money = new Money();
        // $producer = variant
        // $purchase = subscription
        // $type = upgrade

        // fees, gross, net?
        // select * from invoices
        // invoice_subscriptions
        // via invoies
        // get payments
        // calculate via payments?
        // taxes?
        // payment is alwa
        // invoices are via stripe for stripe-billing ?
        // invoices live in plandalf OR stripe ?
        // subscription, payments are

        // need invoices for MRR?!

    }
}
