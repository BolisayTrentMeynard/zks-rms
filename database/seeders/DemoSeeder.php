<?php

namespace Database\Seeders;

use App\Enums\ActorType;
use App\Enums\BookingType;
use App\Enums\ChangeRequest;
use App\Enums\Duration;
use App\Enums\ProofStatus;
use App\Enums\ReservationStatus;
use App\Models\PaymentProof;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Notifier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Clearly fake data for demos and the defense: guests and reservations in every status,
 * a pending cancel request, a pending reschedule request, a walk-in and a few past months.
 *
 * NOT called by `db:seed`. Run it on purpose, after the Admin exists:
 *   php artisan db:seed --class=DemoSeeder
 * It refuses to run in production, and refuses to run twice (reset with migrate:fresh --seed).
 * Every demo guest uses an @demo.zks.test email and the password "password".
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoSeeder is for demos only and will not run in production.');
        }

        if (User::where('email', 'like', '%@demo.zks.test')->exists()) {
            throw new RuntimeException('Demo data is already there. Run php artisan migrate:fresh --seed first to start over.');
        }

        $admin = User::where('role', 'admin')->first();
        if (! $admin) {
            throw new RuntimeException('Run php artisan db:seed first so the Admin account exists, then run the DemoSeeder.');
        }

        $g = [];
        foreach ([
            'ana' => 'Demo Ana Reyes',
            'ben' => 'Demo Ben Cruz',
            'carla' => 'Demo Carla Dizon',
            'dante' => 'Demo Dante Lim',
            'elena' => 'Demo Elena Ramos',
            'felix' => 'Demo Felix Tan',
            'gina' => 'Demo Gina Uy',
            'hugo' => 'Demo Hugo Perez',
        ] as $key => $name) {
            $g[$key] = User::factory()->create([
                'name' => $name,
                'email' => $key.'@demo.zks.test',
                'phone' => '0900 000 000'.(count($g) + 1),
            ]);
        }
        $g['hugo']->update(['is_active' => false]); // a deactivated guest for the User List

        // Today and the future (dates are relative, so the demo never goes stale).
        $held = $this->reservation($g['ana'], 12, ReservationStatus::SlotHeld, BookingType::Pool, 20, 'Birthday', hold: now()->addHours(20));
        $near = $this->reservation($g['ben'], 14, ReservationStatus::SlotHeld, BookingType::Transient, 6, 'Family weekend', hold: now()->addHours(1)->addMinutes(30));
        $verifying = $this->reservation($g['carla'], 9, ReservationStatus::VerifyingPayment, BookingType::Room, 10, 'Reunion', proof: ProofStatus::ForReview);
        $action = $this->reservation($g['dante'], 11, ReservationStatus::ActionNeeded, BookingType::Pool, 15, 'Team building', proof: ProofStatus::Flagged);
        $confirmed = $this->reservation($g['elena'], 20, ReservationStatus::Confirmed, BookingType::VenueHall, 60, 'Debut', proof: ProofStatus::Accepted);
        $cancelReq = $this->reservation($g['felix'], 30, ReservationStatus::Confirmed, BookingType::Room, 12, 'Anniversary', proof: ProofStatus::Accepted);
        $cancelReq->update(['change_request' => ChangeRequest::Cancel, 'change_requested_at' => now()->subHours(3)]);
        $moveReq = $this->reservation($g['gina'], 25, ReservationStatus::Confirmed, BookingType::Transient, 7, 'Christening', proof: ProofStatus::Accepted);
        $moveReq->update(['change_request' => ChangeRequest::Reschedule, 'requested_date' => today()->addDays(40), 'change_requested_at' => now()->subHours(5)]);
        $cancelled = $this->reservation($g['ana'], 18, ReservationStatus::Cancelled, BookingType::Pool, 8, 'Cancelled outing');
        $expired = $this->reservation($g['ben'], -3, ReservationStatus::Expired, BookingType::Pool, 10, 'Missed deadline');

        $walkIn = Reservation::create([
            'user_id' => null,
            'is_walk_in' => true,
            'guest_name' => 'Demo Walk-in Guest',
            'guest_contact' => '0900 000 0099',
            'reservation_date' => today()->addDays(2),
            'duration' => Duration::HalfDay,
            'booking_type' => BookingType::Pool,
            'pax' => 9,
            'occasion' => 'Walk-in swim',
            'status' => ReservationStatus::Confirmed,
            'blocking_date' => today()->addDays(2),
        ]);

        // A few past months so Reports is not empty (Confirmed reservations by reserved date).
        $past = [-10 => 'pool', -24 => 'transient', -38 => 'room', -52 => 'pool', -75 => 'venue_hall', -96 => 'room', -118 => 'pool', -140 => 'transient', -161 => 'pool'];
        $owners = array_values(array_filter($g, fn ($u) => $u->is_active));
        $i = 0;
        foreach ($past as $offset => $type) {
            $owner = $owners[$i++ % count($owners)];
            $this->reservation($owner, $offset, ReservationStatus::Confirmed, BookingType::from($type), 8, 'Past event', proof: ProofStatus::Accepted);
        }
        Reservation::create([
            'user_id' => null, 'is_walk_in' => true, 'guest_name' => 'Demo Walk-in Guest', 'guest_contact' => '0900 000 0099',
            'reservation_date' => today()->subDays(31), 'duration' => Duration::WholeDay, 'booking_type' => BookingType::Pool,
            'pax' => 12, 'occasion' => 'Walk-in swim', 'status' => ReservationStatus::Confirmed, 'blocking_date' => today()->subDays(31),
        ]);

        // Activity log and notifications for the current ones (a short, believable trail).
        foreach ([$held, $near, $verifying, $action, $confirmed, $cancelReq, $moveReq] as $r) {
            ActivityLogger::reservation('submitted', $r, ActorType::Guest, 'Reservation submitted for '.$r->reservation_date->format('M j, Y'), $r->user);
        }
        foreach ([$verifying, $action, $confirmed, $cancelReq, $moveReq] as $r) {
            ActivityLogger::reservation('proof_uploaded', $r, ActorType::Guest, 'Payment proof uploaded', $r->user);
        }
        ActivityLogger::reservation('flagged', $action, ActorType::Admin, 'Admin flagged the payment proof', $admin);
        foreach ([$confirmed, $cancelReq, $moveReq] as $r) {
            ActivityLogger::reservation('confirmed', $r, ActorType::Admin, 'Admin confirmed the payment', $admin);
        }
        ActivityLogger::reservation('cancel_requested', $cancelReq, ActorType::Guest, 'Guest asked to cancel', $cancelReq->user);
        ActivityLogger::reservation('reschedule_requested', $moveReq, ActorType::Guest, 'Guest asked to move the reservation', $moveReq->user);
        ActivityLogger::reservation('cancelled', $cancelled, ActorType::Guest, 'Guest cancelled a Slot Held reservation', $cancelled->user);
        ActivityLogger::reservation('expired', $expired, ActorType::System, 'The 24-hour hold ended with no payment proof');
        ActivityLogger::reservation('walk_in_entered', $walkIn, ActorType::Admin, 'Admin entered a walk-in', $admin);

        Notifier::admin($verifying, 'proof_uploaded', $g['carla']->name.' sent a payment proof for '.$verifying->reservation_date->format('M j, Y').'.');
        Notifier::admin($near, 'hold_near_expiry', 'The hold for '.$near->reservation_date->format('M j, Y').' ('.$g['ben']->name.') ends soon and no proof has been sent.');
        Notifier::admin($cancelReq, 'cancel_requested', $g['felix']->name.' asked to cancel the reservation for '.$cancelReq->reservation_date->format('M j, Y').'.');
        Notifier::admin($moveReq, 'reschedule_requested', $g['gina']->name.' asked to move the reservation from '.$moveReq->reservation_date->format('M j, Y').' to '.today()->addDays(40)->format('M j, Y').'.');
        Notifier::guest($near, 'hold_reminder', 'Your hold for '.$near->reservation_date->format('M j, Y').' expires in about 2 hours. Please upload your payment proof.');
        Notifier::guest($action, 'action_needed', 'Your payment proof for '.$action->reservation_date->format('M j, Y').' needs a clearer screenshot. Please upload it again.');
        Notifier::guest($confirmed, 'confirmed', 'Your reservation for '.$confirmed->reservation_date->format('M j, Y').' is Confirmed.');
        Notifier::guest($expired, 'expired', 'Your hold expired because no payment proof was sent. The date is open again.');
    }

    private function reservation(
        User $guest,
        int $daysFromToday,
        ReservationStatus $status,
        BookingType $type,
        int $pax,
        string $occasion,
        ?\Illuminate\Support\Carbon $hold = null,
        ?ProofStatus $proof = null,
    ): Reservation {
        $date = today()->addDays($daysFromToday);

        $reservation = Reservation::create([
            'user_id' => $guest->id,
            'is_walk_in' => false,
            'reservation_date' => $date,
            'duration' => $daysFromToday % 2 === 0 ? Duration::WholeDay : Duration::HalfDay,
            'booking_type' => $type,
            'pax' => $pax,
            'occasion' => $occasion,
            'status' => $status,
            'hold_expires_at' => $status === ReservationStatus::SlotHeld ? $hold : null,
            'blocking_date' => $status->blocksDate() ? $date : null,
        ]);

        if ($proof) {
            $this->proof($reservation, $proof);
        }

        return $reservation;
    }

    /** A harmless placeholder picture on the private disk, so the Admin proof screen has something to show. */
    private function proof(Reservation $reservation, ProofStatus $status): void
    {
        $disk = Storage::disk(config('zk.proof_disk'));
        $path = 'payment-proofs/demo-'.$reservation->id.'.png';

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        if (function_exists('imagecreatetruecolor')) {
            $img = imagecreatetruecolor(420, 240);
            imagefill($img, 0, 0, imagecolorallocate($img, 235, 240, 245));
            imagestring($img, 5, 20, 100, 'DEMO PROOF - not a real payment', imagecolorallocate($img, 60, 70, 90));
            ob_start();
            imagepng($img);
            $png = ob_get_clean();
        }
        $disk->put($path, $png);

        PaymentProof::create([
            'reservation_id' => $reservation->id,
            'path' => $path,
            'status' => $status,
            'reviewed_at' => $status === ProofStatus::ForReview ? null : now(),
        ]);
    }
}
