<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// "Dinner, €84, paid by Zecca": amount in cents, split in shares between the group's members
class Expense extends Model
{
    use HasFactory;

    public const SPLIT_TYPES = ['equal', 'exact', 'shares'];

    protected $casts = ['spent_on' => 'date'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(ExpenseGroup::class, 'expense_group_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(ExpenseShare::class);
    }

    // Who added it or who paid it, and the group's managers
    public function isEditableBy(User $user): bool
    {
        return in_array($user->id, [$this->created_by, $this->paid_by]) || $this->group->isManagedBy($user);
    }
}
