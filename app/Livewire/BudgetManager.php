<?php

namespace App\Livewire;

use App\Services\Budget\BudgetService;
use App\Services\Budget\CommunityPriceService;
use App\Services\Budget\OpenPricesClient;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class BudgetManager extends Component
{
    public string $label = '';

    public ?float $price_per_kg = null;

    public ?float $weekly_budget_target = null;

    public string $storeSearch = '';

    public string $storeCity = '';

    public array $storeResults = [];

    public function mount(): void
    {
        $this->weekly_budget_target = Auth::user()?->profile?->weekly_budget_target;
    }

    public function saveTarget(): void
    {
        $this->validate([
            'weekly_budget_target' => 'nullable|numeric|min:0|max:99999',
        ]);

        $profile = Auth::user()?->profile;
        if (! $profile) {
            return;
        }

        $profile->update([
            'weekly_budget_target' => $this->weekly_budget_target,
        ]);

        session()->flash('status', 'Budget cible enregistré.');
    }

    public function save(BudgetService $budget): void
    {
        $this->validate([
            'label' => 'required|string|max:255',
            'price_per_kg' => 'required|numeric|min:0.01|max:9999',
        ]);

        $budget->upsert(Auth::user(), $this->label, (float) $this->price_per_kg);
        $this->reset(['label', 'price_per_kg']);
        session()->flash('status', 'Prix enregistré.');
    }

    public function delete(int $id, BudgetService $budget): void
    {
        $budget->delete(Auth::user(), $id);
    }

    public function deleteContribution(int $id, CommunityPriceService $community): void
    {
        $community->deleteContribution(Auth::user(), $id);
        session()->flash('status', 'Contribution supprimée.');
    }

    public function updatedStoreSearch(OpenPricesClient $client): void
    {
        $this->searchStores($client);
    }

    public function updatedStoreCity(OpenPricesClient $client): void
    {
        $this->searchStores($client);
    }

    public function searchStores(OpenPricesClient $client): void
    {
        if (strlen(trim($this->storeSearch)) < 2) {
            $this->storeResults = [];

            return;
        }

        $this->storeResults = collect($client->searchLocations(
            $this->storeSearch,
            $this->storeCity !== '' ? $this->storeCity : null,
        ))->map(fn (array $loc) => [
            'id' => $loc['id'],
            'name' => $loc['osm_name'] ?? 'Magasin',
            'city' => $loc['osm_address_city'] ?? '',
            'brand' => $loc['osm_brand'] ?? null,
            'display' => $loc['osm_display_name'] ?? ($loc['osm_name'] ?? ''),
        ])->values()->all();
    }

    public function selectStore(int $locationId, string $label): void
    {
        $profile = Auth::user()->profile;
        if (! $profile) {
            return;
        }

        $profile->update([
            'open_prices_location_id' => $locationId,
            'open_prices_location_label' => $label,
        ]);

        $this->reset(['storeSearch', 'storeCity', 'storeResults']);
        session()->flash('status', 'Magasin enregistré pour Open Prices.');
    }

    public function clearStore(): void
    {
        $profile = Auth::user()->profile;
        if (! $profile) {
            return;
        }

        $profile->update([
            'open_prices_location_id' => null,
            'open_prices_location_label' => null,
        ]);

        session()->flash('status', 'Magasin Open Prices retiré.');
    }

    public function render(BudgetService $budget, CommunityPriceService $community)
    {
        $profile = Auth::user()->profile;
        $projections = $budget->projections(Auth::user());

        return view('livewire.budget-manager', [
            'entries' => $budget->entriesFor(Auth::user()),
            'weekly' => $budget->weeklyTotal(Auth::user()),
            'projections' => $projections,
            'contributions' => $community->contributionsFor(Auth::user()),
            'selectedStoreId' => $profile?->open_prices_location_id,
            'selectedStoreLabel' => $profile?->open_prices_location_label,
            'storeBrands' => config('futurmeal.store_brands', []),
        ]);
    }
}
