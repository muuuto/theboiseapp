<?php

namespace Tests\Feature;

use App\Console\Mail\AnniversaryReminderMail;
use App\Console\Mail\BirthdayReminderMail;
use App\Console\Mail\ListingReminderMail;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ScheduledEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_birthday_mail_is_sent_with_the_right_age(): void
    {
        $birthdayToday = User::factory()->create(['birthday' => now()->subYears(30)->format('Y-m-d')]);
        User::factory()->create(['birthday' => now()->subYears(30)->addDays(3)->format('Y-m-d')]);
        // Same birthday, but not in any album for a year: left alone
        User::factory()->create(['birthday' => now()->subYears(25)->format('Y-m-d')]);
        $this->makeActive($birthdayToday);

        $this->artisan('notifications:birthday')->assertSuccessful();

        Mail::assertSent(BirthdayReminderMail::class, 1);
        Mail::assertSent(BirthdayReminderMail::class, function (BirthdayReminderMail $mail) use ($birthdayToday) {
            return $mail->hasTo($birthdayToday->email) && $mail->age === 30;
        });
    }

    public function test_anniversary_mail_is_sent_to_album_members(): void
    {
        $member = User::factory()->create();
        $this->makeActive($member);
        $listing = Listing::factory()->create([
            'dateFrom' => now()->subYears(3)->format('Y-m-d'),
            'dateTo' => now()->subYears(3)->addDays(2)->format('Y-m-d'),
        ]);
        $listing->users()->attach($member);
        Listing::factory()->create([
            'dateFrom' => now()->subYears(3)->addMonths(2)->format('Y-m-d'),
            'dateTo' => now()->subYears(3)->addMonths(2)->addDays(2)->format('Y-m-d'),
        ])->users()->attach($member);

        $this->artisan('notifications:anniversaries')->assertSuccessful();

        Mail::assertSent(AnniversaryReminderMail::class, function (AnniversaryReminderMail $mail) use ($member, $listing) {
            return $mail->hasTo($member->email)
                && $mail->listings->pluck('id')->all() === [$listing->id]
                && $mail->listings->first()->diffInYears === 3;
        });
    }

    public function test_reminder_mail_is_sent_for_unseen_albums_only(): void
    {
        $user = User::factory()->create();
        $this->makeActive($user);
        $unseen = Listing::factory()->create();
        $seen = Listing::factory()->create();
        $unseen->users()->attach($user, ['seen' => false]);
        $seen->users()->attach($user, ['seen' => true]);
        $seen->users()->attach(User::factory()->create(), ['seen' => true]);

        $this->artisan('listings:send-reminders')->assertSuccessful();

        Mail::assertSent(ListingReminderMail::class, 1);
        Mail::assertSent(ListingReminderMail::class, function (ListingReminderMail $mail) use ($user, $unseen) {
            return $mail->hasTo($user->email) && $mail->listings->pluck('id')->all() === [$unseen->id];
        });
    }

    public function test_people_not_in_any_album_for_a_year_get_no_automatic_emails(): void
    {
        $gone = User::factory()->create(['birthday' => now()->subYears(40)->format('Y-m-d')]);
        Listing::factory()->create([
            'dateFrom' => now()->subYears(3)->format('Y-m-d'),
            'dateTo' => now()->subYears(3)->addDays(2)->format('Y-m-d'),
        ])->users()->attach($gone, ['seen' => false]);

        $this->artisan('notifications:birthday')->assertSuccessful();
        $this->artisan('notifications:anniversaries')->assertSuccessful();
        $this->artisan('listings:send-reminders')->assertSuccessful();

        Mail::assertNothingSent();
        $this->assertFalse($gone->isActive());
        // One recent album and they're back
        $this->makeActive($gone);
        $this->assertTrue($gone->fresh()->isActive());
    }

    public function test_active_means_an_album_or_a_visit_in_the_last_18_months(): void
    {
        $oldAlbum = fn () => Listing::factory()->create(['dateFrom' => now()->subYears(3)->toDateString(), 'dateTo' => now()->subYears(3)->addDay()->toDateString()]);

        // Old albums only, but they opened the site a year ago: still interested
        $visitor = User::factory()->create(['last_login' => now()->subYear()]);
        $oldAlbum()->users()->attach($visitor);
        $this->assertTrue($visitor->isActive());

        // An album 15 months ago is enough
        $recent = User::factory()->create();
        Listing::factory()->create(['dateFrom' => now()->subMonths(15)->toDateString(), 'dateTo' => now()->subMonths(15)->toDateString()])->users()->attach($recent);
        $this->assertTrue($recent->isActive());

        // Neither for two years: gone
        $gone = User::factory()->create(['last_login' => now()->subYears(2)]);
        $oldAlbum()->users()->attach($gone);
        $this->assertFalse($gone->isActive());

        // The query agrees with the check
        $this->assertEqualsCanonicalizing([$visitor->id, $recent->id], User::active()->pluck('id')->all());
    }

    public function test_a_visit_updates_the_last_login_once_a_day(): void
    {
        $user = User::factory()->create(['last_login' => now()->subYears(2)]);

        $this->actingAs($user)->get('/account')->assertOk();
        $this->assertTrue($user->fresh()->isActive());
        $this->assertSame(now()->toDateString(), substr($user->fresh()->last_login, 0, 10));
    }
}
