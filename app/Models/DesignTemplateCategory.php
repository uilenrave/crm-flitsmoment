<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Categorie voor de gedeelde fotostrip-templatebibliotheek. Bewust geen AccountScope:
 * één bibliotheek voor beide vestigingen.
 */
class DesignTemplateCategory extends Model
{
    protected $fillable = ['slug', 'label', 'sort_order'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function templates(): HasMany
    {
        return $this->hasMany(DesignTemplate::class, 'category_id');
    }

    /** Maak een unieke slug uit een label (voor nieuwe categorieën vanuit de admin). */
    public static function makeSlug(string $label): string
    {
        $base = Str::slug($label) ?: 'categorie';
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
