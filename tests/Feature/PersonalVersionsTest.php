<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\ListingUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PersonalVersionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $anna;
    private User $zecca;
    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->admin = User::factory()->admin()->create();
        $this->anna = User::factory()->create();
        $this->zecca = User::factory()->create();
        $this->listing = Listing::factory()->create([
            'albumLink' => 'https://photos.example/general',
            'description' => '<p>General story</p>',
        ]);
        $this->listing->users()->attach([$this->admin->id, $this->anna->id, $this->zecca->id]);
    }

    public function test_each_person_sees_their_own_version_or_the_general_one(): void
    {
        ListingUser::where('listing_id', $this->listing->id)->where('user_id', $this->anna->id)->update([
            'custom_album_link' => 'https://photos.example/anna',
            'custom_description' => '<p>Anna story</p>',
        ]);

        $this->actingAs($this->anna)->get("/listings/{$this->listing->id}")
            ->assertInertia(fn ($page) => $page
                ->where('album.albumLink', 'https://photos.example/anna')
                ->where('album.description', '<p>Anna story</p>')
                // Nothing in the page tells her it's a personal version
                ->missing('album.personalized')
                ->where('album.personalizedCount', 0));

        $this->actingAs($this->zecca)->get("/listings/{$this->listing->id}")
            ->assertInertia(fn ($page) => $page
                ->where('album.albumLink', 'https://photos.example/general')
                ->where('album.description', '<p>General story</p>'));

        // Admins see how many people have their own version
        $this->actingAs($this->admin)->get("/listings/{$this->listing->id}")
            ->assertInertia(fn ($page) => $page->where('album.personalizedCount', 1));
    }

    public function test_only_the_link_can_be_personal_and_the_description_stays_general(): void
    {
        $this->save([['userId' => $this->anna->id, 'albumLink' => 'https://photos.example/anna', 'description' => '']]);

        $this->actingAs($this->anna)->get("/listings/{$this->listing->id}")
            ->assertInertia(fn ($page) => $page
                ->where('album.albumLink', 'https://photos.example/anna')
                ->where('album.description', '<p>Holidays</p>'));
    }

    public function test_the_form_saves_cleans_and_clears_personal_versions(): void
    {
        $this->save([['userId' => $this->anna->id, 'albumLink' => '', 'description' => '<p>Solo per te</p><script>alert(1)</script>']]);

        $row = ListingUser::where('listing_id', $this->listing->id)->where('user_id', $this->anna->id)->first();
        $this->assertNull($row->custom_album_link);
        $this->assertEquals('<p>Solo per te</p>', $row->custom_description);

        $this->actingAs($this->admin)->get("/listings/{$this->listing->id}/edit")
            ->assertInertia(fn ($page) => $page->where('album.personal.0.userId', $this->anna->id));

        // Saved again without versions: everyone back to the general ones
        $this->save([]);
        $this->assertNull(ListingUser::where('listing_id', $this->listing->id)->where('user_id', $this->anna->id)->value('custom_description'));
    }

    public function test_versions_only_for_people_in_the_album(): void
    {
        $outsider = User::factory()->create();

        $this->save([['userId' => $outsider->id, 'albumLink' => 'https://photos.example/x', 'description' => '']])
            ->assertSessionHasErrors('personal.0.userId');
    }

    public function test_the_demo_account_can_get_its_own_version(): void
    {
        $demo = User::factory()->create(['is_demo' => true]);
        $this->listing->users()->attach($demo);

        $this->save([['userId' => $demo->id, 'albumLink' => '', 'description' => '<p>A weekend trip with friends.</p>']], [$demo->id]);

        $this->actingAs($demo)->get("/listings/{$this->listing->id}")
            ->assertInertia(fn ($page) => $page->where('album.description', '<p>A weekend trip with friends.</p>'));
    }

    private function save(array $personal, array $extraPeople = [])
    {
        return $this->actingAs($this->admin)->put("/listings/{$this->listing->id}", [
            'title' => 'Trip',
            'dateFrom' => '2026-07-01',
            'dateTo' => '2026-07-05',
            'location' => 'Merano',
            'albumLink' => 'https://photos.example/general',
            'tags' => ['trip', 'Anna', 'Bob', 'Carlo', 'Dario'],
            'description' => '<p>Holidays</p>',
            'people' => array_merge([$this->admin->id, $this->anna->id, $this->zecca->id], $extraPeople),
            'personal' => $personal,
        ]);
    }
}
