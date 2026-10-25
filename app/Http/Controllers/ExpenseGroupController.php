<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseGroup;
use App\Models\ExpensePayment;
use App\Models\ExpenseShare;
use App\Models\Listing;
use App\Models\User;
use App\Support\ExpenseMath;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

// Shared expenses (like Splitwise): the groups. Only their members see them, admins included
class ExpenseGroupController extends Controller
{
    // My groups, with what I get back or owe in each
    public function index(Request $request)
    {
        $user = $request->user();
        $groups = $user->expenseGroups()
            ->with(['members', 'expenses.shares', 'payments', 'listing'])
            ->get()
            ->map(function (ExpenseGroup $group) use ($user) {
                $balances = ExpenseMath::balances($group);

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'album' => $group->listing ? ['id' => $group->listing->id, 'title' => $group->listing->title, 'cover' => Media::url($group->listing->logo)] : null,
                    'members' => $group->members->map(fn ($member) => $this->person($member)),
                    'myBalance' => $balances[$user->id] ?? 0,
                    'settled' => collect($balances)->every(fn ($cents) => $cents === 0),
                    'lastActivityAt' => collect([$group->updated_at, $group->expenses->max('created_at'), $group->payments->max('created_at')])->filter()->max()?->toIso8601String(),
                ];
            })
            ->sortByDesc('lastActivityAt')
            ->values();

        return Inertia::render('Expenses/Index', [
            'groups' => $groups,
            'totals' => [
                'owed' => $groups->sum(fn ($group) => max($group['myBalance'], 0)),
                'owe' => $groups->sum(fn ($group) => max(-$group['myBalance'], 0)),
            ],
        ]);
    }

    public function show(Request $request, ExpenseGroup $group)
    {
        $user = $request->user();
        $group->load(['members', 'listing', 'expenses.shares.user', 'expenses.payer', 'payments.from', 'payments.to']);
        abort_unless($group->hasMember($user), 403, 'Unauthorized Action');

        $balances = ExpenseMath::balances($group);
        $people = $group->members->keyBy('id');
        $name = fn ($id) => $people[$id]->name ?? User::find($id)?->name ?? 'Deleted user';

        $expenses = $group->expenses->map(fn (Expense $expense) => [
            'type' => 'expense',
            'id' => $expense->id,
            'description' => $expense->description,
            'amount' => $expense->amount,
            // In another currency: as written ("£45.00")
            'original' => $expense->currency ? ExpenseMath::format($expense->original_amount, $expense->currency) : null,
            'date' => $expense->spent_on->toDateString(),
            'createdAt' => $expense->created_at?->toIso8601String(),
            'paidBy' => $expense->payer?->name ?? 'Deleted user',
            'paidByMe' => $expense->paid_by == $user->id,
            'splitType' => $expense->split_type,
            'myShare' => $expense->shares->firstWhere('user_id', $user->id)?->amount,
            'shares' => $expense->shares->sortBy(fn ($share) => $share->user?->name)->values()->map(fn (ExpenseShare $share) => [
                'name' => $share->user?->name ?? 'Deleted user',
                'amount' => $share->amount,
                'shares' => $share->shares,
            ]),
            'canEdit' => $expense->setRelation('group', $group)->isEditableBy($user),
        ]);
        $payments = $group->payments->map(fn (ExpensePayment $payment) => [
            'type' => 'payment',
            'id' => $payment->id,
            'from' => $payment->from?->name ?? 'Deleted user',
            'to' => $payment->to?->name ?? 'Deleted user',
            'involvesMe' => in_array($user->id, [$payment->from_user_id, $payment->to_user_id]),
            'amount' => $payment->amount,
            'date' => $payment->paid_on->toDateString(),
            'createdAt' => $payment->created_at?->toIso8601String(),
            'canDelete' => $payment->setRelation('group', $group)->isEditableBy($user),
        ]);

        return Inertia::render('Expenses/Group', [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'album' => $group->listing ? ['id' => $group->listing->id, 'title' => $group->listing->title] : null,
                'canManage' => $group->isManagedBy($user),
                'settled' => collect($balances)->every(fn ($cents) => $cents === 0),
            ],
            'me' => $user->id,
            'members' => $group->members->map(fn ($member) => $this->person($member) + ['balance' => $balances[$member->id] ?? 0]),
            'debts' => collect(ExpenseMath::simplify($balances))->map(fn ($debt) => [
                'from' => ['id' => $debt['from'], 'name' => $name($debt['from'])],
                'to' => ['id' => $debt['to'], 'name' => $name($debt['to'])],
                'amount' => $debt['amount'],
            ]),
            // Newest first: by the date of the expense/payment, then by when it was added
            'activity' => $expenses->concat($payments)
                ->sortBy([['date', 'desc'], ['createdAt', 'desc']])
                ->values(),
            'total' => $group->expenses->sum('amount'),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Expenses/GroupForm', $this->formProps($request->user(), null));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $data = $this->validateGroup($request, $user);

        $group = ExpenseGroup::create([
            'name' => $data['name'],
            'listing_id' => $data['listing_id'] ?? null,
            'created_by' => $user->id,
        ]);
        $group->members()->sync(collect($data['members'])->push($user->id)->unique()->all());

        return redirect("/expenses/{$group->id}")->with('message', 'Group created!');
    }

    public function edit(Request $request, ExpenseGroup $group)
    {
        abort_unless($group->isManagedBy($request->user()) && $group->hasMember($request->user()), 403, 'Unauthorized Action');

        return Inertia::render('Expenses/GroupForm', $this->formProps($request->user(), $group));
    }

    public function update(Request $request, ExpenseGroup $group)
    {
        $user = $request->user();
        abort_unless($group->isManagedBy($user) && $group->hasMember($user), 403, 'Unauthorized Action');
        $data = $this->validateGroup($request, $user, $group);

        // Whoever has expenses or payments in the group stays, or the balances wouldn't add up
        $members = collect($data['members'])->push($user->id)->unique();
        $removed = $group->members->pluck('id')->diff($members);
        $involved = $removed->filter(fn ($id) => $this->isInvolved($group, $id));
        if ($involved->isNotEmpty()) {
            throw ValidationException::withMessages([
                'members' => __(':names can\'t leave: they have expenses or payments in this group.', ['names' => User::findMany($involved)->pluck('name')->join(', ', ' ' . __('and') . ' ')]),
            ]);
        }

        $group->update(['name' => $data['name'], 'listing_id' => $data['listing_id'] ?? null]);
        $group->members()->sync($members->all());

        return redirect("/expenses/{$group->id}")->with('message', 'Group saved!');
    }

    // Only when everyone is settled: deleting would lose who owes what
    public function destroy(Request $request, ExpenseGroup $group)
    {
        abort_unless($group->isManagedBy($request->user()) && $group->hasMember($request->user()), 403, 'Unauthorized Action');
        if (collect(ExpenseMath::balances($group))->contains(fn ($cents) => $cents !== 0)) {
            return back()->withErrors(['group' => __('Settle up first: someone still owes money in this group.')]);
        }

        $group->delete();

        return redirect('/expenses')->with('message', 'Group deleted!');
    }

    private function validateGroup(Request $request, User $user, ?ExpenseGroup $group = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'listing_id' => 'nullable|integer|exists:listings,id',
            'members' => 'array',
            'members.*' => 'integer|exists:users,id',
        ]);

        // Only albums you're in (keeping the one already linked)
        if (! empty($data['listing_id']) && $data['listing_id'] != $group?->listing_id
            && ! $user->listing()->whereKey($data['listing_id'])->exists()) {
            throw ValidationException::withMessages(['listing_id' => __('You can only link an album you\'re in.')]);
        }
        // The demo account never joins: its money isn't real
        if (User::whereIn('id', $data['members'] ?? [])->where('is_demo', true)->exists()) {
            throw ValidationException::withMessages(['members' => __('The demo account can\'t join a group.')]);
        }

        $data['members'] ??= [];

        return $data;
    }

    private function isInvolved(ExpenseGroup $group, int $userId): bool
    {
        return $group->expenses()->where('paid_by', $userId)->exists()
            || ExpenseShare::whereIn('expense_id', $group->expenses()->select('id'))->where('user_id', $userId)->exists()
            || $group->payments()->where(fn ($query) => $query->where('from_user_id', $userId)->orWhere('to_user_id', $userId))->exists();
    }

    private function formProps(User $user, ?ExpenseGroup $group): array
    {
        return [
            'group' => $group ? [
                'id' => $group->id,
                'name' => $group->name,
                'listing_id' => $group->listing_id,
                'members' => $group->members->pluck('id'),
                'settled' => collect(ExpenseMath::balances($group))->every(fn ($cents) => $cents === 0),
            ] : null,
            'me' => $user->id,
            'users' => User::where('is_demo', false)->orderBy('name')->get()->map(fn ($person) => $this->person($person)),
            // Your albums, newest first, with their people to fill in the members in one click
            'albums' => $user->listing()->with('users:id,is_demo')->orderByDesc('dateFrom')->get()
                ->map(fn (Listing $listing) => [
                    'id' => $listing->id,
                    'title' => $listing->title,
                    'year' => substr($listing->dateFrom, 0, 4),
                    'people' => $listing->users->where('is_demo', false)->pluck('id')->values(),
                ]),
        ];
    }

    private function person(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'avatar' => Media::url($user->profilePicture)];
    }
}
