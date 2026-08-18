<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Categorie in de vrijgestelde-elementen-bibliotheek — parallel aan DesignTemplateCategory. */
class DesignElementCategory extends Model
{
    protected $fillable = ['slug', 'label', 'sort_order'];

    protected $casts = ['sort_order' => 'integer'];

    public function elements(): HasMany
    {
        return $this->hasMany(DesignElement::class, 'category_id');
    }

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
