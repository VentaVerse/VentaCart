<?php

namespace Extensions\Reviews\Http\Controllers;

use App\Exceptions\RemoteImageFetchException;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Extensions\Reviews\Models\Review;
use Extensions\Reviews\Models\ReviewImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ErpReviewController extends Controller
{
    use \App\Http\Controllers\Api\FetchesRemoteImages;
    use \App\Http\Controllers\Api\LogsApiCalls;

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

        $imageUrls = $validated['images'] ?? [];
        $writtenPaths = [];

        try {
            $review = DB::transaction(function () use ($validated, $productId, $imageUrls, &$writtenPaths) {
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

                // Download and save images. A review whose photos could not be
                // fetched is as half-synced as a product whose photos could
                // not be fetched, and the ERP would file a 201 as a clean
                // success either way, so this endpoint fails the same way the
                // product push does. Retrying is safe: the review row is rolled
                // back with everything else, and the duplicate check above
                // still skips a review that did land earlier.
                //
                // What is deliberately NOT tightened here is which responses
                // count as an image. Marketplace review photos arrive with all
                // sorts of content types and this endpoint has always accepted
                // any successful response, so narrowing that now would reject
                // review pushes that work today. Only the handling of an
                // outright fetch failure changes.
                if (! empty($imageUrls)) {
                    Storage::disk('public')->makeDirectory('reviews');
                    $failed = [];

                    foreach ($imageUrls as $imageUrl) {
                        $result = $this->fetchRemoteImage($imageUrl);

                        if (! $result['ok']) {
                            $failed[] = ['url' => $imageUrl, 'reason' => $result['reason']];
                            continue;
                        }

                        $extension = $this->guessExtension($result['mime'], $imageUrl);
                        $filename = 'reviews/' . Str::uuid() . '.' . $extension;

                        Storage::disk('public')->put($filename, $result['body']);
                        $writtenPaths[] = $filename;

                        ReviewImage::create([
                            'review_id' => $review->id,
                            'path' => $filename,
                        ]);
                    }

                    if (! empty($failed)) {
                        throw new RemoteImageFetchException($failed, count($imageUrls));
                    }
                }

                return $review;
            });
        } catch (RemoteImageFetchException $e) {
            // Only files this request wrote, each under a fresh uuid filename
            // that existed nowhere before the call.
            $this->discardWrittenFiles($writtenPaths, function (string $path) {
                return ReviewImage::where('path', $path)->exists();
            });

            $failedCount = count($e->failures);

            return $this->logAndRespond($request, [
                'error' => 'images_unavailable',
                'message' => "Review not created: {$failedCount} of {$e->attempted} image(s) could not be fetched. "
                    . 'The whole push was rolled back, so nothing in the store changed. '
                    . 'Fix the image URLs listed below and push again.',
                'images_failed' => $e->failures,
            ], 422);
        }

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
