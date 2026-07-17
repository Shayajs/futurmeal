<?php

use App\Http\Controllers\FriendInviteController;
use App\Http\Controllers\ProgramInviteController;
use App\Http\Controllers\RecipeController;
use App\Livewire\BudgetManager;
use App\Livewire\ChartsExplorer;
use App\Livewire\DayEditor;
use App\Livewire\FriendsPanel;
use App\Livewire\MealPlanner;
use App\Livewire\MenuDiscovery;
use App\Livewire\NotificationCenter;
use App\Livewire\Onboarding\ProfileSetup;
use App\Livewire\ProgramManager;
use App\Livewire\RecipeEditor;
use App\Livewire\Settings\AiSettings;
use App\Livewire\Settings\NutritionProfile;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('/politique-de-confidentialite', 'legal.privacy')->name('legal.privacy');
Route::view('/cookies', 'legal.cookies')->name('legal.cookies');
Route::view('/mentions-legales', 'legal.legal-notice')->name('legal.notice');

Route::middleware(['auth'])->group(function () {
    Route::get('/onboarding', ProfileSetup::class)->name('onboarding');
});

Route::middleware(['auth', 'verified', 'onboarded'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('metrics', 'metrics')->name('metrics');
    Route::get('charts', ChartsExplorer::class)->name('charts');
    Route::get('planner', MealPlanner::class)->name('planner');
    Route::get('planner/day/{date}', DayEditor::class)->name('planner.day');
    Route::get('programs', ProgramManager::class)->name('programs');
    Route::get('programs/join/{token}', ProgramInviteController::class)->name('programs.join');
    Route::get('recipes', [RecipeController::class, 'index'])->name('recipes.index');
    Route::get('recipes/create', RecipeEditor::class)->name('recipes.create');
    Route::get('recipes/{recipe}', [RecipeController::class, 'show'])->name('recipes.show');
    Route::get('recipes/{recipe}/edit', RecipeEditor::class)->name('recipes.edit');
    Route::view('profile', 'profile')->name('profile');
    Route::view('settings', 'settings.index')->name('settings');
    Route::get('settings/nutrition', NutritionProfile::class)->name('settings.nutrition');
    Route::get('settings/budget', BudgetManager::class)->name('settings.budget');
    Route::get('settings/ai', AiSettings::class)->name('settings.ai');
    Route::get('friends', FriendsPanel::class)->name('friends');
    Route::get('friends/add/{code}', FriendInviteController::class)->name('friends.add');
    Route::get('discover', MenuDiscovery::class)->name('discover');
    Route::get('notifications', NotificationCenter::class)->name('notifications');
});

require __DIR__.'/auth.php';
