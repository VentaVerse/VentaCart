<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewImage;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function show(string $token)
    {
        $review = Review::where('token', $token)->with('product.images')->firstOrFail();

        // Already submitted — show thank you
        if ($review->rating !== null) {
            return view('review.thankyou', compact('review'));
        }

        return view('review.submit', compact('review'));
    }

    public function store(Request $request, string $token)
    {
        $review = Review::where('token', $token)->firstOrFail();

        // Already submitted
        if ($review->rating !== null) {
            return redirect()->route('review.submit', $token);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:255',
            'body' => 'nullable|string|max:5000',
            'images' => 'nullable|array|max:2',
            'images.*' => 'image|max:2048',
        ]);

        $review->update([
            'rating' => $validated['rating'],
            'title' => $validated['title'] ?? null,
            'body' => $validated['body'] ?? null,
        ]);

        // Handle image uploads
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('reviews', 'public');
                ReviewImage::create([
                    'review_id' => $review->id,
                    'path' => $path,
                ]);
            }
        }

        return redirect()->route('review.submit', $token);
    }
}
