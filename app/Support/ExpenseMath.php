<?php

namespace App\Support;

use App\Models\ExpenseGroup;

// The money of shared expenses, always in cents (integers: no rounding errors)
class ExpenseMath
{
    // "12,50" or "12.5" → 1250; null if it isn't an amount
    public static function parse(mixed $euros): ?int
    {
        $euros = str_replace([' ', '€'], '', (string) $euros);
        if (! preg_match('/^(\d{1,7})(?:[.,](\d{1,2}))?$/', $euros, $match)) {
            return null;
        }

        return (int) $match[1] * 100 + (int) str_pad($match[2] ?? '0', 2, '0');
    }

    // "€12.50" in English, "12,50 €" in Italian; another currency: "£45.00", "45,00 CHF"
    public static function format(int $cents, ?string $currency = null): string
    {
        $sign = $cents < 0 ? '-' : '';
        $symbol = ['GBP' => '£', 'USD' => '$', 'JPY' => '¥', 'CNY' => '¥'][$currency] ?? $currency ?? '€';
        $glued = mb_strlen($symbol) < 3; // "£45.00" but "CHF 45.00"

        return app()->getLocale() === 'it'
            ? $sign . number_format(abs($cents) / 100, 2, ',', '.') . ' ' . $symbol
            : $sign . $symbol . ($glued ? '' : ' ') . number_format(abs($cents) / 100, 2);
    }

    // What each person owes for an expense, adding up exactly to the total:
    // - equal:  $values = [userId => anything] (who's in), the leftover cents go one each to the first ones
    // - exact:  $values = [userId => cents], already adding up to the total (checked by the caller)
    // - shares: $values = [userId => shares] (2 nights, 3 nights), leftover cents to the biggest remainders
    public static function split(int $total, string $type, array $values): array
    {
        ksort($values);

        if ($type === 'exact') {
            return array_map('intval', $values);
        }

        $weights = $type === 'equal' ? array_fill_keys(array_keys($values), 1) : array_map('intval', $values);
        $sum = array_sum($weights);
        $result = [];
        $remainders = [];
        foreach ($weights as $userId => $weight) {
            $result[$userId] = intdiv($total * $weight, $sum);
            $remainders[$userId] = ($total * $weight) % $sum;
        }

        // The cents left by rounding down: one each, biggest remainder first (then lowest id)
        $left = $total - array_sum($result);
        uksort($remainders, fn ($a, $b) => [$remainders[$b], $a] <=> [$remainders[$a], $b]);
        foreach (array_slice(array_keys($remainders), 0, $left) as $userId) {
            $result[$userId]++;
        }
        ksort($result);

        return $result;
    }

    // [userId => cents] for every member: positive = gets money back, negative = owes money
    public static function balances(ExpenseGroup $group): array
    {
        $group->loadMissing(['members', 'expenses.shares', 'payments']);
        $balances = $group->members->mapWithKeys(fn ($member) => [$member->id => 0])->all();
        $add = function (int $userId, int $cents) use (&$balances) {
            $balances[$userId] = ($balances[$userId] ?? 0) + $cents;
        };

        foreach ($group->expenses as $expense) {
            $add($expense->paid_by, $expense->amount);
            foreach ($expense->shares as $share) {
                $add($share->user_id, -$share->amount);
            }
        }
        foreach ($group->payments as $payment) {
            $add($payment->from_user_id, $payment->amount);
            $add($payment->to_user_id, -$payment->amount);
        }

        return $balances;
    }

    // The fewest payments to settle everyone: [['from' => id, 'to' => id, 'amount' => cents]].
    // Whoever owes the most pays whoever gets back the most, until everyone is at zero
    public static function simplify(array $balances): array
    {
        $creditors = array_filter($balances, fn ($cents) => $cents > 0);
        $debtors = array_map(fn ($cents) => -$cents, array_filter($balances, fn ($cents) => $cents < 0));
        $payments = [];

        while ($creditors && $debtors) {
            $to = self::largest($creditors);
            $from = self::largest($debtors);
            $amount = min($creditors[$to], $debtors[$from]);
            $payments[] = ['from' => $from, 'to' => $to, 'amount' => $amount];

            $creditors[$to] -= $amount;
            $debtors[$from] -= $amount;
            if ($creditors[$to] === 0) {
                unset($creditors[$to]);
            }
            if ($debtors[$from] === 0) {
                unset($debtors[$from]);
            }
        }

        return $payments;
    }

    // Key of the biggest amount (lowest id on a tie, so the result is always the same)
    private static function largest(array $amounts): int
    {
        $max = max($amounts);

        return min(array_keys($amounts, $max, true));
    }
}
