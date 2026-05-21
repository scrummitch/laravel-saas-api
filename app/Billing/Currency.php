<?php

namespace App\Billing;

use Symfony\Component\Intl\Currencies;

class Currency
{
    public function __construct(
        protected string $name,
        protected string $alpha3,
        protected int $numeric,
        protected int $exp,
        protected array|string $country
    ) {}

    public function getFlag(): string
    {
        // Special cases and exceptions
        $specialCases = [
            'EUR' => '🇪🇺', // European Union
            'XCD' => '🏴‍☠️', // East Caribbean Dollar (multiple countries)
            'XOF' => '🌍', // West African CFA franc (multiple countries)
            'XAF' => '🌍', // Central African CFA franc (multiple countries)
            'XPF' => '🇵🇫', // CFP franc (French Polynesia)
        ];

        if (array_key_exists($this->alpha3, $specialCases)) {
            return $specialCases[$this->alpha3];
        }

        // For standard cases, convert currency code to country code
        $countryCode = substr($this->alpha3, 0, 2);

        // Convert country code to regional indicator symbols
        $flag = mb_convert_encoding(
            '&#'.(127397 + ord($countryCode[0])).';'.
            '&#'.(127397 + ord($countryCode[1])).';',
            'UTF-8',
            'HTML-ENTITIES'
        );

        return $flag;
    }

    public function getMoneyCurrency(): \Money\Currency
    {
        return new \Money\Currency($this->alpha3);
    }

    public function getSymbol(): string
    {
        return Currencies::getSymbol($this->alpha3);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAlpha3(): string
    {
        return $this->alpha3;
    }

    public function getNumeric(): int
    {
        return $this->numeric;
    }

    public function getExp(): int
    {
        return $this->exp;
    }

    public function getCountry(): string|array
    {
        return $this->country;
    }
}
