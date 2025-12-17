<?php

namespace Tests\Feature;

use App\Models\Logbook;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_screen_can_be_rendered(): void
    {
        $user = User::factory()->create();
        
        // Buat data dummy: 1 Unit dengan 3 Logbook
        $unit = Unit::factory()->create();
        Logbook::factory()->count(3)->create([
            'unit_id' => $unit->id,
            'created_by' => $user->id
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk(); // Status 200
        $response->assertViewIs('dashboard');
        
        // Pastikan variabel penting dikirim ke view
        $response->assertViewHas(['units', 'totals', 'totalAll']);
    }
}