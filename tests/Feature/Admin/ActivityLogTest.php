<?php

namespace Tests\Feature\Admin;

use App\Enums\ActorType;
use App\Enums\LogCategory;
use App\Models\ActivityLog;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function at(ActivityLog $log, string $when): ActivityLog
    {
        DB::table('activity_logs')->where('id', $log->id)->update(['created_at' => $when]);

        return $log->fresh();
    }

    private function reservationLog(Reservation $r, string $event = 'submitted', string $text = 'A reservation event'): ActivityLog
    {
        return ActivityLogger::reservation($event, $r, ActorType::Guest, $text, $r->user);
    }

    public function test_only_the_admin_can_open_it(): void
    {
        $this->get(route('admin.activity-log'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.activity-log'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.activity-log'))->assertOk();
    }

    public function test_it_is_read_only(): void
    {
        $this->actingAs($this->admin);
        $url = route('admin.activity-log');

        $this->post($url)->assertStatus(405);
        $this->put($url)->assertStatus(405);
        $this->delete($url)->assertStatus(405);
    }

    public function test_it_lists_events_newest_first_with_label_actor_and_description(): void
    {
        $r = Reservation::factory()->create();
        $old = $this->at($this->reservationLog($r, 'submitted', 'OLD-ENTRY-TEXT'), now()->subDays(2)->toDateTimeString());
        $new = $this->at(ActivityLogger::reservation('expired', $r, ActorType::System, 'NEW-ENTRY-TEXT'), now()->subDay()->toDateTimeString());

        $this->actingAs($this->admin)->get(route('admin.activity-log'))
            ->assertOk()
            ->assertSeeInOrder(['Hold expired', 'System', 'NEW-ENTRY-TEXT', 'Reservation submitted', 'Guest', 'OLD-ENTRY-TEXT'])
            ->assertSee($r->user->name);
    }

    public function test_the_admins_own_login_shows_the_admin_as_actor(): void
    {
        $this->post('/login', ['email' => $this->admin->email, 'password' => 'password']);

        $log = ActivityLog::where('event', 'login')->firstOrFail();
        $this->assertSame(ActorType::Admin, $log->actor_type);

        $this->get(route('admin.activity-log'))->assertSee('Logged in')->assertSee('Admin');
    }

    public function test_it_filters_by_event_type(): void
    {
        $r = Reservation::factory()->create();
        $this->reservationLog($r, 'submitted', 'RESERVATION-ROW');
        ActivityLogger::account('registered', $r->user, 'ACCOUNT-ROW');

        $this->actingAs($this->admin)->get(route('admin.activity-log', ['type' => 'reservation']))
            ->assertSee('RESERVATION-ROW')->assertDontSee('ACCOUNT-ROW');
        $this->get(route('admin.activity-log', ['type' => 'account']))
            ->assertSee('ACCOUNT-ROW')->assertDontSee('RESERVATION-ROW');
    }

    public function test_it_filters_by_date(): void
    {
        $r = Reservation::factory()->create();
        $this->at($this->reservationLog($r, 'submitted', 'JAN-ROW'), '2026-01-10 09:00:00');
        $this->at($this->reservationLog($r, 'confirmed', 'MAR-ROW'), '2026-03-10 09:00:00');
        $this->at($this->reservationLog($r, 'expired', 'MAY-ROW'), '2026-05-10 23:30:00');

        $this->actingAs($this->admin)->get(route('admin.activity-log', ['from' => '2026-02-01', 'to' => '2026-04-30']))
            ->assertSee('MAR-ROW')->assertDontSee('JAN-ROW')->assertDontSee('MAY-ROW');

        // The end date is included for the whole day.
        $this->get(route('admin.activity-log', ['from' => '2026-05-10', 'to' => '2026-05-10']))
            ->assertSee('MAY-ROW')->assertDontSee('MAR-ROW');
    }

    public function test_it_searches_by_guest_name_including_walk_ins(): void
    {
        $maria = Reservation::factory()->create(['user_id' => User::factory()->create(['name' => 'Maria Santos'])->id]);
        $this->reservationLog($maria, 'submitted', 'MARIA-ROW');

        $walkIn = Reservation::factory()->create(['user_id' => null, 'is_walk_in' => true, 'guest_name' => 'Pedro Reyes', 'guest_contact' => '0917']);
        ActivityLogger::reservation('walk_in_entered', $walkIn, ActorType::Admin, 'PEDRO-ROW', $this->admin);

        $this->actingAs($this->admin)->get(route('admin.activity-log', ['q' => 'Maria']))
            ->assertSee('MARIA-ROW')->assertDontSee('PEDRO-ROW');
        $this->get(route('admin.activity-log', ['q' => 'Pedro']))
            ->assertSee('PEDRO-ROW')->assertSee('Walk-in entered')->assertDontSee('MARIA-ROW');
    }

    public function test_it_can_show_one_reservation_or_one_guest(): void
    {
        $a = Reservation::factory()->create();
        $b = Reservation::factory()->create();
        $this->reservationLog($a, 'submitted', 'A-ROW');
        $this->reservationLog($b, 'submitted', 'B-ROW');
        ActivityLogger::account('profile_edited', $a->user, 'A-ACCOUNT-ROW');

        $this->actingAs($this->admin)->get(route('admin.activity-log', ['reservation' => $a->id]))
            ->assertSee('Showing one reservation')->assertSee('A-ROW')->assertDontSee('B-ROW')->assertDontSee('A-ACCOUNT-ROW');

        $this->get(route('admin.activity-log', ['guest' => $a->user_id]))
            ->assertSee('Showing one guest')->assertSee('A-ROW')->assertSee('A-ACCOUNT-ROW')->assertDontSee('B-ROW');
    }

    public function test_bad_filter_values_are_ignored(): void
    {
        $this->reservationLog(Reservation::factory()->create(), 'submitted', 'SOME-ROW');

        $this->actingAs($this->admin)->get(route('admin.activity-log', [
            'type' => 'nonsense', 'from' => 'yesterday', 'to' => '2026-13-45', 'reservation' => 'abc', 'guest' => '99999999',
        ]))->assertOk()->assertSee('SOME-ROW');
    }

    public function test_it_pages_at_25_and_shows_an_empty_state(): void
    {
        $this->actingAs($this->admin)->get(route('admin.activity-log'))->assertSee('No activity found.');

        $r = Reservation::factory()->create();
        for ($i = 0; $i < 30; $i++) {
            $this->reservationLog($r);
        }

        $response = $this->get(route('admin.activity-log'))->assertOk();
        $this->assertCount(25, $response->viewData('logs'));
        $this->assertSame(30, $response->viewData('logs')->total());
    }

    public function test_the_nav_and_reservation_page_link_to_it(): void
    {
        $r = Reservation::factory()->create();

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee('Activity Log');
        $this->get(route('admin.reservations.show', $r))
            ->assertSee(route('admin.activity-log', ['reservation' => $r->id]), false);

        $this->actingAs($r->user)->get(route('availability'))->assertDontSee('Activity Log');
    }
}
