<?php

namespace App\Models\Store;

use App\Database\Model;
use App\Store\IsPurchasable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Money\Money;

/**
 * @property Purchase      $purchase
 * @property IsPurchasable $purchasable
 *
 * @property int $quantity
 * @property Money|null $amount_discount
 * @property Money|null $amount_total
 * @property Money|null $amount_subtotal
 * @property Money|null $amount_tax
 *
 * // note: do we need discounts applied directly here?
 */
class PurchaseItem extends Model
{
    protected $table = 'store_purchase_items';

    protected $guarded = [];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function purchasable()
    {
        return $this->morphTo();
    }
}
