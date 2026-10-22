<?php

namespace App\Support;

use App\Models\Expense;
use App\Models\ExpensePayment;
use Illuminate\Support\Facades\Mail;

// Emails of shared expenses (the demo account never gets them: it can't join a group)
class ExpenseMails
{
    // To everyone with a share in a new expense, except who added it (in their language)
    public static function expenseAdded(Expense $expense): void
    {
        $expense->load(['group', 'payer', 'creator', 'shares.user']);

        foreach ($expense->shares as $share) {
            if (! $share->user || $share->user_id == $expense->created_by || $share->amount === 0) {
                continue;
            }

            Locale::for($share->user, function () use ($expense, $share) {
                $yourShare = ExpenseMath::format($share->amount);
                $addedBy = $expense->creator?->name ?? __('Someone');
                Notify::push($share->user, 'expenses', [
                    'title' => __(':what: your share :amount', ['what' => $expense->description, 'amount' => $yourShare]),
                    'body' => __(':name added it in :group', ['name' => $addedBy, 'group' => $expense->group->name]),
                    'url' => '/expenses/' . $expense->group->id,
                    'tag' => 'expense-' . $expense->id,
                ]);
                if (! Notify::wants($share->user, 'expenses', 'email')) {
                    return;
                }

                self::send($share->user, 'mails.expense_added', __('New expense in :group: :what', ['group' => $expense->group->name, 'what' => $expense->description]), [
                    'name' => $share->user->name,
                    'addedBy' => $addedBy,
                    'description' => $expense->description,
                    'total' => ExpenseMath::format($expense->amount),
                    'paidBy' => $expense->paid_by == $share->user_id ? __('you') : ($expense->payer?->name ?? __('someone')),
                    'yourShare' => $yourShare,
                    'date' => $expense->spent_on->translatedFormat('j F Y'),
                    'groupName' => $expense->group->name,
                    'link' => url('/expenses/' . $expense->group->id),
                ]);
            });
        }
    }

    // To the two people of a payment, except who recorded it (in their language)
    public static function paymentRecorded(ExpensePayment $payment): void
    {
        $payment->load(['group', 'from', 'to', 'creator']);

        foreach ([$payment->from, $payment->to] as $person) {
            if (! $person || $person->id == $payment->created_by) {
                continue;
            }

            Locale::for($person, function () use ($payment, $person) {
                $sentence = $person->id == $payment->to_user_id
                    ? __(':name paid you', ['name' => $payment->from?->name ?? __('Someone')])
                    : __('You paid :name', ['name' => $payment->to?->name ?? __('someone')]);
                $recordedBy = $payment->creator?->name ?? __('Someone');
                Notify::push($person, 'expenses', [
                    'title' => $sentence . ' ' . ExpenseMath::format($payment->amount),
                    'body' => __('Recorded by :name in :group', ['name' => $recordedBy, 'group' => $payment->group->name]),
                    'url' => '/expenses/' . $payment->group->id,
                    'tag' => 'payment-' . $payment->id,
                ]);
                if (! Notify::wants($person, 'expenses', 'email')) {
                    return;
                }

                self::send($person, 'mails.expense_payment', __('Payment recorded in :group', ['group' => $payment->group->name]), [
                    'name' => $person->name,
                    'recordedBy' => $recordedBy,
                    'sentence' => $sentence,
                    'amount' => ExpenseMath::format($payment->amount),
                    'date' => $payment->paid_on->translatedFormat('j F Y'),
                    'groupName' => $payment->group->name,
                    'link' => url('/expenses/' . $payment->group->id),
                ]);
            });
        }
    }

    private static function send($user, string $view, string $subject, array $data): void
    {
        Mail::send($view, $data, fn ($message) => $message->to($user->email, $user->name)
            ->subject($subject . ' - theboise.it')
            ->from('info@theboise.it', 'TheBoise'));
    }
}
