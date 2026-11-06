<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Carbon\Carbon;

/**
 * Counts only: bookings per month and occupancy. No money.
 * A booking is a Confirmed reservation (walk-ins are Confirmed too), counted in the month of its reserved date.
 * Counting is done in PHP over a date range so it behaves the same on MySQL and SQLite.
 */
class ReportService
{
    /**
     * One row per month of the year, plus totals.
     *
     * @return array{rows: list<array{month: int, label: string, bookings: int, walk_ins: int, dates_booked: int, days: int, occupancy: int}>, totals: array{bookings: int, walk_ins: int, dates_booked: int, days: int, occupancy: int}}
     */
    public function forYear(int $year): array
    {
        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = Carbon::create($year, 12, 31)->startOfDay();

        $confirmed = Reservation::where('status', ReservationStatus::Confirmed)
            ->whereDate('reservation_date', '>=', $start->toDateString())
            ->whereDate('reservation_date', '<=', $end->toDateString())
            ->get(['reservation_date', 'is_walk_in'])
            ->groupBy(fn ($r) => (int) $r->reservation_date->format('n'));

        $rows = [];
        $totals = ['bookings' => 0, 'walk_ins' => 0, 'dates_booked' => 0, 'days' => 0];

        for ($month = 1; $month <= 12; $month++) {
            $items = $confirmed->get($month, collect());
            $days = Carbon::create($year, $month, 1)->daysInMonth;
            $dates = $items->map(fn ($r) => $r->reservation_date->toDateString())->unique()->count();

            $rows[] = [
                'month' => $month,
                'label' => Carbon::create($year, $month, 1)->format('F'),
                'bookings' => $items->count(),
                'walk_ins' => $items->where('is_walk_in', true)->count(),
                'dates_booked' => $dates,
                'days' => $days,
                'occupancy' => $this->percent($dates, $days),
            ];

            $totals['bookings'] += $items->count();
            $totals['walk_ins'] += $items->where('is_walk_in', true)->count();
            $totals['dates_booked'] += $dates;
            $totals['days'] += $days;
        }

        $totals['occupancy'] = $this->percent($totals['dates_booked'], $totals['days']);

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Years for the picker: from the first to the last year that has a Confirmed reservation,
     * always including the current year. Newest first.
     *
     * @return list<int>
     */
    public function years(): array
    {
        $base = Reservation::where('status', ReservationStatus::Confirmed);

        $first = $base->min('reservation_date');
        $last = $base->max('reservation_date');
        $current = (int) today()->format('Y');

        $from = min($current, $first ? (int) Carbon::parse($first)->format('Y') : $current);
        $to = max($current, $last ? (int) Carbon::parse($last)->format('Y') : $current);

        return range($to, $from);
    }

    private function percent(int $part, int $whole): int
    {
        return $whole > 0 ? (int) round($part / $whole * 100) : 0;
    }
}
