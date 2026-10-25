<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\TripDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// Tickets, bookings and PDFs of a trip: private files only the people going can open
class TripDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private Trip $trip;
    private User $me;
    private User $anna;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(TripDocument::DISK);
        [$this->me, $this->anna] = User::factory()->count(2)->create();
        $this->trip = Trip::create(['title' => 'Sicilia 2027', 'created_by' => $this->me->id, 'date_from' => '2027-08-01', 'date_to' => '2027-08-08']);
        $this->trip->members()->sync([$this->me->id, $this->anna->id]);
    }

    private function pdf(string $name = 'boarding-pass.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 120, 'application/pdf');
    }

    public function test_add_open_and_remove_a_document(): void
    {
        $this->actingAs($this->me)->post("/trips/{$this->trip->id}/documents", ['file' => $this->pdf(), 'name' => 'Ryanair boarding pass', 'for' => $this->anna->id])
            ->assertSessionHasNoErrors();
        $document = TripDocument::sole();
        $this->assertSame([$this->anna->id, 'Ryanair boarding pass'], [$document->for_user_id, $document->name]);
        // Private: not in the public folder
        Storage::disk(TripDocument::DISK)->assertExists($document->path);
        $this->assertStringStartsWith("trip-documents/{$this->trip->id}/", $document->path);

        $this->actingAs($this->anna)->get("/trips/{$this->trip->id}")->assertInertia(fn ($page) => $page
            ->where('documents.0.name', 'Ryanair boarding pass')
            ->where('documents.0.forUser', $this->anna->id)
            ->where('documents.0.canDelete', true));
        $this->actingAs($this->anna)->get("/trips/{$this->trip->id}/documents/{$document->id}")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        // Anna was told it's there
        $this->assertStringContainsString('added "Ryanair boarding pass" for you', app('mailer')->getSymfonyTransport()->messages()->first()?->getOriginalMessage()->getSubject() ?? '');

        $this->actingAs($this->anna)->delete("/trips/{$this->trip->id}/documents/{$document->id}")->assertRedirect();
        $this->assertSame(0, TripDocument::count());
        Storage::disk(TripDocument::DISK)->assertMissing($document->path);
    }

    public function test_only_the_people_going(): void
    {
        $this->actingAs($this->me)->post("/trips/{$this->trip->id}/documents", ['file' => $this->pdf()]);
        $document = TripDocument::sole();
        $this->assertSame('boarding-pass', $document->name);
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get("/trips/{$this->trip->id}/documents/{$document->id}")->assertForbidden();
        $this->actingAs($stranger)->post("/trips/{$this->trip->id}/documents", ['file' => $this->pdf()])->assertForbidden();
        // Anna can open it, but only who added it (or the organiser) removes one that's nobody's
        $this->actingAs($this->anna)->delete("/trips/{$this->trip->id}/documents/{$document->id}")->assertForbidden();
        // A document of another trip isn't reachable through this one
        $other = Trip::create(['title' => 'Other', 'created_by' => $stranger->id]);
        $other->members()->sync([$stranger->id]);
        $this->actingAs($stranger)->get("/trips/{$other->id}/documents/{$document->id}")->assertNotFound();

        $this->actingAs($this->me)->post("/trips/{$this->trip->id}/documents", ['file' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('file');
        $this->actingAs($this->me)->post("/trips/{$this->trip->id}/documents", ['file' => $this->pdf(), 'for' => $stranger->id]);
        $this->assertNull(TripDocument::latest('id')->first()->for_user_id);

        // Deleting the trip deletes the files too
        $this->actingAs($this->me)->delete("/trips/{$this->trip->id}");
        $this->assertSame(0, TripDocument::count());
        Storage::disk(TripDocument::DISK)->assertDirectoryEmpty("trip-documents");
    }
}
