<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function validRegistration(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'correct-horse',
            'password_confirmation' => 'correct-horse',
        ], $overrides);
    }

    public function test_register_and_login_pages_render_for_guests(): void
    {
        $this->get('/register')->assertOk()->assertSee('Create account');
        $this->get('/login')->assertOk()->assertSee('Remember me');
    }

    public function test_user_can_register_and_is_logged_in(): void
    {
        $this->post('/register', $this->validRegistration())->assertRedirect(route('posts.index'));

        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame('Jane Doe', $user->name);
        $this->assertTrue(Hash::check('correct-horse', $user->password));
        $this->assertNotSame('correct-horse', $user->password);
        $this->assertAuthenticatedAs($user);
    }

    public function test_email_is_stored_lowercase_and_uniqueness_ignores_case(): void
    {
        $this->post('/register', $this->validRegistration(['email' => 'Jane@Example.COM']));
        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);

        auth()->logout();
        $this->post('/register', $this->validRegistration(['email' => 'JANE@example.com', 'name' => 'Other']))
            ->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_is_validated(): void
    {
        $this->post('/register', $this->validRegistration(['name' => '']))->assertSessionHasErrors('name');
        $this->post('/register', $this->validRegistration(['email' => 'not-an-email']))->assertSessionHasErrors('email');
        $this->post('/register', $this->validRegistration(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertSessionHasErrors('password');
        $this->post('/register', $this->validRegistration(['password_confirmation' => 'different-one']))
            ->assertSessionHasErrors('password');
        $this->post('/register', ['email' => ['a@b.cd']] + $this->validRegistration())->assertSessionHasErrors('email');

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_user_can_log_in_with_any_email_casing(): void
    {
        User::factory()->create(['email' => 'jane@example.com', 'password' => 'correct-horse']);

        $this->post('/login', ['email' => 'Jane@Example.com', 'password' => 'correct-horse'])
            ->assertRedirect(route('posts.index'));

        $this->assertAuthenticated();
    }

    public function test_login_with_wrong_credentials_fails(): void
    {
        User::factory()->create(['email' => 'jane@example.com', 'password' => 'correct-horse']);

        $this->from('/login')
            ->post('/login', ['email' => 'jane@example.com', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'correct-horse'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_redirects_back_to_the_page_that_required_it(): void
    {
        $user = User::factory()->create();

        $this->get('/posts/create')->assertRedirect(route('login'));
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('posts.create'));
    }

    public function test_login_is_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', ['email' => 'x@example.com', 'password' => 'wrong'])->assertStatus(302);
        }

        $this->post('/login', ['email' => 'x@example.com', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect(route('posts.index'));

        $this->assertGuest();
    }

    public function test_guests_cannot_log_out_and_users_do_not_see_guest_pages(): void
    {
        $this->post('/logout')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());
        $this->get('/login')->assertRedirect('/');
        $this->get('/register')->assertRedirect('/');
    }
}
