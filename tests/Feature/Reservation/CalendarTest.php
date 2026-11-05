<?php

namespace Tests\Feature\Reservation;

use App\Enums\Duration;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    /** A date 10 days ahead and the calendar URL for its month. */
    private function target(): array
    {
        $date = now()->addDays(10);

        return [$date->toDateString(), '/availability?month='.$date->format('Y-m')];
    }

    private function book(string $date, array $over = []): Reservation
    {
        return Reservation::factory()->create(array_merge([
            'reservation_date' => $date,
            'blocking_date' => $date,
        ], $over));
    }

    public function test_visitors_see_available_and_reserved_but_no_names_or_statuses(): void
    {
        [$date, $url] = $this->target();
        $owner = User::factory()->create(['name' => 'Private Person']);
        $this->book($date, ['user_id' => $owner->id]);

        $this->get($url)->assertOk()
            ->assertSee('Available')
            ->assertSee('Reserved')
            ->assertDontSee('Private Person')
            ->assertDontSee('Slot Held');
    }

    public function test_half_day_reservation_blocks_the_date_like_a_whole_day(): void
    {
        [$date, $url] = $this->target();
        $this->book($date, ['duration' => Duration::HalfDay]);

        $this->get($url)->assertDontSee(route('reservations.create', ['date' => $date]), false);
    }

    public function test_expired_and_cancelled_dates_are_free_again(): void
    {
        [$date, $url] = $this->target();
        Reservation::factory()->released(ReservationStatus::Expired)->create(['reservation_date' => $date]);

        $this->get($url)->assertSee(route('reservations.create', ['date' => $date]), false);
    }

    public function test_owner_sees_their_status_badge_and_other_guests_only_see_reserved(): void
    {
        [$date, $url] = $this->target();
        $owner = User::factory()->create();
        $this->book($date, ['user_id' => $owner->id]);

        $this->actingAs($owner)->get($url)->assertSee('Slot Held')->assertSee('Your booking');

        $this->actingAs(User::factory()->create())->get($url)
            ->assertDontSee('Slot Held')->assertDontSee('Your booking');
    }

    public function test_admin_sees_every_badge_with_the_booker_name_and_no_booking_links(): void
    {
        [$date, $url] = $this->target();
        $owner = User::factory()->create(['name' => 'Maria Santos']);
        $this->book($date, ['user_id' => $owner->id]);

        $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk()
            ->assertSee('Slot Held')
            ->assertSee('Maria Santos')
            ->assertDontSee('/reserve?date=', false);
    }

    public function test_today_and_past_dates_cannot_be_chosen_but_tomorrow_can(): void
    {
        $this->get('/availability')->assertOk()
            ->assertDontSee(route('reservations.create', ['date' => today()->toDateString()]), false);

        $tomorrow = today()->addDay();
        $this->get('/availability?month='.$tomorrow->format('Y-m'))
            ->assertSee(route('reservations.create', ['date' => $tomorrow->toDateString()]), false);
    }

    public function test_bad_or_past_month_falls_back_to_the_current_month(): void
    {
        $this->get('/availability?month=banana')->assertOk()->assertSee(now()->format('F Y'));
        $this->get('/availability?month=2020-01')->assertOk()->assertSee(now()->format('F Y'));
    }

    public function test_the_calendar_explains_that_a_reserved_date_may_reopen(): void
    {
        $this->get('/availability')->assertSee('may reopen if a hold expires');
    }

    public function test_visitor_choosing_a_date_logs_in_and_returns_to_the_form(): void
    {
        [$date] = $this->target();
        $user = User::factory()->create();

        $this->get('/reserve?date='.$date)->assertRedirect(route('login'));

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(url('/reserve?date='.$date));
    }

    public function test_admin_login_ignores_a_saved_reservation_link(): void
    {
        [$date] = $this->target();
        $admin = User::factory()->admin()->create();

        $this->get('/reserve?date='.$date);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
    }
}
