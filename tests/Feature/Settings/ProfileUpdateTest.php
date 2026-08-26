<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
    }

    /**
     * The single admin account must be undeletable — replaces the starter kit's
     * "user can delete their account" tests, which asserted the opposite.
     */
    public function test_there_is_no_account_deletion_route()
    {
        $this->assertFalse(
            Route::has('profile.destroy'),
            'A profile.destroy route exists. The single admin account must not be deletable.'
        );
    }

    public function test_the_admin_account_cannot_be_deleted_via_the_model()
    {
        $user = User::factory()->create();

        $this->expectException(RuntimeException::class);

        try {
            $user->delete();
        } finally {
            $this->assertDatabaseHas('users', ['id' => $user->id]);
        }
    }
}
