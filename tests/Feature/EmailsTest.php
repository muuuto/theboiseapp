<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use App\Support\MailCover;
use App\Support\MailSamples;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

// The emails' shared design, the album previews in emails and the test emails of the maintenance page.
// Like ForumEmailsTest, reads what the "array" mailer really sent
class EmailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_admin_can_send_every_sample_email_to_themselves(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Muto']);
        $album = Listing::factory()->create(['title' => 'Sicilia 2026', 'logo' => $this->cover()]);
        $album->users()->attach($admin);

        foreach (array_keys(MailSamples::TEMPLATES) as $template) {
            $this->actingAs($admin)->post('/admin/maintenance/test-email', ['template' => $template])
                ->assertSessionHasNoErrors()
                ->assertSessionHas('message');
        }

        $messages = $this->sentMessages();
        $this->assertCount(count(MailSamples::TEMPLATES), $messages);
        foreach ($messages as $message) {
            $this->assertEquals([$admin->email], collect($message->getEnvelope()->getRecipients())->map->getAddress()->all());
            $this->assertStringContainsString('You get this email because', $message->getOriginalMessage()->getHtmlBody());
        }
        // The album samples use the admin's own albums
        $this->assertStringContainsString('Sicilia 2026', $this->htmlOf($messages->first()));
    }

    public function test_sample_emails_work_on_a_site_without_albums(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/admin/maintenance/test-email', ['template' => 'anniversary_reminder'])
            ->assertSessionHasNoErrors();

        $this->assertCount(1, $this->sentMessages());
    }

    public function test_only_admins_send_test_emails_and_only_known_templates(): void
    {
        $this->actingAs(User::factory()->create())->post('/admin/maintenance/test-email', ['template' => 'welcome_email'])->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->post('/admin/maintenance/test-email', ['template' => '../secret'])
            ->assertSessionHasErrors('template');

        $this->assertCount(0, $this->sentMessages());
    }

    public function test_maintenance_page_lists_the_templates(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/maintenance')
            ->assertInertia(fn ($page) => $page->where('mailTemplates.welcome_email', 'Welcome'));
    }

    public function test_preview_command_sends_every_email(): void
    {
        User::factory()->admin()->create();

        $this->artisan('mails:preview preview@example.com')->assertSuccessful();

        $this->assertCount(count(MailSamples::TEMPLATES), $this->sentMessages());
    }

    public function test_new_album_email_links_to_this_site_with_a_jpeg_preview(): void
    {
        $admin = User::factory()->admin()->create();
        $friend = User::factory()->create();

        $this->actingAs($admin)->post('/listings', [
            'title' => 'Sicilia 2026',
            'dateFrom' => '2026-08-01',
            'dateTo' => '2026-08-08',
            'location' => 'Palermo',
            'albumLink' => 'https://www.amazon.it/photos/share/abc',
            'tags' => ['beach'],
            'description' => '<p>Holidays</p>',
            'people' => [$friend->id],
            'cover' => UploadedFile::fake()->image('photo.jpg', 800, 600),
        ])->assertSessionHasNoErrors();
        $album = Listing::firstWhere('title', 'Sicilia 2026');

        $html = $this->htmlOf($this->sentMessages()->first());
        $this->assertStringContainsString(url('/listings/' . $album->id), $html);
        $this->assertStringNotContainsString('https://theboise.it/listings', $html);
        $this->assertStringContainsString(e(MailCover::url($album)), $html);
    }

    public function test_mail_cover_needs_the_signed_link(): void
    {
        $album = Listing::factory()->create(['logo' => $this->cover()]);

        $this->get('/mail/cover/' . $album->id)->assertForbidden();
        $this->get(MailCover::url($album))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_mail_cover_is_a_jpeg_without_the_transparent_bars(): void
    {
        $album = Listing::factory()->create(['logo' => $this->cover()]);

        [$width, $height, $type] = getimagesize(MailCover::jpeg($album));

        $this->assertEquals([400, 600, IMAGETYPE_JPEG], [$width, $height, $type]);
    }

    public function test_albums_without_a_preview_have_no_mail_cover(): void
    {
        $album = Listing::factory()->create(['logo' => null]);

        $this->assertNull(MailCover::url($album));
        $this->assertNull(MailCover::jpeg($album));
    }

    public function test_password_reset_email_uses_the_shared_design(): void
    {
        $user = User::factory()->create(['name' => 'Anna']);

        $this->post('/forgot-password', ['email' => $user->email]);

        $html = $this->htmlOf($this->sentMessages()->first());
        $this->assertStringContainsString('Hello there Anna', $html);
        $this->assertStringContainsString(url('/reset-password/'), $html);
        $this->assertStringContainsString('You get this email because', $html);
    }

    // A 400x600 photo padded to 800x600 with transparent bars, like the old previews
    private function cover(): string
    {
        $image = imagecreatetruecolor(800, 600);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefilledrectangle($image, 0, 0, 799, 599, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagefilledrectangle($image, 200, 0, 599, 599, imagecolorallocate($image, 200, 120, 60));
        ob_start();
        imagewebp($image, null, 100);
        Storage::disk('public')->put($path = 'logos/test.webp', ob_get_clean());

        return $path;
    }

    private function htmlOf($message): string
    {
        return $message->getOriginalMessage()->getHtmlBody();
    }

    private function sentMessages()
    {
        return app('mailer')->getSymfonyTransport()->messages();
    }
}
