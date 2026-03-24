<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ErpReviewController extends Controller
{
    use LogsApiCalls;

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required_without:product_sku|nullable|integer|exists:products,id',
            'product_sku' => 'required_without:product_id|nullable|string|max:64',
            'author' => 'required|string|max:255',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:10000',
            'status' => 'nullable|string|in:pending,approved,rejected',
            'date_added' => 'nullable|date',
            'platform' => 'required|string|max:32',
            'platform_review_id' => 'required|string|max:128',
            'images' => 'nullable|array|max:10',
            'images.*' => 'url|max:2048',
        ]);

        // Check for duplicate by platform + platform_review_id
        $existing = Review::where('platform', $validated['platform'])
            ->where('platform_review_id', $validated['platform_review_id'])
            ->first();

        if ($existing) {
            return $this->logAndRespond($request, [
                'status' => 'skipped',
                'message' => 'Review already exists.',
                'review_id' => $existing->id,
            ], 200);
        }

        // Resolve product_id from SKU if needed
        $productId = $validated['product_id'] ?? null;

        if (! $productId && ! empty($validated['product_sku'])) {
            $product = Product::where('sku', $validated['product_sku'])->first();

            if (! $product) {
                return $this->logAndRespond($request, [
                    'status' => 'error',
                    'message' => 'Product not found for SKU: ' . $validated['product_sku'],
                ], 404, $validated['product_sku']);
            }

            $productId = $product->id;
        }

        $review = DB::transaction(function () use ($validated, $productId, $request) {
            $review = Review::create([
                'product_id' => $productId,
                'name' => $validated['author'],
                'email' => null,
                'token' => Str::random(64),
                'rating' => $validated['rating'],
                'title' => $validated['title'] ?? null,
                'body' => $validated['body'] ?? null,
                'status' => $validated['status'] ?? 'approved',
                'is_verified_purchase' => true,
                'platform' => $validated['platform'],
                'platform_review_id' => $validated['platform_review_id'],
                'published_at' => $validated['date_added'] ?? now(),
            ]);

            // Download and save images
            if (! empty($validated['images'])) {
                Storage::disk('public')->makeDirectory('reviews');

                foreach ($validated['images'] as $imageUrl) {
                    try {
                        $response = Http::timeout(15)->get($imageUrl);

                        if ($response->successful()) {
                            $extension = $this->guessExtension($response->header('Content-Type'), $imageUrl);
                            $filename = 'reviews/' . Str::uuid() . '.' . $extension;

                            Storage::disk('public')->put($filename, $response->body());

                            ReviewImage::create([
                                'review_id' => $review->id,
                                'path' => $filename,
                            ]);
                        }
                    } catch (\Throwable $e) {
                        // Log but don't fail the whole review for a single image
                        report($e);
                    }
                }
            }

            return $review;
        });

        $review->load('images');

        return $this->logAndRespond($request, [
            'status' => 'created',
            'review_id' => $review->id,
            'product_id' => $review->product_id,
            'images_saved' => $review->images->count(),
        ], 201);
    }

    private function guessExtension(?string $contentType, string $url): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if ($contentType && isset($map[$contentType])) {
            return $map[$contentType];
        }

        // Try from URL path
        $path = parse_url($url, PHP_URL_PATH);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            return $ext === 'jpeg' ? 'jpg' : $ext;
        }

        return 'jpg';
    }
}
