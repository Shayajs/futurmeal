<?php

namespace App\Models;

use App\Data\NutrientProfile;
use App\Services\Nutrition\NutritionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CiqualFood extends Model
{
    protected $table = 'ciqual_foods';

    protected $fillable = [
        'alim_code',
        'name_fr',
        'name_en',
        'group_name',
    ];

    public function compositions(): HasMany
    {
        return $this->hasMany(CiqualComposition::class);
    }

    public function nutrientProfile(): NutrientProfile
    {
        return app(NutritionResolver::class)->profileFromCiqual($this->id);
    }
}
