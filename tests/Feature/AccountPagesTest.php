<?php

namespace Tests\Feature;

use App\Models\Slogan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_page_has_profile_and_wallet(): void
    {
        $user = User::factory()->create([
            'name' => 'Anna',
            'birthday' => '1995-04-12',
            'wallet' => json_encode(['name' => 'Patente', 'path' => 'wallet/x.jpg']),
        ]);

        $this->actingAs($user)->get('/account')
            ->assertInertia(fn ($page) => $page
                ->component('Account/Index')
                ->where('profile.name', 'Anna')
                ->where('profile.birthday', '1995-04-12')
                ->where('wallet.name', 'Patente'));
    }

    public function test_birthday_can_be_set_and_renaming_moves_the_slogans(): void
    {
        $user = User::factory()->create(['name' => 'Old']);
        Slogan::create(['sloganPhrase' => 'Ciao', 'author' => 'Old']);

        $this->actingAs($user)->put('/account/update', ['name' => 'New', 'birthday' => '1996-01-30'])->assertRedirect('/account');

        $this->assertEquals('1996-01-30', $user->fresh()->birthday);
        $this->assertEquals('New', Slogan::first()->author);
    }

    public function test_birthday_must_be_in_the_past(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/account/update', ['name' => $user->name, 'birthday' => now()->addDay()->format('Y-m-d')])
            ->assertSessionHasErrors('birthday');
    }

    public function test_password_can_be_changed_with_the_current_one(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'wrong',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->put('/account/password', [
            'current_password' => 'password',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertRedirect('/account');

        $this->assertTrue(Hash::check('new-password-1', $user->fresh()->password));
    }

    public function test_wallet_photo_is_saved_as_json(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->put('/account/addWallet', [
            'mediaName' => 'Carta identità',
            'addWallet' => UploadedFile::fake()->image('id.jpg'),
        ])->assertRedirect('/account');

        $wallet = json_decode($user->fresh()->wallet, true);
        $this->assertEquals('Carta identità', $wallet['name']);
        Storage::disk('public')->assertExists($wallet['path']);
    }

    public function test_webp_profile_pictures_work(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $image = imagecreatetruecolor(500, 500);
        $file = tempnam(sys_get_temp_dir(), 'me') . '.webp';
        imagewebp($image, $file);

        $this->actingAs($user)->put('/account/update', [
            'name' => $user->name,
            'profilePicture' => new UploadedFile($file, 'me.webp', 'image/webp', null, true),
        ])->assertRedirect('/account');

        [$width] = getimagesizefromstring(Storage::disk('public')->get($user->fresh()->profilePicture));
        $this->assertEquals(384, $width);
    }

    public function test_remember_me_keeps_you_logged_in(): void
    {
        $user = User::factory()->create();

        $this->post('/users/authenticate', ['email' => $user->email, 'password' => 'password', 'remember' => true]);

        $this->assertNotNull($user->fresh()->remember_token);
        $this->assertAuthenticatedAs($user);
    }

    public function test_guest_pages_are_vue_pages(): void
    {
        $this->get('/login')->assertInertia(fn ($page) => $page->component('Auth/Login'));
        $this->get('/forgot-password')->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
        $this->get('/reset-password/abc?email=anna@example.com')
            ->assertInertia(fn ($page) => $page->component('Auth/ResetPassword')->where('token', 'abc')->where('email', 'anna@example.com'));
    }

    public function test_forgot_password_gives_a_readable_message(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHas('status', fn ($status) => ! str_starts_with($status, 'passwords.'));
    }

    public function test_zecca_page_is_public(): void
    {
        $this->get('/zeccaGay')->assertInertia(fn ($page) => $page->component('Zecca')->has('answer')->where('auth.user', null));
        $this->actingAs(User::factory()->create())->get('/zeccaGay')->assertInertia(fn ($page) => $page->component('Zecca'));
    }

    public function test_monitor_lists_users_for_admins(): void
    {
        $admin = User::factory()->admin()->create(['last_login' => now()]);
        User::factory()->create(['last_login' => null, 'name' => 'Never']);

        $this->actingAs($admin)->get('/monitor')
            ->assertInertia(fn ($page) => $page->component('Admin/Monitor')->has('users', 2)->where('users.0.isAdmin', true));
    }

    public function test_slogan_page_and_old_manage_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/slogan/create')->assertInertia(fn ($page) => $page->component('Slogans/Create'));
        $this->actingAs($user)->get('/listings/manage')->assertRedirect('/');
    }
}
