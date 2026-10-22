<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseGroup;
use App\Support\ExpenseMails;
use App\Support\ExpenseMath;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

// Shared expenses: add, edit and delete an expense of a group (any member can add one)
class ExpenseController extends Controller
{
    public function create(Request $request, ExpenseGroup $group)
    {
        abort_unless($group->hasMember($request->user()), 403, 'Unauthorized Action');

        return Inertia::render('Expenses/ExpenseForm', $this->formProps($request, $group, null));
    }

    public function store(Request $request, ExpenseGroup $group)
    {
        $user = $request->user();
        abort_unless($group->hasMember($user), 403, 'Unauthorized Action');
        [$data, $shares] = $this->validateExpense($request, $group);

        $expense = DB::transaction(function () use ($group, $user, $data, $shares) {
            $expense = $group->expenses()->create($data + ['created_by' => $user->id]);
            $this->saveShares($expense, $shares);

            return $expense;
        });
        $group->touch();

        ExpenseMails::expenseAdded($expense);

        return redirect("/expenses/{$group->id}")->with('message', 'Expense added!');
    }

    public function edit(Request $request, ExpenseGroup $group, Expense $expense)
    {
        abort_unless($group->hasMember($request->user()) && $expense->isEditableBy($request->user()), 403, 'Unauthorized Action');

        return Inertia::render('Expenses/ExpenseForm', $this->formProps($request, $group, $expense));
    }

    public function update(Request $request, ExpenseGroup $group, Expense $expense)
    {
        abort_unless($group->hasMember($request->user()) && $expense->isEditableBy($request->user()), 403, 'Unauthorized Action');
        [$data, $shares] = $this->validateExpense($request, $group);

        DB::transaction(function () use ($expense, $data, $shares) {
            $expense->update($data);
            $expense->shares()->delete();
            $this->saveShares($expense, $shares);
        });
        $group->touch();

        return redirect("/expenses/{$group->id}")->with('message', 'Expense saved!');
    }

    public function destroy(Request $request, ExpenseGroup $group, Expense $expense)
    {
        abort_unless($group->hasMember($request->user()) && $expense->isEditableBy($request->user()), 403, 'Unauthorized Action');

        $expense->delete();
        $group->touch();

        return redirect("/expenses/{$group->id}")->with('message', 'Expense deleted!');
    }

    // [expense fields, [userId => ['amount' => cents, 'shares' => n|null]]]
    private function validateExpense(Request $request, ExpenseGroup $group): array
    {
        $memberIds = $group->members->pluck('id')->all();
        $data = $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required',
            'spent_on' => 'required|date',
            'paid_by' => ['required', 'integer', 'in:' . implode(',', $memberIds)],
            'split_type' => 'required|in:' . implode(',', Expense::SPLIT_TYPES),
            'split' => 'required|array',
        ]);

        $amount = ExpenseMath::parse($data['amount']);
        if (! $amount) {
            throw ValidationException::withMessages(['amount' => 'Write an amount in euro, like 12.50']);
        }

        // Only the group's members, and only who's in (unticked, 0 or empty = not in)
        $split = collect($data['split'])->only($memberIds);
        $values = match ($data['split_type']) {
            'equal' => $split->filter(fn ($in) => filter_var($in, FILTER_VALIDATE_BOOLEAN)),
            'exact' => $split->filter(fn ($euros) => trim((string) $euros) !== '')->map(fn ($euros) => ExpenseMath::parse($euros)),
            'shares' => $split->filter(fn ($shares) => (int) $shares > 0)->map(fn ($shares) => (int) $shares),
        };

        if ($data['split_type'] === 'exact') {
            if ($values->contains(null)) {
                throw ValidationException::withMessages(['split' => 'Write each amount in euro, like 12.50']);
            }
            $values = $values->filter();
            if ($values->sum() !== $amount) {
                throw ValidationException::withMessages(['split' => 'The amounts add up to ' . ExpenseMath::format($values->sum()) . ', not ' . ExpenseMath::format($amount) . '.']);
            }
        }
        if ($values->isEmpty()) {
            throw ValidationException::withMessages(['split' => 'Choose who shares this expense.']);
        }
        if ($data['split_type'] === 'shares' && $values->contains(fn ($shares) => $shares > 1000)) {
            throw ValidationException::withMessages(['split' => 'Use smaller numbers of shares (at most 1000).']);
        }

        $split = ExpenseMath::split($amount, $data['split_type'], $values->all());
        $shares = collect($split)->map(fn ($cents, $userId) => [
            'amount' => $cents,
            'shares' => $data['split_type'] === 'shares' ? $values[$userId] : null,
        ])->all();

        return [[
            'description' => $data['description'],
            'amount' => $amount,
            'spent_on' => $data['spent_on'],
            'paid_by' => $data['paid_by'],
            'split_type' => $data['split_type'],
        ], $shares];
    }

    private function saveShares(Expense $expense, array $shares): void
    {
        foreach ($shares as $userId => $share) {
            $expense->shares()->create(['user_id' => $userId] + $share);
        }
    }

    private function formProps(Request $request, ExpenseGroup $group, ?Expense $expense): array
    {
        $expense?->load('shares');

        return [
            'group' => ['id' => $group->id, 'name' => $group->name],
            'me' => $request->user()->id,
            'members' => $group->members->map(fn ($member) => ['id' => $member->id, 'name' => $member->name, 'avatar' => Media::url($member->profilePicture)]),
            'expense' => $expense ? [
                'id' => $expense->id,
                'description' => $expense->description,
                'amount' => number_format($expense->amount / 100, 2, '.', ''),
                'spent_on' => $expense->spent_on->toDateString(),
                'paid_by' => $expense->paid_by,
                'split_type' => $expense->split_type,
                'shares' => $expense->shares->mapWithKeys(fn ($share) => [$share->user_id => [
                    'amount' => number_format($share->amount / 100, 2, '.', ''),
                    'shares' => $share->shares,
                ]]),
            ] : null,
        ];
    }
}
