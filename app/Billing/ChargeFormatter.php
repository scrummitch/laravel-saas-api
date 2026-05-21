<?php

namespace App\Billing;

use Carbon\CarbonInterval;
use Money\Currencies\ISOCurrencies;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use Symfony\Component\Intl\Currencies;

/**
 * Formats money values for display.
 */
class ChargeFormatter
{
    public static function format(?Money $money): string
    {
        if (is_null($money)) {
            return '';
        }

        $currencies = new ISOCurrencies;

        $numberFormatter = new \NumberFormatter('en_AU', \NumberFormatter::CURRENCY);
        $moneyFormatter = new IntlMoneyFormatter($numberFormatter, $currencies);

        return $moneyFormatter->format($money);
    }

    public static function formatShort(Money $money, int $decimals = 0): string
    {
        $amount = $money->getAmount();
        $symbol = Currencies::getSymbol($money->getCurrency()->getCode());

        return $symbol.number_format($amount / 100, $decimals);
    }

    public static function formatPerInterval(Money $money, CarbonInterval $interval): string
    {
        $amount = $money->getAmount();
        $symbol = Currencies::getSymbol($money->getCurrency()->getCode());

        if ($amount == 0) {
            return 'FREE';
        }

        $intervalSpec = match ($interval->spec()) {
            'P1M' => 'month',
            'P1Y' => 'year',
            default => $interval->spec(),
        };

        return sprintf('%s%s/%s', $symbol, $amount / 100, $intervalSpec);
    }
}
