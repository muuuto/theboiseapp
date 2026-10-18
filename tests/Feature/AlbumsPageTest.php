<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlbumsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_albums_page_lists_the_users_albums_newest_first(): void
    {
        $user = User::factory()->create();
        $old = Listing::factory()->create(['title' => 'Old trip', 'dateFrom' => '2020-01-01', 'dateTo' => '2020-01-02']);
        $recent = Listing::factory()->create(['title' => 'Recent trip', 'dateFrom' => '2025-01-01', 'dateTo' => '2025-01-02']);
        $old->users()->attach($user, ['seen' => true]);
        $recent->users()->attach($user, ['seen' => false]);
        Listing::factory()->create(['title' => 'Not mine']);

        $this->actingAs($user)->get('/')
            ->assertInertia(fn ($page) => $page
                ->component('Albums/Index')
                ->where('albums.total', 2)
                ->where('albums.data.0.title', 'Recent trip')
                ->where('albums.data.0.isNew', true)
                ->where('albums.data.1.title', 'Old trip')
                ->where('albums.data.1.isNew', false)
                ->has('albums.data.0.tags', 2));
    }

    public function test_albums_can_be_searched_and_filtered_by_tag(): void
    {
        $user = User::factory()->create();
        Listing::factory()->create(['title' => 'Merano weekend', 'tags' => 'mountains'])->users()->attach($user);
        Listing::factory()->create(['title' => 'Sea', 'tags' => 'beach, Youtube'])->users()->attach($user);

        $this->actingAs($user)->get('/?search=Merano')
            ->assertInertia(fn ($page) => $page->where('albums.total', 1)->where('albums.data.0.title', 'Merano weekend')->where('filters.search', 'Merano'));

        $this->actingAs($user)->get('/?tag=Youtube')
            ->assertInertia(fn ($page) => $page->where('albums.total', 1)->where('albums.data.0.title', 'Sea'));
    }

    public function test_shared_data_has_the_user_and_notifications(): void
    {
        $user = User::factory()->admin()->create(['name' => 'Muto']);
        $listing = Listing::factory()->create(['title' => 'Unseen one']);
        $listing->users()->attach($user, ['seen' => false]);

        $this->actingAs($user)->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.name', 'Muto')
                ->where('auth.user.isAdmin', true)
                ->where('auth.user.isDemo', false)
                ->where('auth.user.darkMode', null)
                ->where('notifications.unseen.0.title', 'Unseen one'));
    }

    public function test_theme_choice_is_saved_on_the_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/')->put('/account/theme', ['theme' => 'dark'])->assertRedirect('/');
        $this->assertSame(1, (int) $user->fresh()->dark_mode);

        $this->actingAs($user)->put('/account/theme', ['theme' => 'system']);
        $this->assertNull($user->fresh()->dark_mode);

        $this->actingAs($user)->put('/account/theme', ['theme' => 'purple'])->assertSessionHasErrors('theme');
    }

    public function test_dark_mode_is_applied_before_the_page_is_drawn(): void
    {
        $user = User::factory()->create(['dark_mode' => 1]);

        $this->actingAs($user)->get('/')->assertSee('<html lang="en" class="dark">', false);
    }

    public function test_logout_from_a_vue_page_reloads_the_whole_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout', [], ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', '/login');

        $this->assertGuest();
    }
}
