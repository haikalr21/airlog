<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToolControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper: Buat User Biasa (Level 1)
     */
    private function createRegularUser()
    {
        return User::factory()->create([
            'access_level' => 1,
        ]);
    }

    /**
     * Helper: Buat Admin (Level 2)
     */
    private function createAdminUser()
    {
        return User::factory()->create([
            'access_level' => 2,
        ]);
    }

    /**
     * Test 1: Halaman Index Tools bisa dibuka
     */
    public function test_tool_page_can_be_rendered(): void
    {
        $user = $this->createRegularUser();
        Tool::create(['name' => 'Obeng']);

        $response = $this
            ->actingAs($user)
            ->get(route('tools.index')); // /manage/tools

        $response->assertOk();
        $response->assertSee('Obeng');
    }

    /**
     * Test 2: Admin BISA MENAMBAH alat baru
     * Logika Controller: Jika peralatan_id = 0, maka Create.
     */
    public function test_admin_can_create_new_tool(): void
    {
        $admin = $this->createAdminUser();

        $response = $this
            ->actingAs($admin)
            ->post('/manage/tools/update', [ // Sesuai route web.php Anda
                'peralatan_id' => 0, // 0 artinya Buat Baru
                'tools_name' => 'Tang Kombinasi',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('successMessage', 'Peralatan baru berhasil ditambahkan');

        $this->assertDatabaseHas('alat', [ // Model Tool biasanya tabelnya 'alat' atau 'tools', sesuaikan jika error
            'name' => 'Tang Kombinasi',
        ]);
    }

    /**
     * Test 3: Admin BISA EDIT alat
     * Logika Controller: Jika peralatan_id > 0, maka Update.
     */
    public function test_admin_can_update_existing_tool(): void
    {
        $admin = $this->createAdminUser();
        $tool = Tool::create(['name' => 'Nama Lama']);

        $response = $this
            ->actingAs($admin)
            ->post('/manage/tools/update', [
                'peralatan_id' => $tool->id, // ID > 0 artinya Update
                'tools_name' => 'Nama Baru',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('successMessage', 'Peralatan berhasil diperbarui');

        $this->assertDatabaseHas('alat', [
            'id' => $tool->id,
            'name' => 'Nama Baru',
        ]);
    }

    /**
     * Test 4: User Biasa DITOLAK saat menambah/edit
     */
    public function test_regular_user_cannot_manage_tools(): void
    {
        $user = $this->createRegularUser();

        // Coba Create
        $response = $this
            ->actingAs($user)
            ->post('/manage/tools/update', [
                'peralatan_id' => 0,
                'tools_name' => 'Alat Ilegal',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('errorMessage', 'Anda tidak memiliki akses admin.');
        
        // Pastikan tidak masuk DB
        $this->assertDatabaseMissing('alat', ['name' => 'Alat Ilegal']);
    }

    /**
     * Test 5: Admin BISA MENGHAPUS alat
     */
    public function test_admin_can_delete_tool(): void
    {
        $admin = $this->createAdminUser();
        $tool = Tool::create(['name' => 'Alat Rusak']);

        $response = $this
            ->actingAs($admin)
            ->post('/manage/tools/delete', [
                'peralatan_id' => $tool->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('successMessage', 'Peralatan berhasil dihapus');

        $this->assertDatabaseMissing('alat', ['id' => $tool->id]);
    }

    /**
     * Test 6: Validasi Input (Nama Kosong)
     */
    public function test_tool_validation_error(): void
    {
        $admin = $this->createAdminUser();

        $response = $this
            ->actingAs($admin)
            ->post('/manage/tools/update', [
                'peralatan_id' => 0,
                'tools_name' => '', // Kosong
            ]);

        $response->assertRedirect();
        // Controller Anda mengambil error pertama dari validator
        // "The tools name field is required."
        $response->assertSessionHas('errorMessage'); 
    }
}