<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Settle up: "Zecca paid Anna €20", in cents
class ExpensePayment extends Model
{
    use HasFactory;

    protected $casts = ['paid_on' => 'date'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ExpenseGroup::class, 'expense_group_id');
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Who recorded it, the two people involved, and the group's managers
    public function isEditableBy(User $user): bool
    {
        return in_array($user->id, [$this->created_by, $this->from_user_id, $this->to_user_id]) || $this->group->isManagedBy($user);
    }
}
