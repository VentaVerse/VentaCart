<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\PaymentMethodRestriction;

class GeoZone extends Model
{
    protected $fillable = ['name', 'description', 'is_active', 'sort_order', 'source'];

    protected static function booted(): void
    {
        static::deleted(function (GeoZone $geoZone) {
            $id = $geoZone->id;
            $idStr = (string) $id;

            // Clean payment restriction JSON arrays
            PaymentMethodRestriction::all()->each(function ($r) use ($idStr) {
                $changed = false;
                if ($r->geo_zone_ids && in_array($idStr, $r->geo_zone_ids)) {
                    $r->geo_zone_ids = array_values(array_filter($r->geo_zone_ids, fn($v) => $v !== $idStr)) ?: null;
                    $changed = true;
                }
                if ($r->exclude_geo_zone_ids && in_array($idStr, $r->exclude_geo_zone_ids)) {
                    $r->exclude_geo_zone_ids = array_values(array_filter($r->exclude_geo_zone_ids, fn($v) => $v !== $idStr)) ?: null;
                    $changed = true;
                }
                if ($changed) $r->save();
            });

            // Clean free shipping rule JSON arrays (guarded: the model
            // lives in the shipping-free extension now).
            if (class_exists(\Extensions\ShippingFree\Models\FreeShippingRule::class)
                && \Illuminate\Support\Facades\Schema::hasTable('free_shipping_rules')) {
                \Extensions\ShippingFree\Models\FreeShippingRule::all()->each(function ($r) use ($id, $idStr) {
                if ($r->shipping_zone_ids && (in_array($id, $r->shipping_zone_ids) || in_array($idStr, $r->shipping_zone_ids))) {
                    $r->shipping_zone_ids = array_values(array_filter($r->shipping_zone_ids, fn($v) => (int)$v !== $id)) ?: null;
                    $r->save();
                }
                });
            }

            // Null out flat rate references (guarded, same reason).
            // (Weight-based methods reference geo zones via the
            // weight_based_method_geo_zone pivot, which the FK cascades on delete.)
            if (\Illuminate\Support\Facades\Schema::hasTable('flat_shipping_rates')) {
                \Illuminate\Support\Facades\DB::table('flat_shipping_rates')->where('geo_zone_id', $id)->update(['geo_zone_id' => null]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(GeoZoneEntry::class);
    }
}
