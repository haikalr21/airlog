<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test halaman profile utama (Show).
     */
    public function test_profile_show_page_can_be_rendered(): void
    {
        // Buat user dengan data lengkap (joined date penting untuk view)
        $user = User::factory()->create([
            'name' => 'testuser',
            'joined' => now(),
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('profile.show', $user->name));

        $response->assertOk();
        $response->assertViewIs('profile.show');
        $response->assertViewHas(['user', 'notifications', 'unreadCount']);
    }

    /**
     * Test halaman notifikasi profile (yang pakai Pagination).
     */
    public function test_profile_notifications_page_can_be_rendered(): void
    {
        $user = User::factory()->create([
            'name' => 'testuser',
            'joined' => now(),
        ]);

        // Buat dummy notifikasi agar tidak kosong
        $author = User::factory()->create();
        $notification = Notification::factory()->create([
            'author_id' => $author->id,
            'title' => 'Test Notification',
            'created_at' => now(),
        ]);
        
        // Attach ke user
        $user->notifications()->attach($notification->id, ['status' => 0]);

        $response = $this
            ->actingAs($user)
            ->get(route('profile.notifications', $user->name));

        $response->assertOk();
        $response->assertViewIs('profile.notifications');
        // Pastikan variabel yang dikirim bernama 'allNotifications' (sesuai controller Anda)
        $response->assertViewHas('allNotifications'); 
        $response->assertSee('Test Notification');
    }

    /**
     * Test fitur Generate QR Code.
     */
    public function test_generate_qr_code_redirects_correctly(): void
    {
        $user = User::factory()->create(['name' => 'qruser']);

        $response = $this
            ->actingAs($user)
            ->get(route('profile.qr', $user->name)); // Pastikan nama route di web.php adalah 'profile.qr'

        // Controller melakukan redirect ke API eksternal
        $response->assertRedirect();
        
        // Cek apakah redirect targetnya mengandung URL API QR Server
        $targetUrl = $response->headers->get('Location');
        $this->assertStringContainsString('api.qrserver.com', $targetUrl);
    }
}