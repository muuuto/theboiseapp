<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\PushSubscription;
use App\Models\User;
use App\Support\PushSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

// English (default) and Italian: every text has its translation, and each person reads the site,
// the emails and the notifications in their language
class TranslationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_text_has_an_italian_translation(): void
    {
        $italian = json_decode(File::get(lang_path('it.json')), true);
        $missing = [];
        $placeholders = [];

        foreach ($this->keys() as $key => $file) {
            if (! array_key_exists($key, $italian)) {
                $missing[] = "{$key}   ({$file})";
                continue;
            }
            // Same :placeholders on both sides, or a name or number would go missing
            preg_match_all('/:([a-z]+)/i', $key, $english);
            preg_match_all('/:([a-z]+)/i', $italian[$key], $translated);
            if (array_diff(array_unique($english[1]), array_unique($translated[1]))) {
                $placeholders[] = $key;
            }
        }

        $this->assertSame([], $missing, "Texts without Italian (add them to lang/it.json):\n" . implode("\n", $missing));
        $this->assertSame([], $placeholders, "Italian missing a :placeholder:\n" . implode("\n", $placeholders));
    }

    public function test_the_language_follows_the_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/account')->assertInertia(fn ($page) => $page->where('locale', 'en'));

        $this->actingAs($user)->put('/locale', ['locale' => 'it'])->assertRedirect();
        $this->assertSame('it', $user->fresh()->locale);
        $this->actingAs($user->fresh())->get('/account')->assertInertia(fn ($page) => $page->where('locale', 'it'));

        $this->actingAs($user)->put('/locale', ['locale' => 'fr'])->assertSessionHasErrors('locale');
    }

    public function test_before_logging_in_the_choice_lasts_the_visit(): void
    {
        $this->put('/locale', ['locale' => 'it']);

        $this->get('/login')->assertInertia(fn ($page) => $page->where('locale', 'it'));
        // Server messages too
        $this->post('/users/authenticate', ['email' => 'nobody@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => 'Email o password sbagliate.']);
    }

    public function test_messages_and_form_errors_in_italian(): void
    {
        $user = User::factory()->create(['locale' => 'it']);

        $this->actingAs($user)->put('/account/password', ['current_password' => 'wrong', 'password' => 'x', 'password_confirmation' => 'y'])
            ->assertSessionHasErrors(['current_password' => 'La password attuale non è corretta.']);
        $this->actingAs($user)->post('/expenses', [])->assertSessionHasErrors(['name' => 'Il campo nome è obbligatorio.']);
        $this->actingAs($user)->post('/expenses', ['name' => 'Casa'])->assertRedirect();
        $this->actingAs($user)->get('/expenses')->assertInertia(fn ($page) => $page->where('flash.message', 'Gruppo creato!'));
    }

    public function test_emails_and_notifications_in_each_persons_language(): void
    {
        $sent = [];
        $this->app->instance(PushSender::class, new class($sent) extends PushSender {
            public function __construct(private array &$sent) {}

            public function send(Collection $subscriptions, array $payload): array
            {
                foreach ($subscriptions as $subscription) {
                    $this->sent[$subscription->user_id] = $payload;
                }

                return [];
            }
        });
        $admin = User::factory()->admin()->create(['name' => 'Muto']);
        $anna = User::factory()->create(['locale' => 'it']);
        $bob = User::factory()->create();
        foreach ([$anna, $bob] as $user) {
            PushSubscription::create(['user_id' => $user->id, 'endpoint' => "https://fcm.googleapis.com/{$user->id}", 'endpoint_hash' => hash('sha256', (string) $user->id), 'public_key' => 'k', 'auth_token' => 'a']);
        }

        $this->actingAs($admin)->post('/listings', [
            'title' => 'Sicilia', 'dateFrom' => '2026-08-01', 'dateTo' => '2026-08-08', 'location' => 'Palermo',
            'albumLink' => 'https://www.amazon.it/photos/share/abc', 'tags' => ['mare'], 'description' => '<p>x</p>', 'cover' => $this->fakeCover(),
            'people' => [$admin->id, $anna->id, $bob->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Nuovo album: Sicilia', $sent[$anna->id]['title']);
        $this->assertSame('New album: Sicilia', $sent[$bob->id]['title']);
        $emails = app('mailer')->getSymfonyTransport()->messages()->mapWithKeys(fn ($message) => [$message->getEnvelope()->getRecipients()[0]->getAddress() => $message->getOriginalMessage()]);
        $this->assertSame('Nuovo album Sicilia su theboise.it', $emails[$anna->email]->getSubject());
        $this->assertStringContainsString('Muto ha pubblicato un nuovo album', $emails[$anna->email]->getHtmlBody());
        $this->assertStringContainsString('agosto 2026', $emails[$anna->email]->getHtmlBody());
        $this->assertSame('New album Sicilia added to theboise.it', $emails[$bob->email]->getSubject());
        // The admin's page stays in English after sending Italian emails
        $this->assertSame('en', app()->getLocale());
    }

    public function test_scheduled_emails_use_the_language_too(): void
    {
        $this->travelTo('2026-08-03 09:00');
        $anna = User::factory()->create(['locale' => 'it', 'birthday' => '1996-08-03']);
        $this->makeActive($anna);
        Listing::factory()->create(['dateFrom' => '2023-08-03', 'dateTo' => '2023-08-04'])->users()->attach($anna);

        $this->artisan('notifications:birthday')->assertSuccessful();
        $this->artisan('notifications:anniversaries')->assertSuccessful();

        $subjects = app('mailer')->getSymfonyTransport()->messages()->map(fn ($message) => $message->getOriginalMessage()->getSubject())->all();
        $this->assertContains('Buon compleanno da TheBoise!', $subjects);
        $this->assertContains('In questo giorno, anni fa', $subjects);
    }

    // Every text written for translation: t('...') / tc('...') in the pages, __('...') /
    // trans_choice('...') in the server code and the email templates
    private function keys(): array
    {
        $keys = [];
        $files = collect(File::allFiles(resource_path('js')))->concat(File::allFiles(resource_path('views')))
            ->concat(File::allFiles(app_path()))
            ->concat([base_path('routes/web.php')])
            // i18n.js only has examples in its comments
            ->filter(fn ($file) => preg_match('/\.(vue|js|php)$/', (string) $file) && ! str_ends_with((string) $file, 'i18n.js'));

        foreach ($files as $file) {
            $source = File::get((string) $file);
            $pattern = str_ends_with((string) $file, '.php')
                ? '/(?:__|trans_choice)\(\s*(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")/'
                : '/\b(?:t|tc)\(\s*(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")/';
            preg_match_all($pattern, $source, $matches);
            foreach ($matches[1] as $literal) {
                $key = stripcslashes(substr($literal, 1, -1));
                $keys[$key] = str_replace(base_path() . '/', '', (string) $file);
            }
        }

        return $keys;
    }
}
