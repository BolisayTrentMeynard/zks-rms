<?php

namespace Tests\Feature\Admin;

use App\Enums\ActorType;
use App\Enums\ReservationStatus;
use App\Models\ActivityLog;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalkInTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function valid(array $over = []): array
    {
        return array_merge([
            'guest_name' => 'Pedro Reyes',
            'guest_contact' => '09171234567',
            'reservation_date' => today()->addDays(30)->toDateString(),
            'duration' => 'whole_day',
            'booking_type' => 'pool',
            'pax' => 10,
            'occasion' => 'Birthday',
        ], $over);
    }

    public function test_a_walk_in_is_created_confirmed_with_no_account_hold_or_proof(): void
    {
        $data = $this->valid();

        $response = $this->actingAs($this->admin)->post(route('walk-ins.store'), $data);

        $r = Reservation::firstOrFail();
        $response->assertRedirect(route('admin.reservations.show', $r))->assertSessionHas('status');

        $this->assertTrue($r->is_walk_in);
        $this->assertNull($r->user_id);
        $this->assertSame('Pedro Reyes', $r->guest_name);
        $this->assertSame('09171234567', $r->guest_contact);
        $this->assertSame(ReservationStatus::Confirmed, $r->status);
        $this->assertNull($r->hold_expires_at);
        $this->assertSame($data['reservation_date'], $r->blocking_date->toDateString());
        $this->assertSame(0, $r->paymentProofs()->count());
    }

    public function test_it_is_logged_as_walk_in_entered_by_the_admin(): void
    {
        $this->actingAs($this->admin)->post(route('walk-ins.store'), $this->valid());

        $log = ActivityLog::where('event', 'walk_in_entered')->firstOrFail();
        $this->assertSame(ActorType::Admin, $log->actor_type);
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertNull($log->user_id);
        $this->assertSame(Reservation::first()->id, $log->reservation_id);
        $this->assertSame(1, ActivityLog::count());
    }

    public function test_today_is_allowed_but_a_past_date_is_not(): void
    {
        $this->actingAs($this->admin)->post(route('walk-ins.store'), $this->valid(['reservation_date' => today()->toDateString()]))
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Reservation::count());

        $this->post(route('walk-ins.store'), $this->valid(['reservation_date' => today()->subDay()->toDateString()]))
            ->assertSessionHasErrors('reservation_date');
        $this->assertSame(1, Reservation::count());
    }

    public function test_a_taken_date_is_refused_for_every_booking_type_and_nothing_is_saved(): void
    {
        $taken = Reservation::factory()->create();
        $date = $taken->reservation_date->toDateString();

        foreach (['pool', 'transient', 'room', 'venue_hall'] as $type) {
            $this->actingAs($this->admin)->post(route('walk-ins.store'), $this->valid(['reservation_date' => $date, 'booking_type' => $type, 'pax' => 3]))
                ->assertSessionHasErrors('reservation_date');
        }

        $this->assertSame(1, Reservation::count());
        $this->assertSame(0, ActivityLog::where('event', 'walk_in_entered')->count());
    }

    public function test_the_pax_caps_apply(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('walk-ins.store'), $this->valid(['booking_type' => 'transient', 'pax' => 8]))->assertSessionHasErrors('pax');
        $this->post(route('walk-ins.store'), $this->valid(['booking_type' => 'room', 'pax' => 13]))->assertSessionHasErrors('pax');
        $this->assertSame(0, Reservation::count());

        $this->post(route('walk-ins.store'), $this->valid(['booking_type' => 'transient', 'pax' => 7]))->assertSessionHasNoErrors();
        $this->post(route('walk-ins.store'), $this->valid(['reservation_date' => today()->addDays(31)->toDateString(), 'booking_type' => 'room', 'pax' => 12]))->assertSessionHasNoErrors();
        $this->post(route('walk-ins.store'), $this->valid(['reservation_date' => today()->addDays(32)->toDateString(), 'booking_type' => 'venue_hall', 'pax' => 500]))->assertSessionHasNoErrors();
        $this->assertSame(3, Reservation::count());
    }

    public function test_required_fields_and_bad_values_are_rejected(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('walk-ins.store'), [])->assertSessionHasErrors(['guest_name', 'guest_contact', 'reservation_date', 'duration', 'booking_type', 'pax', 'occasion']);
        $this->post(route('walk-ins.store'), $this->valid(['duration' => 'night']))->assertSessionHasErrors('duration');
        $this->post(route('walk-ins.store'), $this->valid(['booking_type' => 'spa']))->assertSessionHasErrors('booking_type');
        $this->post(route('walk-ins.store'), $this->valid(['pax' => 0]))->assertSessionHasErrors('pax');
        $this->post(route('walk-ins.store'), $this->valid(['reservation_date' => '15/12/2026']))->assertSessionHasErrors('reservation_date');
        $this->assertSame(0, Reservation::count());
    }

    public function test_only_the_admin_can_use_the_form(): void
    {
        $this->get(route('walk-ins.create'))->assertRedirect(route('login'));
        $this->post(route('walk-ins.store'), $this->valid())->assertRedirect(route('login'));

        $guest = User::factory()->create();
        $this->actingAs($guest)->get(route('walk-ins.create'))->assertForbidden();
        $this->actingAs($guest)->post(route('walk-ins.store'), $this->valid())->assertForbidden();

        $this->assertSame(0, Reservation::count());
    }

    public function test_the_form_preselects_the_date_from_the_calendar(): void
    {
        $date = today()->addDays(12)->toDateString();

        $this->actingAs($this->admin)->get(route('walk-ins.create', ['date' => $date]))
            ->assertOk()->assertSee('value="'.$date.'"', false);

        $this->get(route('walk-ins.create', ['date' => 'junk']))->assertOk()->assertSee('value="'.today()->toDateString().'"', false);
        $this->get(route('walk-ins.create', ['date' => today()->subDays(3)->toDateString()]))->assertOk()->assertSee('value="'.today()->toDateString().'"', false);
    }

    public function test_the_admin_calendar_links_open_dates_including_today_to_the_walk_in_form(): void
    {
        $this->actingAs($this->admin)->get('/availability')
            ->assertOk()
            ->assertSee(route('walk-ins.create', ['date' => today()->toDateString()]), false)
            ->assertDontSee('/reserve?date=', false);
    }

    public function test_a_walk_in_blocks_the_date_for_guests_and_shows_on_the_admin_pages(): void
    {
        $this->actingAs($this->admin)->post(route('walk-ins.store'), $this->valid());
        $r = Reservation::firstOrFail();
        $date = $r->reservation_date->toDateString();

        $this->get(route('admin.dashboard'))->assertSee('Pedro Reyes')->assertSee('(walk-in)');
        $this->get(route('admin.dashboard', ['q' => 'Pedro']))->assertSee('Pedro Reyes');
        $this->get(route('admin.reservations.show', $r))->assertOk()->assertSee('09171234567')->assertSee('No payment proof is needed');

        // A guest cannot book the same date.
        $this->actingAs(User::factory()->create())->post('/reservations', [
            'reservation_date' => $date, 'duration' => 'whole_day', 'booking_type' => 'pool', 'pax' => 5, 'occasion' => 'Reunion',
        ])->assertRedirect();
        $this->assertSame(1, Reservation::whereDate('blocking_date', $date)->count());
    }

    public function test_the_admin_can_cancel_a_walk_in_and_the_date_reopens(): void
    {
        $this->actingAs($this->admin)->post(route('walk-ins.store'), $this->valid());
        $r = Reservation::firstOrFail();

        $this->post(route('admin.reservations.cancel', $r))->assertSessionHas('status');

        $this->assertSame(ReservationStatus::Cancelled, $r->fresh()->status);
        $this->assertNull($r->fresh()->blocking_date);
    }
}
