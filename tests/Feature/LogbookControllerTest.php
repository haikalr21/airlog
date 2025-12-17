<?php

namespace Tests\Feature;

use App\Models\Logbook;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogbookControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser()
    {
        // UBAH JADI STRING '2'
        return User::factory()->create(['access_level' => '2']); 
    }

    private function createRegularUser()
    {
        // UBAH JADI STRING '0'
        return User::factory()->create(['access_level' => '0']); 
    }

    private function createApproverUser()
    {
        // UBAH JADI STRING '1'
        return User::factory()->create(['access_level' => '1']); 
    }

    /**
     * Test 1: Halaman Index Logbook per Unit bisa dibuka
     */
    public function test_logbook_index_can_be_rendered(): void
    {
        $user = $this->createRegularUser();
        $unit = Unit::create(['nama' => 'Unit Pembangkit']);
        
        // Buat dummy logbook
        Logbook::create([
            'unit_id' => $unit->id,
            'judul' => 'Laporan Harian',
            'date' => now(),
            'shift' => '1',
            'created_by' => $user->id,
            'is_approved' => 0 // Boolean di MySQL tinyint(1), jadi 0 aman
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('logbook.index', $unit->id));

        $response->assertOk();
        $response->assertSee('Laporan Harian');
    }

    /**
     * Test 2: User bisa membuat Logbook baru
     */
    public function test_user_can_create_logbook(): void
    {
        $user = $this->createRegularUser();
        $unit = Unit::create(['nama' => 'Unit Test']);

        $response = $this
            ->actingAs($user)
            ->post(route('logbook.store', $unit->id), [
                'nameWithTitle' => 'Logbook Shift Pagi',
                'dateWithTitle' => '2025-12-20',
                'radio_shift' => '1',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('successMessage', 'Logbook berhasil ditambahkan!');

        $this->assertDatabaseHas('logbooks', [
            'judul' => 'Logbook Shift Pagi',
            'unit_id' => $unit->id,
            'created_by' => $user->id,
        ]);
    }

    /**
     * Test 3: Approver (Level 1/2) BISA Approve Logbook
     */
    public function test_supervisor_can_approve_logbook(): void
    {
        $supervisor = $this->createApproverUser(); // Level 1
        $unit = Unit::create(['nama' => 'Unit Approve']);
        
        $logbook = Logbook::create([
            'unit_id' => $unit->id,
            'judul' => 'Pending Logbook',
            'date' => now(),
            'shift' => '1',
            'created_by' => User::factory()->create()->id,
            'is_approved' => 0
        ]);

        $response = $this
            ->actingAs($supervisor)
            ->put(route('logbook.approve', ['unit_id' => $unit->id, 'logbook_id' => $logbook->id]));

        $response->assertRedirect();
        $response->assertSessionHas('successMessage', 'Status logbook: Disetujui');

        $this->assertDatabaseHas('logbooks', [
            'id' => $logbook->id,
            'is_approved' => 1,
            'approved_by' => $supervisor->id,
        ]);
    }

    /**
     * Test 4: User Biasa (Level 0) TIDAK BISA Approve
     */
    public function test_regular_user_cannot_approve_logbook(): void
    {
        $user = $this->createRegularUser(); // Level 0
        $unit = Unit::create(['nama' => 'Unit Deny']);
        
        $logbook = Logbook::create([
            'unit_id' => $unit->id,
            'judul' => 'Logbook',
            'date' => now(),
            'shift' => '1',
            'created_by' => User::factory()->create()->id,
            'is_approved' => 0
        ]);

        $response = $this
            ->actingAs($user)
            ->put(route('logbook.approve', ['unit_id' => $unit->id, 'logbook_id' => $logbook->id]));

        // Di controller, jika user biasa akses approve, dia diredirect dengan error
        $response->assertRedirect();
        $response->assertSessionHas('errorMessage', 'Anda tidak memiliki hak akses');
        
        // Pastikan DB tidak berubah
        $this->assertDatabaseHas('logbooks', [
            'id' => $logbook->id,
            'is_approved' => 0,
        ]);
    }

    /**
     * Test 5: Pembuat Logbook BISA Menghapus Logbook Miliknya
     */
    public function test_creator_can_delete_own_logbook(): void
    {
        $user = $this->createRegularUser();
        $unit = Unit::create(['nama' => 'Unit Hapus']);
        
        $logbook = Logbook::create([
            'unit_id' => $unit->id,
            'judul' => 'Logbook Hapus',
            'date' => now(),
            'shift' => '1',
            'created_by' => $user->id, // Milik user ini
        ]);

        $response = $this
            ->actingAs($user)
            ->delete(route('logbook.destroy', ['unit_id' => $unit->id, 'logbook_id' => $logbook->id]));

        $response->assertRedirect();
        $response->assertSessionHas('successMessage', 'Logbook berhasil dihapus');

        $this->assertDatabaseMissing('logbooks', ['id' => $logbook->id]);
    }

    /**
     * Test 6: User Lain TIDAK BISA Menghapus Logbook Orang Lain
     */
    public function test_user_cannot_delete_others_logbook(): void
    {
        $owner = User::factory()->create();
        $hacker = $this->createRegularUser(); // User lain
        $unit = Unit::create(['nama' => 'Unit Aman']);
        
        $logbook = Logbook::create([
            'unit_id' => $unit->id,
            'judul' => 'Logbook Orang',
            'date' => now(),
            'shift' => '1',
            'created_by' => $owner->id, 
        ]);

        $response = $this
            ->actingAs($hacker)
            ->delete(route('logbook.destroy', ['unit_id' => $unit->id, 'logbook_id' => $logbook->id]));

        $response->assertRedirect();
        $response->assertSessionHas('errorMessage', 'Akses ditolak.');
        
        $this->assertDatabaseHas('logbooks', ['id' => $logbook->id]);
    }
}