<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper untuk membuat user biasa (Bukan Admin)
     */
    private function createRegularUser()
    {
        return User::factory()->create([
            'access_level' => 1, // Anggap 1 adalah user biasa
        ]);
    }

    /**
     * Helper untuk membuat user Admin
     */
    private function createAdminUser()
    {
        return User::factory()->create([
            'access_level' => 2, // Sesuai controller Anda, level 2 adalah Admin
        ]);
    }

    /**
     * Test 1: Pastikan halaman daftar unit bisa dibuka
     */
    public function test_unit_page_can_be_rendered(): void
    {
        $user = $this->createRegularUser();
        
        // Buat beberapa dummy unit
        Unit::create(['nama' => 'Unit A']);
        Unit::create(['nama' => 'Unit B']);

        $response = $this
            ->actingAs($user)
            ->get(route('units.index')); // /manage/units

        $response->assertOk(); // Status 200
        $response->assertViewIs('manage.units.index'); // Pastikan view-nya benar
        $response->assertSee('Unit A'); // Pastikan data muncul
    }

    /**
     * Test 2: Admin BISA menambah unit baru
     */
    public function test_admin_can_create_unit(): void
    {
        $admin = $this->createAdminUser();

        $response = $this
            ->actingAs($admin)
            ->post(route('units.store'), [
                'nama' => 'Unit Baru',
            ]);

        // Cek redirect (biasanya back())
        $response->assertRedirect();
        
        // Cek session successMessage (sesuai controller Anda)
        $response->assertSessionHas('successMessage', 'Unit berhasil dibuat!');

        // Cek database
        $this->assertDatabaseHas('units', [
            'nama' => 'Unit Baru',
        ]);
    }

    /**
     * Test 3: User Biasa TIDAK BISA menambah unit (Harus ditolak)
     */
    public function test_regular_user_cannot_create_unit(): void
    {
        $user = $this->createRegularUser();

        $response = $this
            ->actingAs($user)
            ->post(route('units.store'), [
                'nama' => 'Unit Ilegal',
            ]);

        // Di controller Anda, user biasa di-redirect back dengan errorMessage
        $response->assertRedirect();
        $response->assertSessionHas('errorMessage', 'Hanya Admin yang bisa menambah unit.');

        // Pastikan TIDAK masuk database
        $this->assertDatabaseMissing('units', [
            'nama' => 'Unit Ilegal',
        ]);
    }

    /**
     * Test 4: Admin BISA mengupdate unit
     */
    public function test_admin_can_update_unit(): void
    {
        $admin = $this->createAdminUser();
        $unit = Unit::create(['nama' => 'Nama Lama']);

        $response = $this
            ->actingAs($admin)
            ->put(route('units.update', $unit->id), [
                'nama' => 'Nama Baru',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('successMessage', 'Unit berhasil diperbarui');

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'nama' => 'Nama Baru',
        ]);
    }

    /**
     * Test 5: Admin BISA menghapus unit
     */
    public function test_admin_can_delete_unit(): void
    {
        $admin = $this->createAdminUser();
        $unit = Unit::create(['nama' => 'Unit Dihapus']);

        $response = $this
            ->actingAs($admin)
            ->delete(route('units.destroy', $unit->id));

        $response->assertRedirect();
        $response->assertSessionHas('successMessage', 'Unit berhasil dihapus');

        $this->assertDatabaseMissing('units', [
            'id' => $unit->id,
        ]);
    }

    /**
     * Test 6: Validasi Input (Nama Kosong)
     */
    public function test_create_unit_validation_error(): void
    {
        $admin = $this->createAdminUser();

        $response = $this
            ->actingAs($admin)
            ->from(route('units.index')) // Simulasi user ada di halaman index
            ->post(route('units.store'), [
                'nama' => '', // Nama kosong
            ]);

        $response->assertRedirect(route('units.index'));
        $response->assertSessionHas('errorMessage', 'Nama unit wajib diisi.');
    }
}