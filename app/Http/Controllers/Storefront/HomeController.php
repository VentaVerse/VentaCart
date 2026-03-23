<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\HomepageSection;
use App\Models\Brand;
use App\Models\Product;
use App\Services\ThemeService;

class HomeController extends Controller
{
    public function index(ThemeService $theme)
    {
        $sections = $theme->getHomepageSections();

        $sectionData = [];
        foreach ($sections as $section) {
            $data = ['section' => $section];

            switch ($section->type) {
                case 'products':
                    $data['products'] = $this->getProductsForSection($section, $theme);
                    break;
                case 'brands':
                    $data['brands'] = Brand::where('is_active', true)
                        ->whereNotNull('image')
                        ->where('image', '!=', '')
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->get();
                    break;
            }

            $sectionData[] = $data;
        }

        return view('home', compact('sectionData'));
    }

    private function getProductsForSection(HomepageSection $section, ThemeService $theme): \Illuminate\Database\Eloquent\Collection
    {
        $config = $section->config ?? [];
        $limit = $config['limit'] ?? 12;
        $filter = $config['filter'] ?? 'new';

        $query = Product::where('is_active', true)->with(['images', 'specials']);

        switch ($filter) {
            case 'new':
                $query->whereNotNull('date_available')
                    ->latest('date_available');
                break;
            case 'featured':
                $query->latest();
                break;
            case 'specials':
                $query->whereHas('specials', function ($q) {
                    $q->where('display_mode', 'sale')
                        ->where(function ($q2) {
                            $q2->whereNull('date_start')->orWhere('date_start', '<=', now());
                        })
                        ->where(function ($q2) {
                            $q2->whereNull('date_end')->orWhere('date_end', '>=', now());
                        });
                });
                break;
            case 'best_sellers':
                $query->latest();
                break;
            default:
                $query->latest();
        }

        return $query->limit($limit)->get();
    }
}
