<?php

namespace Tests\Feature\Admin;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function booking(string $date, ReservationStatus $status = ReservationStatus::Confirmed, array $over = []): Reservation
    {
        $blocks = $status->blocksDate();

        return Reservation::factory()->create([
            'status' => $status,
            'hold_expires_at' => $status === ReservationStatus::SlotHeld ? now()->addHour() : null,
            'reservation_date' => $date,
            'blocking_date' => $blocks ? $date : null,
        ] + $over);
    }

    private function walkIn(string $date): Reservation
    {
        return $this->booking($date, ReservationStatus::Confirmed, [
            'user_id' => null, 'is_walk_in' => true, 'guest_name' => 'Pedro Reyes', 'guest_contact' => '0917',
        ]);
    }

    public function test_it_counts_confirmed_bookings_per_month_including_walk_ins(): void
    {
        $this->booking('2028-01-05');
        $this->booking('2028-01-20');
        $this->walkIn('2028-01-25');
        $this->booking('2028-03-01');

        $report = app(ReportService::class)->forYear(2028);

        $jan = $report['rows'][0];
        $this->assertSame(['January', 3, 1, 3, 31, 10], [$jan['label'], $jan['bookings'], $jan['walk_ins'], $jan['dates_booked'], $jan['days'], $jan['occupancy']]);
        $this->assertSame(0, $report['rows'][1]['bookings']); // February
        $this->assertSame(1, $report['rows'][2]['bookings']); // March
        $this->assertSame(4, $report['totals']['bookings']);
        $this->assertSame(1, $report['totals']['walk_ins']);
        $this->assertSame(4, $report['totals']['dates_booked']);
    }

    public function test_only_confirmed_reservations_are_counted(): void
    {
        $this->booking('2028-05-01');
        $this->booking('2028-05-02', ReservationStatus::SlotHeld);
        $this->booking('2028-05-03', ReservationStatus::VerifyingPayment);
        $this->booking('2028-05-04', ReservationStatus::ActionNeeded);
        $this->booking('2028-05-05', ReservationStatus::Expired);
        $this->booking('2028-05-06', ReservationStatus::Cancelled);

        $may = app(ReportService::class)->forYear(2028)['rows'][4];

        $this->assertSame(1, $may['bookings']);
        $this->assertSame(1, $may['dates_booked']);
    }

    public function test_a_booking_counts_in_the_month_of_its_reserved_date_and_the_right_year(): void
    {
        $this->booking('2027-12-31');
        $this->booking('2028-01-01');
        $this->booking('2028-12-31');
        $this->booking('2029-01-01');

        $report = app(ReportService::class)->forYear(2028);

        $this->assertSame(1, $report['rows'][0]['bookings']);  // Jan 1
        $this->assertSame(1, $report['rows'][11]['bookings']); // Dec 31
        $this->assertSame(2, $report['totals']['bookings']);
    }

    public function test_days_in_month_follow_leap_years(): void
    {
        $leap = app(ReportService::class)->forYear(2028);
        $normal = app(ReportService::class)->forYear(2027);

        $this->assertSame(29, $leap['rows'][1]['days']);
        $this->assertSame(28, $normal['rows'][1]['days']);
        $this->assertSame(366, $leap['totals']['days']);
        $this->assertSame(365, $normal['totals']['days']);
    }

    public function test_occupancy_is_a_rounded_percent_of_dates_booked(): void
    {
        foreach (['01', '02', '03'] as $day) { // 3 of 30 days in April = 10%
            $this->booking('2028-04-'.$day);
        }
        $this->assertSame(10, app(ReportService::class)->forYear(2028)['rows'][3]['occupancy']);
    }

    public function test_years_run_from_first_to_last_booking_and_include_the_current_year(): void
    {
        $this->booking('2031-06-01');
        $this->booking('2024-06-01');
        $current = (int) today()->format('Y');

        $years = app(ReportService::class)->years();

        $this->assertSame(max(2031, $current), $years[0]);
        $this->assertSame(min(2024, $current), end($years));
        $this->assertContains($current, $years);
    }

    public function test_with_no_bookings_it_still_shows_the_current_year(): void
    {
        $years = app(ReportService::class)->years();

        $this->assertSame([(int) today()->format('Y')], $years);
    }

    public function test_only_the_admin_can_open_it(): void
    {
        $this->get(route('admin.reports'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.reports'))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.reports'))->assertOk();
    }

    public function test_the_page_shows_the_table_and_the_year_filter_with_bad_values_ignored(): void
    {
        $this->booking('2028-02-10');
        $admin = User::factory()->admin()->create();
        $current = (int) today()->format('Y');

        $this->actingAs($admin)->get(route('admin.reports', ['year' => 2028]))
            ->assertOk()->assertSee('Reports')->assertSee('February')->assertSee('Whole year')->assertSee('1 of 29');

        foreach (['abc', '1900', '2028.5', '-1'] as $bad) {
            $response = $this->get(route('admin.reports', ['year' => $bad]))->assertOk();
            $this->assertSame($current, $response->viewData('year'));
        }
    }

    public function test_the_nav_link_is_for_the_admin_only(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertSee('Reports');
        $this->actingAs(User::factory()->create())->get(route('availability'))->assertDontSee('Reports');
    }
}
