<?php

namespace Tests\Feature;

use App\Models\System\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_index_redirects_to_the_sales_customers_tab(): void
    {
        $user = User::factory()->create(['role' => 'sales_clerk']);

        $this
            ->actingAs($user)
            ->get('/customers')
            ->assertRedirect(route('sales-orders.index', ['tab' => 'customers']));
    }

    public function test_customer_can_be_created_from_the_sales_page(): void
    {
        $user = User::factory()->create(['role' => 'sales_clerk']);

        $response = $this
            ->actingAs($user)
            ->post('/customers', [
                'name' => 'Test Customer',
                'customer_type' => 'Retail',
                'phone' => '09171234567',
                'email' => 'test.customer@example.com',
                'address' => '123 Test Street',
            ]);

        $response->assertRedirect(route('sales-orders.index', ['tab' => 'customers']));

        $this->assertDatabaseHas('customers', [
            'email' => 'test.customer@example.com',
            'address' => '123 Test Street',
        ]);
    }

    public function test_only_implemented_customer_routes_are_registered(): void
    {
        foreach (['index', 'store', 'update', 'destroy', 'export'] as $action) {
            $this->assertTrue(Route::has("customers.$action"), "customers.$action should be registered.");
        }

        foreach (['create', 'show', 'edit'] as $action) {
            $this->assertFalse(Route::has("customers.$action"), "customers.$action should not be registered.");
        }
    }

    public function test_customers_are_not_accessible_to_unrelated_roles(): void
    {
        $user = User::factory()->create(['role' => 'workshop_staff']);

        $this->actingAs($user)->get('/customers')->assertForbidden();
    }
}
