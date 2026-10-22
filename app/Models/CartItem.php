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

    /**
     * Can this line no longer be bought?
     *
     * True when its product is gone or switched off, or it names a variant
     * that is gone or switched off. A cart keeps a line from before the
     * switch was turned, so the cart page and checkout both ask this rather
     * than trusting that the line was valid when it was added.
     */
    public function isUnavailable(): bool
    {
        if (! $this->product || ! $this->product->is_active) {
            return true;
        }

        return $this->product_variant_id !== null
            && (! $this->productVariant || ! $this->productVariant->is_active);
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
