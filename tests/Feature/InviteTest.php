<?php

namespace Tests\Feature;

use App\Models\Invite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InviteTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_without_invite_shows_invite_only_page(): void
    {
        $this->get('/register')->assertOk()->assertSee('Invite only')->assertDontSee('name="password"', false);
        $this->get('/register/not-a-real-code')->assertOk()->assertSee('Invite only');
        $this->get('/login')->assertDontSee('href="/register"', false);
    }

    public function test_valid_invite_shows_the_form(): void
    {
        [, $token] = $this->invite();

        $this->get("/register/{$token}")
            ->assertOk()
            ->assertSee('name="password"', false)
            ->assertSee('value="' . $token . '"', false);
    }

    public function test_registering_with_an_invite_creates_the_user_and_uses_the_invite(): void
    {
        [$invite, $token] = $this->invite('For Zecca');

        $this->post('/users', $this->registration($token))->assertRedirect('/');

        $user = User::where('email', 'zecca@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertEquals($user->id, $invite->fresh()->used_by);
        $this->assertNotNull($invite->fresh()->used_at);
    }

    public function test_registering_without_a_valid_invite_fails(): void
    {
        $this->post('/users', $this->registration(null))->assertSessionHasErrors('invite');
        $this->post('/users', $this->registration('wrong-code'))->assertSessionHasErrors('invite');

        $this->assertDatabaseMissing('users', ['email' => 'zecca@example.com']);
    }

    public function test_an_invite_works_only_once(): void
    {
        [, $token] = $this->invite();
        $this->post('/users', $this->registration($token));
        auth()->logout();

        $this->post('/users', $this->registration($token, 'someone@example.com'))->assertSessionHasErrors('invite');
        $this->get("/register/{$token}")->assertSee('Invite only');
        $this->assertDatabaseMissing('users', ['email' => 'someone@example.com']);
    }

    public function test_expired_invites_do_not_work(): void
    {
        [$invite, $token] = $this->invite();
        $invite->update(['expires_at' => now()->subMinute()]);

        $this->get("/register/{$token}")->assertSee('Invite only');
        $this->post('/users', $this->registration($token))->assertSessionHasErrors('invite');
    }

    public function test_the_database_does_not_contain_the_code(): void
    {
        [$invite, $token] = $this->invite();

        $this->assertNotEquals($token, $invite->token_hash);
        $this->assertDatabaseMissing('invites', ['token_hash' => $token]);
    }

    public function test_admin_creates_an_invite_link_shown_once(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/admin/invites', ['note' => 'For Zecca', 'days' => 7]);

        $response->assertRedirect('/admin/maintenance')->assertSessionHas('inviteLink');
        $token = basename(session('inviteLink'));
        $this->assertNotNull(Invite::findUsable($token));
        $this->assertEquals('For Zecca', Invite::first()->note);
        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, Invite::first()->expires_at->timestamp, 5);

        $this->actingAs($admin)->get('/admin/maintenance')->assertSee('For Zecca')->assertSee('open until');
    }

    public function test_only_admins_can_manage_invites(): void
    {
        [$invite] = $this->invite();
        $user = User::factory()->create();

        $this->actingAs($user)->post('/admin/invites', ['days' => 7])->assertForbidden();
        $this->actingAs($user)->delete("/admin/invites/{$invite->id}")->assertForbidden();
        $this->assertModelExists($invite);
    }

    public function test_admin_can_revoke_an_open_invite(): void
    {
        [$invite, $token] = $this->invite();

        $this->actingAs(User::factory()->admin()->create())
            ->delete("/admin/invites/{$invite->id}")
            ->assertRedirect('/admin/maintenance');

        $this->assertModelMissing($invite);
        $this->assertNull(Invite::findUsable($token));
    }

    public function test_logged_in_users_cannot_register(): void
    {
        [, $token] = $this->invite();

        $this->actingAs(User::factory()->create())->post('/users', $this->registration($token))->assertRedirect();
        $this->assertDatabaseMissing('users', ['email' => 'zecca@example.com']);
    }

    private function invite(?string $note = null): array
    {
        return Invite::generate(User::factory()->admin()->create(), $note, 7);
    }

    private function registration(?string $token, string $email = 'zecca@example.com'): array
    {
        return [
            'name' => 'Zecca',
            'email' => $email,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'invite' => $token,
        ];
    }
}
