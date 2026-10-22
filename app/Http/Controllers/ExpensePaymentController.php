<?php

namespace App\Http\Controllers;

use App\Models\ExpenseGroup;
use App\Models\ExpensePayment;
use App\Support\ExpenseMails;
use App\Support\ExpenseMath;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

// Shared expenses: settle up ("Zecca paid Anna €20"), also from the "Paid ✓" button of a debt
class ExpensePaymentController extends Controller
{
    public function store(Request $request, ExpenseGroup $group)
    {
        $user = $request->user();
        abort_unless($group->hasMember($user), 403, 'Unauthorized Action');

        $members = implode(',', $group->members->pluck('id')->all());
        $data = $request->validate([
            'from_user_id' => "required|integer|in:{$members}",
            'to_user_id' => "required|integer|different:from_user_id|in:{$members}",
            'amount' => 'required',
            'paid_on' => 'nullable|date',
        ]);
        $amount = ExpenseMath::parse($data['amount']);
        if (! $amount) {
            throw ValidationException::withMessages(['amount' => 'Write an amount in euro, like 12.50']);
        }

        $payment = $group->payments()->create([
            'from_user_id' => $data['from_user_id'],
            'to_user_id' => $data['to_user_id'],
            'amount' => $amount,
            'paid_on' => $data['paid_on'] ?? now()->toDateString(),
            'created_by' => $user->id,
        ]);
        $group->touch();

        ExpenseMails::paymentRecorded($payment);

        return back()->with('message', 'Payment recorded!');
    }

    public function destroy(Request $request, ExpenseGroup $group, ExpensePayment $payment)
    {
        abort_unless($group->hasMember($request->user()) && $payment->isEditableBy($request->user()), 403, 'Unauthorized Action');

        $payment->delete();
        $group->touch();

        return back()->with('message', 'Payment deleted!');
    }
}
