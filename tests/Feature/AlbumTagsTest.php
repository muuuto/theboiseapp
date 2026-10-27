<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// The album tags library: one spelling per person, for everyone who posts
class AlbumTagsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        Tag::create(['name' => 'Matteo', 'aliases' => ['Matte']]);
        Tag::create(['name' => 'Anna']);
    }

    private function postAlbum(array $fields)
    {
        return $this->actingAs($this->admin)->post('/listings', $fields + [
            'title' => 'Sicilia', 'dateFrom' => '2026-08-01', 'dateTo' => '2026-08-03', 'location' => 'Palermo',
            'albumLink' => 'https://photos.google.com/share/abc', 'description' => '<p>x</p>', 'cover' => $this->fakeCover(),
            'people' => [$this->admin->id],
        ]);
    }

    public function test_tags_are_written_as_the_library_does(): void
    {
        $this->postAlbum(['tags' => ['matte', 'ANNA']])->assertSessionHasNoErrors();
        $this->assertSame('Matteo, Anna', Listing::sole()->tags);
    }

    public function test_a_new_name_like_an_existing_one_needs_a_confirmation(): void
    {
        $this->postAlbum(['tags' => ['Mat']])->assertSessionHasErrors('tags');
        $this->assertSame(0, Listing::count());

        // "No, it's someone else": added, marked new for the admins
        $this->postAlbum(['tags' => ['Mat'], 'newTags' => ['Mat']])->assertSessionHasNoErrors();
        $this->assertTrue(Tag::firstWhere('name', 'Mat')->is_new);
        // A name like nothing else needs no confirmation
        $this->postAlbum(['tags' => ['Zecca']])->assertSessionHasNoErrors();
        $this->assertTrue(Tag::where('name', 'Zecca')->exists());
    }

    public function test_photos_link_video_link_or_both(): void
    {
        $this->postAlbum(['tags' => ['Anna'], 'albumLink' => '', 'videoLinks' => ['https://youtu.be/dQw4w9WgXcQ']])->assertSessionHasNoErrors();
        $album = Listing::sole();
        $this->assertSame('', $album->albumLink);
        $this->assertSame('Anna, Youtube', $album->tags);
        $this->postAlbum(['tags' => ['Anna'], 'albumLink' => '', 'videoLinks' => []])->assertSessionHasErrors('albumLink');
    }

    public function test_admins_rename_and_merge_on_every_album(): void
    {
        $sara = Tag::create(['name' => 'Sara']);
        $typo = Tag::create(['name' => 'Sarà']);
        Listing::factory()->create(['tags' => 'Matteo, Sarà']);
        Listing::factory()->create(['tags' => 'Sara, Sarà, Anna']);

        $this->actingAs($this->admin)->get('/admin/tags')->assertInertia(fn ($page) => $page
            ->where('lookAlikes', fn ($pairs) => collect($pairs)->contains([$sara->id, $typo->id])));

        $this->actingAs($this->admin)->post("/admin/tags/{$typo->id}/merge", ['into' => $sara->id])->assertSessionHasNoErrors();
        $this->assertSame(['Matteo, Sara', 'Sara, Anna'], Listing::orderBy('id')->pluck('tags')->all());
        // Typing "Sarà" gives Sara from now on (same letters without the accent: no alias needed)
        $this->assertSame('Sara', \App\Support\Tags::resolve('Sarà', Tag::all())->name);
        $this->assertNull(Tag::find($typo->id));

        $user = User::factory()->create();
        $matteo = Tag::firstWhere('name', 'Matteo');
        $this->actingAs($this->admin)->put("/admin/tags/{$matteo->id}", ['name' => 'Matteo G', 'aliases' => ['Matte', 'Mat'], 'userId' => $user->id])->assertSessionHasNoErrors();
        $this->assertSame('Matteo G, Sara', Listing::orderBy('id')->first()->tags);
        $this->assertSame($user->id, $matteo->fresh()->user_id);
        // An alias can't be someone else's name
        $this->actingAs($this->admin)->put("/admin/tags/{$matteo->id}", ['name' => 'Matteo G', 'aliases' => ['Anna']])->assertSessionHasErrors('aliases');

        // "Not the same person": not suggested again
        $anna = Tag::firstWhere('name', 'Anna');
        $anne = Tag::create(['name' => 'Anne']);
        $this->actingAs($this->admin)->post('/admin/tags/distinct', ['ids' => [$anne->id, $anna->id]]);
        $this->actingAs($this->admin)->get('/admin/tags')->assertInertia(fn ($page) => $page->where('lookAlikes', []));

        // Only unused tags are deleted; only admins come here
        $this->actingAs($this->admin)->delete("/admin/tags/{$anna->id}")->assertStatus(422);
        $this->actingAs($this->admin)->delete("/admin/tags/{$anne->id}")->assertRedirect();
        $this->actingAs(User::factory()->create())->get('/admin/tags')->assertForbidden();
    }
}
