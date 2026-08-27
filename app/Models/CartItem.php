<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id', 'product_id', 'product_variant_id',
        'quantity', 'name', 'variant_label', 'price', 'sku',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Serialized into the storefront's Alpine payloads. */
    protected $appends = ['image'];

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function getLineTotalAttribute(): float
    {
        return $this->price * $this->quantity;
    }

    /**
     * The one picture for this line, resolved in one place because the cart,
     * the mini cart and the checkout summary were each answering it their own
     * way and none of them asked the variant.
     *
     * The colourway's own photo first: it is what the shopper picked and what
     * they expect to recognise in their cart. Then the product's primary
     * image, then its first gallery image for a product whose primary was
     * never set.
     */
    public function getImageAttribute(): ?string
    {
        return $this->productVariant?->image
            ?: $this->product?->image
            ?: $this->product?->images?->first()?->path;
    }
}
