<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $items = Item::select(['id', 'updated_at'])->get();

        $content = '<?xml version="1.0" encoding="UTF-8"?>';
        $content .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        
        // Page d'accueil
        $content .= '<url>';
        $content .= '<loc>' . url('/') . '</loc>';
        $content .= '<lastmod>' . now()->toAtomString() . '</lastmod>';
        $content .= '<changefreq>daily</changefreq>';
        $content .= '<priority>1.0</priority>';
        $content .= '</url>';
        
        // Page liste des items
        $content .= '<url>';
        $content .= '<loc>' . route('items.index') . '</loc>';
        $content .= '<lastmod>' . now()->toAtomString() . '</lastmod>';
        $content .= '<changefreq>daily</changefreq>';
        $content .= '<priority>0.9</priority>';
        $content .= '</url>';
        
        // Pages individuelles des items
        foreach ($items as $item) {
            $content .= '<url>';
            $content .= '<loc>' . route('items.show', $item->id) . '</loc>';
            $content .= '<lastmod>' . $item->updated_at->toAtomString() . '</lastmod>';
            $content .= '<changefreq>weekly</changefreq>';
            $content .= '<priority>0.7</priority>';
            $content .= '</url>';
        }
        
        $content .= '</urlset>';

        return response($content, 200)
            ->header('Content-Type', 'application/xml');
    }
}