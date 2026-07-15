<?php

namespace Tests\Feature;

use App\Livewire\BodyMetricsChart;
use App\Models\BodyMetric;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BodyMetricsEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_record_a_past_weight(): void
    {
        $user = User::factory()->create();
        $pastDate = today()->subDays(45)->toDateString();

        Livewire::actingAs($user)
            ->test(BodyMetricsChart::class)
            ->set('recorded_at', $pastDate)
            ->set('weight_kg', 84.5)
            ->call('save')
            ->assertHasNoErrors();

        $metric = BodyMetric::where('user_id', $user->id)->first();

        $this->assertNotNull($metric);
        $this->assertSame($pastDate, $metric->recorded_at->toDateString());
        $this->assertSame(84.5, $metric->weight_kg);
    }

    public function test_saving_same_date_updates_existing_metric(): void
    {
        $user = User::factory()->create();
        $date = today()->subDays(10)->toDateString();

        $component = Livewire::actingAs($user)->test(BodyMetricsChart::class);

        $component->set('recorded_at', $date)->set('weight_kg', 85)->call('save');
        $component->set('recorded_at', $date)->set('weight_kg', 84)->call('save');

        $this->assertSame(1, BodyMetric::where('user_id', $user->id)->count());
        $this->assertSame(84.0, BodyMetric::where('user_id', $user->id)->first()->weight_kg);
    }

    public function test_future_date_is_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(BodyMetricsChart::class)
            ->set('recorded_at', today()->addDay()->toDateString())
            ->set('weight_kg', 80)
            ->call('save')
            ->assertHasErrors('recorded_at');

        $this->assertSame(0, BodyMetric::count());
    }

    public function test_past_day_can_be_filled_with_eaten_foods(): void
    {
        // Le journal passe par le DayEditor : toute date passée est éditable
        $user = User::factory()->create();
        $pastDate = today()->subDays(3)->toDateString();

        $food = \App\Models\FoodItem::create([
            'user_id' => $user->id,
            'reference_type' => 'custom',
            'name' => 'Pizza maison',
            'energy_kcal' => 250,
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\DayEditor::class, ['date' => $pastDate])
            ->call('openSlot', 'dinner')
            ->set('quantityG', 250)
            ->call('selectFoodForAdd', 'custom', $food->id, 'Pizza maison', null)
            ->call('addFood');

        $entry = \App\Models\MealPlanEntry::whereDate('planned_on', $pastDate)->first();

        $this->assertNotNull($entry);
        $this->assertSame('Pizza maison', $entry->label);
        $this->assertSame(250.0, $entry->quantity_g);
    }
}
