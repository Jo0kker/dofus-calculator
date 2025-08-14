<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Recipe extends Model
{
    protected $fillable = [
        'item_id',
        'quantity_produced',
        'profession',
        'profession_level',
    ];

    protected $casts = [
        'quantity_produced' => 'integer',
        'profession_level' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'recipe_ingredients')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function calculateCost(Server $server, array &$calculated = []): ?float
    {
        // Éviter les boucles infinies
        if (isset($calculated[$this->item_id])) {
            return $calculated[$this->item_id];
        }
        
        $totalCost = 0;
        
        foreach ($this->ingredients as $ingredient) {
            $ingredientCost = $this->getIngredientCost($ingredient, $server, $calculated);
            if ($ingredientCost === null) {
                return null;
            }
            $totalCost += $ingredientCost * $ingredient->pivot->quantity;
        }
        
        $calculated[$this->item_id] = $totalCost;
        return $totalCost;
    }
    
    private function getIngredientCost(Item $ingredient, Server $server, array &$calculated): ?float
    {
        // Si l'ingrédient a une recette, calculer récursivement
        if ($ingredient->recipe) {
            $craftCost = $ingredient->recipe->calculateCost($server, $calculated);
            
            // Si on peut le crafter, comparer avec le prix direct
            if ($craftCost !== null) {
                $directPrice = $ingredient->getPriceForServer($server);
                
                // Prendre le moins cher entre craft et achat direct
                if ($directPrice) {
                    return min($craftCost, $directPrice->price);
                } else {
                    return $craftCost;
                }
            }
        }
        
        // Sinon, utiliser le prix direct
        $price = $ingredient->getPriceForServer($server);
        return $price ? $price->price : null;
    }

    public function calculateProfit(Server $server): ?array
    {
        $cost = $this->calculateCost($server);
        if ($cost === null) {
            return null;
        }
        
        $itemPrice = $this->item->getPriceForServer($server);
        if (!$itemPrice) {
            return null;
        }
        
        $revenue = $itemPrice->price * $this->quantity_produced;
        $profit = $revenue - $cost;
        $profitMargin = $cost > 0 ? ($profit / $cost) * 100 : 0;
        
        return [
            'cost' => $cost,
            'revenue' => $revenue,
            'profit' => $profit,
            'profit_margin' => $profitMargin,
        ];
    }
}
