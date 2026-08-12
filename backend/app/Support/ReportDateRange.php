<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class ReportDateRange
{
    public function __construct(
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly string $period,
        public readonly int $days,
    ) {}

    public static function fromRequest(Request $request): self
    {
        if ($request->filled('from') && $request->filled('to')) {
            $validated = $request->validate([
                'from' => ['required', 'date'],
                'to' => ['required', 'date', 'after_or_equal:from'],
            ]);

            $from = Carbon::parse($validated['from'])->startOfDay();
            $to = Carbon::parse($validated['to'])->endOfDay();

            if ($from->diffInDays($to) > 365) {
                throw ValidationException::withMessages([
                    'to' => ['The date range cannot exceed 365 days.'],
                ]);
            }

            $days = max(1, $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);

            return new self($from, $to, 'custom', $days);
        }

        $period = $request->query('period');

        if ($period !== null) {
            $validated = $request->validate([
                'period' => ['required', 'in:today,yesterday,7d,30d'],
            ]);
            $period = $validated['period'];

            return match ($period) {
                'today' => new self(now()->startOfDay(), now()->endOfDay(), 'today', 1),
                'yesterday' => new self(
                    now()->subDay()->startOfDay(),
                    now()->subDay()->endOfDay(),
                    'yesterday',
                    1,
                ),
                '7d' => new self(now()->subDays(6)->startOfDay(), now()->endOfDay(), '7d', 7),
                '30d' => new self(now()->subDays(29)->startOfDay(), now()->endOfDay(), '30d', 30),
            };
        }

        $days = min(90, max(1, (int) $request->query('days', 7)));

        return new self(
            now()->subDays($days - 1)->startOfDay(),
            now()->endOfDay(),
            "{$days}d",
            $days,
        );
    }
}
