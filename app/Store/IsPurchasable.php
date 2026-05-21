<?php

namespace App\Store;

use App\Billing\Currency;
use App\Models\Catalog\ProductFamily;
use Illuminate\Support\Collection;

/**
 * @property \Money\Currency $currency
 */
interface IsPurchasable
{
    public function getPurchasableId(): string;

    public function getDisplayName(): string;

    public function getDescription(): string;

    public function getType(): string;

    public function getCurrency(): \Money\Currency;

    public function getCharges(): Collection;

    public function getProductFamilies(): Collection;

    public function getQuantity(): int;

    public function isRecurring(): bool;
}
