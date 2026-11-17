<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
    }

    public function test_invalid_password_is_rejected(): void
    {
        $user = User::create([
            'name' => 'Test Admin',
            'email' => 'testadmin@example.com',
            'password' => Hash::make('CorrectPassword123!'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'WrongPassword123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_active_admin_can_access_dashboard(): void
    {
        $user = User::create([
            'name' => 'Test Admin',
            'email' => 'activeadmin@example.com',
            'password' => Hash::make('CorrectPassword123!'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect('/admin');

        $dashboard = $this->actingAs($user)->get('/admin');

        $dashboard->assertOk();
        $dashboard->assertSee('Test Admin');
        $dashboard->assertSee('Admin Dashboard');
    }

    public function test_inactive_user_cannot_access_dashboard(): void
    {
        $user = User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@example.com',
            'password' => Hash::make('CorrectPassword123!'),
            'role' => 'admin',
            'is_active' => false,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_logout_ends_authenticated_session(): void
    {
        $user = User::create([
            'name' => 'Logout Test',
            'email' => 'logout@example.com',
            'password' => Hash::make('CorrectPassword123!'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}