<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Order of forum categories and posts chosen by the admins.
// Items never placed (all of them until the first reorder, then the new ones) come first, in the
// usual order, so admins see the new ones at the top and can move them
class ForumOrder
{
    private static ?bool $enabled = null;

    // Before the migration runs the column doesn't exist: the forum keeps working, unordered
    public static function enabled(): bool
    {
        return self::$enabled ??= Schema::hasColumn('categories', 'position') && Schema::hasColumn('posts', 'position');
    }

    public static function apply(Builder|Relation $query): Builder|Relation
    {
        return self::enabled() ? $query->orderByRaw('position IS NOT NULL')->orderBy('position') : $query;
    }

    // $current: every id in today's order (also the ones the admin can't see, e.g. hidden from them)
    // $chosen: the ids the admin put in order. The others keep their place after them
    public static function save(string $table, array $current, array $chosen): void
    {
        $chosen = array_values(array_intersect($chosen, $current));
        $order = array_merge($chosen, array_values(array_diff($current, $chosen)));

        DB::transaction(function () use ($table, $order) {
            foreach ($order as $index => $id) {
                DB::table($table)->where('id', $id)->update(['position' => $index + 1]);
            }
        });
    }
}
