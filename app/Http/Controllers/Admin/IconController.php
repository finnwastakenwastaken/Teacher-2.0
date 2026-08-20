<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\IconCatalogue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Search over the icon catalogue, for the picker.
 *
 * The catalogue holds around fifteen thousand icons, so it is searched on the
 * server and only the page of results the owner is looking at crosses the
 * wire — geometry included, so the picker can draw them without a second
 * request. Behind `auth` like the rest of the admin panel: students never see
 * this, and nothing here is needed to render the public site.
 */
class IconController extends Controller
{
    private const LIMIT = 120;

    public function index(Request $request): JsonResponse
    {
        $query = $request->string('q')->toString();
        $library = $request->string('library')->toString() ?: null;

        $icons = IconCatalogue::search($query, $library, self::LIMIT);
        $total = count($icons) < self::LIMIT
            // Fewer results than the cap means we already have all of them,
            // so the extra count query is pure waste.
            ? count($icons)
            : IconCatalogue::count($query, $library);

        return response()->json([
            'icons' => $icons,
            'total' => $total,
            'capped' => $total > count($icons),
            'libraries' => IconCatalogue::libraries(),
        ]);
    }
}
