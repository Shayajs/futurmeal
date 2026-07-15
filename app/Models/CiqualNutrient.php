<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CiqualNutrient extends Model
{
    protected $table = 'ciqual_nutrients';

    public $timestamps = false;

    protected $fillable = ['code', 'name_fr', 'unit'];
}
