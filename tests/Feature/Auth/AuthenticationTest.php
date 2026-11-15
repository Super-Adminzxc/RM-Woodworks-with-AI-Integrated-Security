<?php

namespace Tests\Feature\Auth;

use App\Models\System\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_each_role_is_redirected_to_its_own_home(): void
    {
        $expectedHomes = [
            'admin' => 'dashboard',
            'inventory_clerk' => 'inventory',
            'procurement_officer' => 'procurement',
            'workshop_staff' => 'production',
            'sales_clerk' => 'sales',
            'accounting_staff' => 'dashboard',
        ];

        foreach ($expectedHomes as $role => $homeRoute) {
            $user = User::factory()->create(['role' => $role]);

            $response = $this->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

            $response->assertRedirect(route($homeRoute, absolute: false));
            $this->post('/logout');
        }
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false));
    }
}
