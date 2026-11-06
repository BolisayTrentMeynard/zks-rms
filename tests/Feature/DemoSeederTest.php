<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_covers_every_status_and_both_requests(): void
    {
        Storage::fake(config('zk.proof_disk'));
        User::factory()->admin()->create();

        $this->seed(DemoSeeder::class);

        foreach (ReservationStatus::cases() as $status) {
            $this->assertTrue(Reservation::where('status', $status)->exists(), $status->value.' is missing');
        }
        $this->assertTrue(Reservation::where('change_request', 'cancel')->exists());
        $this->assertTrue(Reservation::where('change_request', 'reschedule')->exists());
        $this->assertTrue(Reservation::where('is_walk_in', true)->exists());
        $this->assertTrue(User::where('is_active', false)->exists());
    }

    public function test_demo_seeder_refuses_to_run_in_production(): void
    {
        User::factory()->admin()->create();
        $this->app['env'] = 'production';

        // Call the seeder itself: the db:seed command would stop to ask for confirmation in production.
        $this->expectException(RuntimeException::class);
        (new DemoSeeder)->run();
    }

    public function test_demo_seeder_refuses_to_run_twice(): void
    {
        Storage::fake(config('zk.proof_disk'));
        User::factory()->admin()->create();
        $this->seed(DemoSeeder::class);

        $this->expectException(RuntimeException::class);
        $this->seed(DemoSeeder::class);
    }

    public function test_plain_db_seed_does_not_create_demo_data(): void
    {
        config(['zk.admin.email' => 'admin@example.test', 'zk.admin.password' => 'a-long-test-password']);

        $this->seed();

        $this->assertSame(0, Reservation::count());
        $this->assertFalse(User::where('email', 'like', '%@demo.zks.test')->exists());
    }
}