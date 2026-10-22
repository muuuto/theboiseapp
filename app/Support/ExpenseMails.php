<?php

namespace App\Support;

use App\Models\Expense;
use App\Models\ExpensePayment;
use Illuminate\Support\Facades\Mail;

// Emails of shared expenses (the demo account never gets them: it can't join a group)
class ExpenseMails
{
    // To everyone with a share in a new expense, except who added it
    public static function expenseAdded(Expense $expense): void
    {
        $expense->load(['group', 'payer', 'creator', 'shares.user']);

        foreach ($expense->shares as $share) {
            if (! $share->user || $share->user_id == $expense->created_by || $share->amount === 0) {
                continue;
            }

            $yourShare = ExpenseMath::format($share->amount);
            Notify::push($share->user, 'expenses', [
                'title' => $expense->description . ': your share ' . $yourShare,
                'body' => ($expense->creator?->name ?? 'Someone') . ' added it in ' . $expense->group->name,
                'url' => '/expenses/' . $expense->group->id,
                'tag' => 'expense-' . $expense->id,
            ]);
            if (! Notify::wants($share->user, 'expenses', 'email')) {
                continue;
            }

            self::send($share->user, 'mails.expense_added', 'New expense in ' . $expense->group->name . ': ' . $expense->description, [
                'name' => $share->user->name,
                'addedBy' => $expense->creator?->name ?? 'Someone',
                'description' => $expense->description,
                'total' => ExpenseMath::format($expense->amount),
                'paidBy' => $expense->paid_by == $share->user_id ? 'you' : ($expense->payer?->name ?? 'someone'),
                'yourShare' => ExpenseMath::format($share->amount),
                'date' => $expense->spent_on->format('j F Y'),
                'groupName' => $expense->group->name,
                'link' => url('/expenses/' . $expense->group->id),
            ]);
        }
    }

    // To the two people of a payment, except who recorded it
    public static function paymentRecorded(ExpensePayment $payment): void
    {
        $payment->load(['group', 'from', 'to', 'creator']);

        foreach ([$payment->from, $payment->to] as $person) {
            if (! $person || $person->id == $payment->created_by) {
                continue;
            }
            $received = $person->id == $payment->to_user_id;
            $sentence = $received
                ? ($payment->from?->name ?? 'Someone') . ' paid you'
                : 'You paid ' . ($payment->to?->name ?? 'someone');
            Notify::push($person, 'expenses', [
                'title' => $sentence . ' ' . ExpenseMath::format($payment->amount),
                'body' => 'Recorded by ' . ($payment->creator?->name ?? 'someone') . ' in ' . $payment->group->name,
                'url' => '/expenses/' . $payment->group->id,
                'tag' => 'payment-' . $payment->id,
            ]);
            if (! Notify::wants($person, 'expenses', 'email')) {
                continue;
            }

            self::send($person, 'mails.expense_payment', 'Payment recorded in ' . $payment->group->name, [
                'name' => $person->name,
                'recordedBy' => $payment->creator?->name ?? 'Someone',
                'sentence' => $received
                    ? ($payment->from?->name ?? 'Someone') . ' paid you'
                    : 'You paid ' . ($payment->to?->name ?? 'someone'),
                'amount' => ExpenseMath::format($payment->amount),
                'date' => $payment->paid_on->format('j F Y'),
                'groupName' => $payment->group->name,
                'link' => url('/expenses/' . $payment->group->id),
            ]);
        }
    }

    private static function send($user, string $view, string $subject, array $data): void
    {
        Mail::send($view, $data, fn ($message) => $message->to($user->email, $user->name)
            ->subject($subject . ' - theboise.it')
            ->from('info@theboise.it', 'TheBoise'));
    }
}
