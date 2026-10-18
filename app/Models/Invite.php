<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

// Single-use, expiring registration link created by an admin
class Invite extends Model
{
    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    // Returns the invite and the plain code: the code is only known now, the database keeps its hash
    public static function generate(User $admin, ?string $note, int $days): array
    {
        $token = Str::random(40);

        $invite = static::create([
            'token_hash' => hash('sha256', $token),
            'note' => $note,
            'created_by' => $admin->id,
            'expires_at' => now()->addDays($days),
        ]);

        return [$invite, $token];
    }

    public static function findUsable(?string $token): ?Invite
    {
        if (! $token) {
            return null;
        }

        return static::where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }
}
