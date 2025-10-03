<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;

class ItemApiController extends Controller
{
    /**
     * Get paginated list of items
     *
     * Returns a paginated list of items with advanced filtering capabilities.
     *
     * **Search Parameters:**
     * - `search` - Basic search by item name (wildcards supported)
     * - `name` - Filter by item name with operators
     * - `type` - Filter by item type with operators
     * - `category` - Filter by item category with operators
     * - `level` - Filter by item level with operators
     * - `dofusdb_id` - Filter by DofusDB ID with operators
     *
     * **Available Operators:**
     * - String fields: `eq:`, `like:`, `starts:`, `ends:`, `in:`
     * - Integer fields: `eq:`, `gt:`, `gte:`, `lt:`, `lte:`, `between:`, `in:`
     *
     * **Include Options (use the `include` parameter to get additional data):**
     * - `prices` - Item prices (all servers or filtered by server_id if specified)
     * - `recipe` - Crafting recipe (if craftable)
     * - `recipe.ingredients` - Recipe with ingredient details
     * - `usedInRecipes` - Recipes using this item
     * - `metadata` - Item stats and effects
     *
     * **Important Notes:**
     * - Without `include` parameter, only basic item data is returned (no prices, no recipe)
     * - `server_id` is optional: if not provided, prices from ALL servers are returned (with server_id in each price)
     * - If `server_id` is specified, only prices for that server are returned
     * - All prices returned have `status = 'approved'`
     *
     * **Examples:**
     * ```
     * ?search=bois                                    # Basic search (only item data)
     * ?name=eq:Bois de Frêne                         # Exact name match
     * ?name=like:épée&include=prices                 # Name search with prices (all servers)
     * ?level=between:50,100&include=recipe           # Level range with recipes
     * ?dofusdb_id=in:289,290,291&include=prices,recipe&server_id=24   # Multiple filters with prices for server 24
     * ```
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Validate request parameters for Scramble documentation
        $request->validate([
            'search' => 'sometimes|string|max:255',
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|max:255',
            'category' => 'sometimes|string|max:255',
            'level' => 'sometimes|string|max:255',
            'dofusdb_id' => 'sometimes|string|max:255',
            'server_id' => 'sometimes|integer|exists:servers,id',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'sort' => 'sometimes|string|in:name,level,type,category,created_at,dofusdb_id',
            'order' => 'sometimes|string|in:asc,desc',
            'include' => 'sometimes|string|max:255',
        ]);

        // Server ID is optional - if not provided, return prices for all servers
        $serverId = $request->input('server_id');

        // Handle dynamic includes
        $includes = $this->parseIncludes($request->input('include', ''));
        $query = Item::query();

        // Apply includes with optional server filtering for prices
        if (!empty($includes)) {
            $query->with($this->buildWithArray($includes, $serverId));
        }

        // Advanced filtering system
        $this->applyAdvancedFilters($query, $request);

        // Sorting
        $sortField = $request->input('sort', 'name');
        $sortOrder = $request->input('order', 'asc');

        $allowedSortFields = ['name', 'level', 'type', 'category', 'created_at', 'dofusdb_id'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, in_array($sortOrder, ['asc', 'desc']) ? $sortOrder : 'asc');
        }

        // Pagination
        $perPage = min($request->input('per_page', 20), 100);
        $items = $query->paginate($perPage);

        return response()->json([
            'data' => $items->map(function ($item) use ($includes, $serverId) {
                return $this->formatItemResponse($item, $includes, $serverId);
            }),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'server_id' => $serverId,
                'includes' => $includes
            ],
            'links' => [
                'first' => $items->url(1),
                'last' => $items->url($items->lastPage()),
                'prev' => $items->previousPageUrl(),
                'next' => $items->nextPageUrl(),
            ]
        ]);
    }

    /**
     * Apply advanced filters with operators
     */
    private function applyAdvancedFilters($query, Request $request)
    {
        // Simple search (legacy support)
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        // Advanced field filtering with operators
        $filterableFields = [
            'name' => 'string',
            'type' => 'string',
            'category' => 'string',
            'level' => 'integer',
            'dofusdb_id' => 'integer'
        ];

        foreach ($filterableFields as $field => $type) {
            if ($request->has($field)) {
                $value = $request->input($field);
                $this->applyFieldFilter($query, $field, $value, $type);
            }
        }
    }

    /**
     * Apply filter with operator support
     */
    private function applyFieldFilter($query, $field, $value, $type)
    {
        // Check if value contains an operator
        if (strpos($value, ':') !== false) {
            [$operator, $filterValue] = explode(':', $value, 2);

            switch ($operator) {
                case 'eq':
                    $query->where($field, '=', $filterValue);
                    break;
                case 'like':
                    $query->where($field, 'like', "%{$filterValue}%");
                    break;
                case 'starts':
                    $query->where($field, 'like', "{$filterValue}%");
                    break;
                case 'ends':
                    $query->where($field, 'like', "%{$filterValue}");
                    break;
                case 'gt':
                    if ($type === 'integer') $query->where($field, '>', (int)$filterValue);
                    break;
                case 'gte':
                    if ($type === 'integer') $query->where($field, '>=', (int)$filterValue);
                    break;
                case 'lt':
                    if ($type === 'integer') $query->where($field, '<', (int)$filterValue);
                    break;
                case 'lte':
                    if ($type === 'integer') $query->where($field, '<=', (int)$filterValue);
                    break;
                case 'between':
                    if ($type === 'integer' && strpos($filterValue, ',') !== false) {
                        [$min, $max] = explode(',', $filterValue);
                        $query->whereBetween($field, [(int)$min, (int)$max]);
                    }
                    break;
                case 'in':
                    $values = explode(',', $filterValue);
                    if ($type === 'integer') {
                        $values = array_map('intval', $values);
                    }
                    $query->whereIn($field, $values);
                    break;
            }
        } else {
            // Default behavior (exact match for integers, like for strings)
            if ($type === 'integer') {
                $query->where($field, '=', (int)$value);
            } else {
                $query->where($field, 'like', "%{$value}%");
            }
        }
    }

    /**
     * Get item details
     *
     * Returns detailed information about a specific item.
     *
     * **Parameters:**
     * - `include` - Comma-separated relations to include (required to get prices, recipe, etc.)
     * - `server_id` - Server ID for price filtering (optional)
     *
     * **Available Includes:**
     * - `prices` - Item prices (all servers or filtered by server_id if specified)
     * - `recipe` - Crafting recipe (if craftable)
     * - `recipe.ingredients` - Recipe with ingredient details
     * - `usedInRecipes` - Recipes using this item
     * - `metadata` - Item stats and effects
     *
     * **Important Notes:**
     * - Without `include` parameter, only basic item data is returned (no prices, no recipe)
     * - `server_id` is optional: if not provided, prices from ALL servers are returned (with server_id in each price)
     * - If `server_id` is specified, only prices for that server are returned
     * - All prices returned have `status = 'approved'`
     *
     * **Examples:**
     * ```
     * /api/items/123                                      # Basic item data only
     * /api/items/123?include=prices                      # With prices from all servers
     * /api/items/123?include=prices&server_id=24         # With prices for server 24 only
     * /api/items/123?include=recipe,recipe.ingredients   # With full recipe data
     * /api/items/123?include=prices,recipe,metadata      # With all available data
     * ```
     *
     * @param Request $request
     * @param Item $item
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Item $item)
    {
        // Server ID is optional - if not provided, return prices for all servers
        $serverId = $request->input('server_id');

        // Handle dynamic includes - no default includes, user must specify what they want
        $includes = $this->parseIncludes($request->input('include', ''));

        // Apply includes with optional server filtering for prices
        if (!empty($includes)) {
            $item->load($this->buildWithArray($includes, $serverId));
        }

        return response()->json([
            'data' => $this->formatItemResponse($item, $includes, $serverId),
            'meta' => [
                'server_id' => $serverId,
                'includes' => $includes
            ]
        ]);
    }

    /**
     * Parse include parameter into array
     */
    private function parseIncludes(string $includeParam): array
    {
        if (empty($includeParam)) {
            return [];
        }

        $requestedIncludes = explode(',', $includeParam);
        $allowedIncludes = [
            'recipe',
            'recipe.ingredients',
            'prices',
            'usedInRecipes',
            'metadata'
        ];

        // Filter only allowed includes
        return array_intersect($requestedIncludes, $allowedIncludes);
    }

    /**
     * Build the with array for Eloquent with custom constraints
     */
    private function buildWithArray(array $includes, ?int $serverId): array
    {
        $withArray = [];

        foreach ($includes as $include) {
            switch ($include) {
                case 'prices':
                    $withArray['prices'] = function($q) use ($serverId) {
                        // Only filter by server if one is specified
                        if ($serverId !== null) {
                            $q->where('server_id', $serverId);
                        }
                        $q->where('status', 'approved');
                    };
                    break;
                case 'recipe':
                    $withArray['recipe'] = function($q) {};
                    break;
                case 'recipe.ingredients':
                    $withArray['recipe.ingredients'] = function($q) {};
                    break;
                case 'usedInRecipes':
                    $withArray['usedInRecipes'] = function($q) {};
                    break;
                case 'metadata':
                    // Metadata is already loaded with the model, no additional query needed
                    break;
            }
        }

        return $withArray;
    }

    /**
     * Format item response based on includes
     */
    private function formatItemResponse($item, array $includes, ?int $serverId): array
    {
        $response = [
            'id' => $item->id,
            'dofusdb_id' => $item->dofusdb_id,
            'name' => $item->name,
            'type' => $item->type,
            'category' => $item->category,
            'level' => $item->level,
            'image_url' => $item->image_url,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];

        // Add metadata only if requested
        if (in_array('metadata', $includes)) {
            $response['metadata'] = $item->metadata;
        }

        // Add includes dynamically
        if (in_array('prices', $includes) && $item->relationLoaded('prices')) {
            $response['prices'] = $item->prices->map(function($price) {
                return [
                    'id' => $price->id,
                    'server_id' => $price->server_id, // Include server_id when returning multiple servers
                    'price' => $price->price,
                    'status' => $price->status,
                    'reports_count' => $price->reports_count,
                    'created_at' => $price->created_at,
                    'updated_at' => $price->updated_at,
                ];
            });
        }

        if (in_array('recipe', $includes) && $item->relationLoaded('recipe') && $item->recipe) {
            $response['recipe'] = [
                'id' => $item->recipe->id,
                'quantity_produced' => $item->recipe->quantity_produced,
                'profession' => $item->recipe->profession,
                'profession_level' => $item->recipe->profession_level,
                'created_at' => $item->recipe->created_at,
                'updated_at' => $item->recipe->updated_at,
            ];

            // Add ingredients if requested
            if (in_array('recipe.ingredients', $includes) && $item->recipe->relationLoaded('ingredients')) {
                $response['recipe']['ingredients'] = $item->recipe->ingredients->map(function($ingredient) {
                    return [
                        'item_id' => $ingredient->id,
                        'name' => $ingredient->name,
                        'quantity' => $ingredient->pivot->quantity,
                        'level' => $ingredient->level,
                        'type' => $ingredient->type,
                    ];
                });
            }
        }

        if (in_array('usedInRecipes', $includes) && $item->relationLoaded('usedInRecipes')) {
            $response['used_in_recipes'] = $item->usedInRecipes->map(function($recipe) {
                return [
                    'recipe_id' => $recipe->id,
                    'item_id' => $recipe->item_id,
                    'quantity_needed' => $recipe->pivot->quantity,
                    'profession' => $recipe->profession,
                ];
            });
        }

        return $response;
    }
}
