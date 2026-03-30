<?php

namespace App\Services;

use App\Models\CustomerActivity;

class ActivityLogger
{
    public static function log(
        ?int $customerId,
        string $type,
        string $description,
        ?object $subject = null,
        ?array $properties = null,
    ): void {
        if (!$customerId) {
            return;
        }

        CustomerActivity::create([
            'customer_id' => $customerId,
            'type' => $type,
            'description' => $description,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'properties' => $properties,
            'created_at' => now(),
        ]);
    }
}
