<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseGroup;
use App\Models\Listing;
use App\Models\User;
use App\Support\ExpenseMath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Shared expenses (like Splitwise): money maths, who can do what, emails.
// Reads what the "array" mailer really sent (Mail::send with a view isn't caught by Mail::fake)
class ExpensesTest extends TestCase
{
    use RefreshDatabase;

    // --- Money maths ---

    public function test_amounts_are_read_with_comma_or_dot(): void
    {
        $this->assertSame(1250, ExpenseMath::parse('12,50'));
        $this->assertSame(1250, ExpenseMath::parse('12.5'));
        $this->assertSame(1200, ExpenseMath::parse('€ 12'));
        $this->assertNull(ExpenseMath::parse('12.505'));
        $this->assertNull(ExpenseMath::parse('-3'));
        $this->assertNull(ExpenseMath::parse('abc'));
    }

    public function test_equal_split_never_loses_a_cent(): void
    {
        $this->assertSame([1 => 334, 2 => 333, 3 => 333], ExpenseMath::split(1000, 'equal', [3 => true, 1 => true, 2 => true]));
        $this->assertSame(1, array_sum(ExpenseMath::split(1, 'equal', [1 => true, 2 => true, 3 => true])));
    }

    public function test_split_by_shares(): void
    {
        // 2 nights and 3 nights of a €100 house
        $this->assertSame([1 => 4000, 2 => 6000], ExpenseMath::split(10000, 'shares', [1 => 2, 2 => 3]));
        $split = ExpenseMath::split(1000, 'shares', [1 => 1, 2 => 1, 3 => 1]);
        $this->assertSame(1000, array_sum($split));
    }

    public function test_simplified_debts_need_the_fewest_payments(): void
    {
        // Anna gets back 30, Zecca 10; Bob owes 25, Carla 15
        $payments = ExpenseMath::simplify([1 => 3000, 2 => 1000, 3 => -2500, 4 => -1500]);

        $this->assertCount(3, $payments);
        $this->assertSame(['from' => 3, 'to' => 1, 'amount' => 2500], $payments[0]);
        // Everyone ends at zero
        $balances = [1 => 3000, 2 => 1000, 3 => -2500, 4 => -1500];
        foreach ($payments as $payment) {
            $balances[$payment['from']] += $payment['amount'];
            $balances[$payment['to']] -= $payment['amount'];
        }
        $this->assertSame([0, 0, 0, 0], array_values($balances));
    }

    // --- Groups ---

    public function test_creating_a_group_from_an_album(): void
    {
        [$me, $anna] = User::factory()->count(2)->create();
        $album = Listing::factory()->create(['title' => 'Sicilia 2026']);
        $album->users()->attach([$me->id, $anna->id]);

        $this->actingAs($me)->post('/expenses', ['name' => 'Sicilia 2026', 'listing_id' => $album->id, 'members' => [$anna->id]])
            ->assertRedirect();

        $group = ExpenseGroup::firstWhere('name', 'Sicilia 2026');
        // The creator is always in
        $this->assertEqualsCanonicalizing([$me->id, $anna->id], $group->members->pluck('id')->all());
        $this->assertEquals($album->id, $group->listing_id);
        // The album shows the group to its members
        $this->actingAs($me)->get("/listings/{$album->id}")
            ->assertInertia(fn ($page) => $page->where('expenseGroups.0.name', 'Sicilia 2026'));
    }

    public function test_only_albums_you_are_in_and_never_the_demo_account(): void
    {
        $me = User::factory()->create();
        $notMine = Listing::factory()->create();
        $demo = User::factory()->create(['is_demo' => true]);

        $this->actingAs($me)->post('/expenses', ['name' => 'X', 'listing_id' => $notMine->id])->assertSessionHasErrors('listing_id');
        $this->actingAs($me)->post('/expenses', ['name' => 'X', 'members' => [$demo->id]])->assertSessionHasErrors('members');
        $this->actingAs($demo)->get('/expenses')->assertForbidden();
        $this->assertSame(0, ExpenseGroup::count());
    }

    public function test_only_members_see_a_group_admins_included(): void
    {
        [$group, $me] = $this->group();

        $this->actingAs($me)->get("/expenses/{$group->id}")->assertOk();
        $this->actingAs(User::factory()->create())->get("/expenses/{$group->id}")->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get("/expenses/{$group->id}")->assertForbidden();
        $this->actingAs(User::factory()->create())->post("/expenses/{$group->id}/expenses", $this->expense([]))->assertForbidden();
    }

    public function test_index_shows_my_balance_in_each_group(): void
    {
        [$group, $me, $anna] = $this->group();
        $this->actingAs($me)->post("/expenses/{$group->id}/expenses", $this->expense([$me->id => true, $anna->id => true], amount: '30'));

        $this->actingAs($me)->get('/expenses')
            ->assertInertia(fn ($page) => $page->where('groups.0.myBalance', 1500)->where('totals.owed', 1500)->where('totals.owe', 0));
        $this->actingAs($anna)->get('/expenses')
            ->assertInertia(fn ($page) => $page->where('groups.0.myBalance', -1500)->where('totals.owe', 1500));
    }

    // --- Expenses ---

    public function test_expense_split_equally_updates_balances_and_debts(): void
    {
        [$group, $me, $anna, $bob] = $this->group();

        $this->actingAs($me)->post("/expenses/{$group->id}/expenses", $this->expense([$me->id => true, $anna->id => true, $bob->id => true], amount: '10'))
            ->assertSessionHasNoErrors();

        $this->assertSame([$me->id => 666, $anna->id => -333, $bob->id => -333], $this->balances($group));
        $this->actingAs($anna)->get("/expenses/{$group->id}")
            ->assertInertia(fn ($page) => $page
                ->where('activity.0.myShare', 333)
                ->where('debts.0.to.id', $me->id)
                ->has('debts', 2));
    }

    public function test_exact_amounts_must_add_up(): void
    {
        [$group, $me, $anna] = $this->group();

        $this->actingAs($me)->post("/expenses/{$group->id}/expenses", $this->expense([$me->id => '10', $anna->id => '15'], amount: '30', type: 'exact'))
            ->assertSessionHasErrors('split');
        $this->actingAs($me)->post("/expenses/{$group->id}/expenses", $this->expense([$me->id => '10', $anna->id => '20,00'], amount: '30', type: 'exact'))
            ->assertSessionHasNoErrors();

        $this->assertSame(-2000, $this->balances($group)[$anna->id]);
    }

    public function test_split_by_shares_and_strangers_are_ignored(): void
    {
        [$group, $me, $anna] = $this->group();
        $stranger = User::factory()->create();

        $this->actingAs($me)->post("/expenses/{$group->id}/expenses", $this->expense([$me->id => 2, $anna->id => 3, $stranger->id => 5], amount: '100', type: 'shares'))
            ->assertSessionHasNoErrors();

        $expense = Expense::first();
        $this->assertEqualsCanonicalizing([$me->id, $anna->id], $expense->shares->pluck('user_id')->all());
        $this->assertSame(6000, $expense->shares->firstWhere('user_id', $anna->id)->amount);
        $this->assertSame(3, $expense->shares->firstWhere('user_id', $anna->id)->shares);
    }

    public function test_bad_expenses_are_refused(): void
    {
        [$group, $me, $anna] = $this->group();
        $stranger = User::factory()->create();

        $this->actingAs($me)->post("/expenses/{$group->id}/expenses", $this->expense([$me->id => true], amount: '0'))->assertSessionHasErrors('amount');
        $this->actingAs($me)->post("/expenses/{$group->id}/expenses", $this->expense([$me->id => false]))->assertSessionHasErrors('split');
        $this->actingAs($me)->post("/expenses/{$group->id}/expenses", $this->expense([$me->id => true], paidBy: $stranger->id))->assertSessionHasErrors('paid_by');
        $this->assertSame(0, Expense::count());
    }

    public function test_who_can_edit_an_expense(): void
    {
        [$group, $me, $anna, $bob] = $this->group();
        $this->actingAs($anna)->post("/expenses/{$group->id}/expenses", $this->expense([$anna->id => true, $bob->id => true], paidBy: $anna->id));
        $expense = Expense::first();

        // Bob is in it but didn't add or pay it; the group creator ($me) can
        $this->actingAs($bob)->put("/expenses/{$group->id}/expenses/{$expense->id}", $this->expense([$bob->id => true]))->assertForbidden();
        $this->actingAs($me)->put("/expenses/{$group->id}/expenses/{$expense->id}", $this->expense([$anna->id => true, $bob->id => true], amount: '50', paidBy: $anna->id))
            ->assertSessionHasNoErrors();
        $this->assertSame(5000, $expense->fresh()->amount);

        $this->actingAs($anna)->delete("/expenses/{$group->id}/expenses/{$expense->id}")->assertRedirect();
        $this->assertSame(0, Expense::count());
    }

    public function test_an_expense_of_another_group_cant_be_reached(): void
    {
        [$group, $me] = $this->group();
        [$other, $stranger] = $this->group();
        $this->actingAs($stranger)->post("/expenses/{$other->id}/expenses", $this->expense([$stranger->id => true], paidBy: $stranger->id));
        $expense = Expense::first();

        $this->actingAs($me)->delete("/expenses/{$group->id}/expenses/{$expense->id}")->assertNotFound();
        $this->assertSame(1, Expense::count());
    }

    // --- Settle up ---

    public function test_paying_a_debt_settles_the_group(): void
    {
        [$group, $me, $anna, $bob] = $this->group();
        $this->actingAs($me)->post("/expenses/{$group->id}/expenses", $this->expense([$me->id => true, $anna->id => true], amount: '40'));

        $this->actingAs($anna)->post("/expenses/{$group->id}/payments", ['from_user_id' => $anna->id, 'to_user_id' => $me->id, 'amount' => '20.00'])
            ->assertSessionHasNoErrors();

        $this->assertSame([$me->id => 0, $anna->id => 0, $bob->id => 0], $this->balances($group));
        $this->actingAs($me)->get("/expenses/{$group->id}")->assertInertia(fn ($page) => $page->has('debts', 0)->where('group.settled', true));
    }

    public function test_payments_need_two_members_and_an_amount(): void
    {
        [$group, $me, $anna] = $this->group();
        $stranger = User::factory()->create();

        $this->actingAs($me)->post("/expenses/{$group->id}/payments", ['from_user_id' => $me->id, 'to_user_id' => $me->id, 'amount' => '5'])->assertSessionHasErrors('to_user_id');
        $this->actingAs($me)->post("/expenses/{$group->id}/payments", ['from_user_id' => $me->id, 'to_user_id' => $stranger->id, 'amount' => '5'])->assertSessionHasErrors('to_user_id');
        $this->actingAs($me)->post("/expenses/{$group->id}/payments", ['from_user_id' => $me->id, 'to_user_id' => $anna->id, 'amount' => 'x'])->assertSessionHasErrors('amount');
        $this->assertSame(0, $group->payments()->count());
    }

    // --- Members and deleting ---

    public function test_people_with_money_in_the_group_cant_be_removed(): void
    {
        [$group, $me, $anna, $bob] = $this->group();
        $this->actingAs($me)->post("/expenses/{$group->id}/expenses", $this->expense([$me->id => true, $anna->id => true]));

        $this->actingAs($me)->put("/expenses/{$group->id}", ['name' => 'Renamed', 'members' => [$me->id]])->assertSessionHasErrors('members');
        // Bob has nothing in it: he can go
        $this->actingAs($me)->put("/expenses/{$group->id}", ['name' => 'Renamed', 'members' => [$me->id, $anna->id]])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$me->id, $anna->id], $group->fresh()->members->pluck('id')->all());
        // Only the creator (or an admin) manages the group
        $this->actingAs($anna)->put("/expenses/{$group->id}", ['name' => 'Mine now', 'members' => [$anna->id]])->assertForbidden();
    }

    public function test_a_group_is_deleted_only_when_settled(): void
    {
        [$group, $me, $anna] = $this->group();
        $this->actingAs($me)->post("/expenses/{$group->id}/expenses", $this->expense([$me->id => true, $anna->id => true]));

        $this->actingAs($me)->delete("/expenses/{$group->id}")->assertSessionHasErrors('group');
        $this->assertModelExists($group);

        $this->actingAs($anna)->post("/expenses/{$group->id}/payments", ['from_user_id' => $anna->id, 'to_user_id' => $me->id, 'amount' => '5']);
        $this->actingAs($me)->delete("/expenses/{$group->id}")->assertRedirect('/expenses');
        $this->assertModelMissing($group);
    }

    // --- Emails ---

    public function test_people_in_a_new_expense_get_an_email_with_their_share(): void
    {
        [$group, $me, $anna, $bob] = $this->group();

        $this->actingAs($me)->post("/expenses/{$group->id}/expenses", $this->expense([$me->id => true, $anna->id => true], amount: '30'));

        // Anna (in it) yes; me (I added it) and Bob (not in it) no
        $this->assertEquals([$anna->email], $this->recipients());
        $html = $this->sentMessages()->first()->getOriginalMessage()->getHtmlBody();
        $this->assertStringContainsString('€15.00', $html);
        $this->assertStringContainsString(url("/expenses/{$group->id}"), $html);
    }

    public function test_a_payment_emails_the_other_person(): void
    {
        [$group, $me, $anna] = $this->group();

        $this->actingAs($me)->post("/expenses/{$group->id}/payments", ['from_user_id' => $me->id, 'to_user_id' => $anna->id, 'amount' => '20']);

        $this->assertEquals([$anna->email], $this->recipients());
        $this->assertStringContainsString('paid you', $this->sentMessages()->first()->getOriginalMessage()->getHtmlBody());
    }

    // A group created by $me with Anna and Bob: [group, me, anna, bob]
    // --- Another currency ---

    public function test_pounds_turned_into_euro_at_the_days_ecb_rate(): void
    {
        [$group, $me, $anna, $bob] = $this->group();
        $this->fakeHttp['api.frankfurter.app/2026-08-02'] = ['amount' => 1, 'base' => 'EUR', 'date' => '2026-07-31', 'rates' => ['GBP' => 0.8]];

        // £45 for three at 0.80 £/€ = €56.25: 1875 each
        $this->actingAs($me)->post("/expenses/{$group->id}/expenses", $this->expense([$me->id => true, $anna->id => true, $bob->id => true], amount: '45') + ['currency' => 'GBP'])
            ->assertSessionHasNoErrors();
        $expense = Expense::with('shares')->sole();
        $this->assertSame([5625, 'GBP', 4500, 0.8, false], [$expense->amount, $expense->currency, $expense->original_amount, (float) $expense->exchange_rate, (bool) $expense->rate_manual]);
        $this->assertSame([1875, 1875, 1875], $expense->shares->pluck('amount')->all());
        $this->assertSame([1500, 1500, 1500], $expense->shares->pluck('original_amount')->all());
        $this->assertSame([$me->id => 3750, $anna->id => -1875, $bob->id => -1875], $this->balances($group));
        $this->actingAs($anna)->get("/expenses/{$group->id}")->assertInertia(fn ($page) => $page->where('activity.0.original', '£45.00'));

        // The form while typing gets the same rate
        $this->actingAs($me)->getJson('/expenses/rate?currency=GBP&date=2026-08-02')->assertJson(['rate' => 0.8, 'date' => '2026-07-31']);

        // Editing shows it as written
        $this->actingAs($me)->get("/expenses/{$group->id}/expenses/{$expense->id}/edit")->assertInertia(fn ($page) => $page
            ->where('expense.amount', '45.00')
            ->where('expense.currency', 'GBP')
            ->where('expense.amount_eur', ''));
    }

    public function test_what_the_card_charged_and_exact_amounts_in_another_currency(): void
    {
        [$group, $me, $anna] = $this->group();

        // CHF 30 + CHF 10, the card charged €42: shares in proportion, adding up exactly
        $this->actingAs($me)->post("/expenses/{$group->id}/expenses", $this->expense([$me->id => '30', $anna->id => '10'], amount: '40', type: 'exact') + ['currency' => 'CHF', 'amount_eur' => '42'])
            ->assertSessionHasNoErrors();
        $expense = Expense::with('shares')->sole();
        $this->assertSame([4200, true], [$expense->amount, (bool) $expense->rate_manual]);
        $this->assertSame([3150, 1050], $expense->shares->sortBy('user_id')->pluck('amount')->values()->all());
        $this->actingAs($me)->get("/expenses/{$group->id}/expenses/{$expense->id}/edit")->assertInertia(fn ($page) => $page
            ->where('expense.amount_eur', '42.00')
            ->where("expense.shares.{$anna->id}.amount", '10.00'));

        // No rate from the bank and nothing written: asked for the euro amount
        $this->actingAs($me)->post("/expenses/{$group->id}/expenses", $this->expense([$me->id => true], amount: '10') + ['currency' => 'USD'])
            ->assertSessionHasErrors('amount_eur');
        $this->actingAs($me)->post("/expenses/{$group->id}/expenses", $this->expense([$me->id => true], amount: '10') + ['currency' => 'XYZ'])
            ->assertSessionHasErrors('currency');
        $this->assertSame(1, Expense::count());
    }

    private function group(): array
    {
        [$me, $anna, $bob] = User::factory()->count(3)->create();
        $group = ExpenseGroup::create(['name' => 'Sicilia 2026', 'created_by' => $me->id]);
        $group->members()->attach([$me->id, $anna->id, $bob->id]);

        return [$group, $me, $anna, $bob];
    }

    private function expense(array $split, string $amount = '10', string $type = 'equal', ?int $paidBy = null): array
    {
        return [
            'description' => 'Dinner',
            'amount' => $amount,
            'spent_on' => '2026-08-02',
            'paid_by' => $paidBy ?? auth()->id() ?? array_key_first($split),
            'split_type' => $type,
            'split' => $split,
        ];
    }

    private function balances(ExpenseGroup $group): array
    {
        $balances = ExpenseMath::balances($group->fresh());
        ksort($balances);

        return $balances;
    }

    private function sentMessages()
    {
        return app('mailer')->getSymfonyTransport()->messages();
    }

    private function recipients(): array
    {
        return $this->sentMessages()
            ->flatMap(fn ($message) => $message->getEnvelope()->getRecipients())
            ->map(fn ($address) => $address->getAddress())
            ->all();
    }
}
