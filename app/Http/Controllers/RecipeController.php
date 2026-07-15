<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use Illuminate\Support\Facades\Auth;

class RecipeController extends Controller
{
    public function index()
    {
        return view('recipes.index', [
            'recipes' => Auth::user()->recipes()->withCount('ingredients')->latest()->get(),
        ]);
    }

    public function show(Recipe $recipe)
    {
        abort_unless($recipe->user_id === Auth::id(), 403);

        $recipe->load('ingredients');

        return view('recipes.show', [
            'recipe' => $recipe,
            'nutrients' => $recipe->nutrientProfile()->toArray(),
        ]);
    }
}
