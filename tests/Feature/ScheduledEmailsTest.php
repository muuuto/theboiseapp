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

        $this->artisan('notifications:birthday')->assertSuccessful();

        Mail::assertSent(BirthdayReminderMail::class, 1);
        Mail::assertSent(BirthdayReminderMail::class, function (BirthdayReminderMail $mail) use ($birthdayToday) {
            return $mail->hasTo($birthdayToday->email) && $mail->age === 30;
        });
    }

    public function test_anniversary_mail_is_sent_to_album_members(): void
    {
        $member = User::factory()->create();
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
}
