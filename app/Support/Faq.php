<?php

namespace App\Support;

use App\Enums\BookingType;

/**
 * The single source of the resort's FAQ. The /faq page and the chat both read from here.
 * Numbers come from config/zk.php so an answer can never disagree with what the system enforces.
 * Nothing here is invented: rates, the GCash number and contact details stay "ask the resort" until configured.
 */
class Faq
{
    /** What to tell a guest when the question needs the resort itself. */
    public static function contact(): string
    {
        $contact = trim((string) config('zk.contact'));

        return $contact !== '' ? 'Please contact the resort: '.$contact : 'Please contact the resort directly.';
    }

    /** @return string  e.g. "tomorrow" */
    private static function earliest(): string
    {
        $days = (int) config('zk.min_advance_days');

        return match (true) {
            $days <= 0 => 'today',
            $days === 1 => 'tomorrow',
            default => $days.' days from today',
        };
    }

    private static function cap(BookingType $type): string
    {
        $cap = $type->paxCap();

        return match (true) {
            $cap !== null => 'up to '.$cap.' guests',
            $type === BookingType::VenueHall => 'no guest limit has been set yet, so please ask the resort',
            default => 'no guest limit',
        };
    }

    /**
     * Higher priority wins when two topics match equally well (the general "how to book" is lowest).
     *
     * @return list<array{id: string, title: string, keywords: list<string>, priority: int, answer: list<string>}>
     */
    public static function topics(): array
    {
        $hold = (int) config('zk.hold_hours');
        $remind = (int) config('zk.hold_reminder_hours');
        $notice = (int) config('zk.notice_days');
        $proofMb = (int) round((int) config('zk.proof_max_kb') / 1024);
        $gcash = trim((string) config('zk.gcash'));

        return [
            [
                'id' => 'how-to-book',
                'title' => 'How do I book?',
                'priority' => 1,
                'keywords' => ['book', 'reserve', 'magbook', 'mag-book', 'magpareserve', 'paano mag', 'sign up', 'register', 'account', 'log in', 'login'],
                'answer' => [
                    '1. Create an account or log in. You need to be logged in to submit a reservation.',
                    '2. Open Availability and pick an open date. Online booking starts '.self::earliest().'. For a same-day booking, please contact the resort.',
                    '3. Choose Whole Day or Half Day, your Booking Type, the number of guests and the occasion, then submit.',
                    '4. The date is held for you for '.$hold.' hours. Send your GCash payment screenshot before the time runs out.',
                    '5. The resort checks your payment. When it is accepted, your reservation becomes Confirmed.',
                ],
            ],
            [
                'id' => 'booking-types',
                'title' => 'Booking types and guest limits',
                'priority' => 3,
                'keywords' => ['pool', 'transient', 'room', 'venue', 'hall', 'pax', 'guests', 'capacity', 'limit', 'how many', 'maximum', 'max', 'whole day', 'half day', 'duration', 'ilan'],
                'answer' => [
                    'You choose one Booking Type per reservation:',
                    '• Pool: '.self::cap(BookingType::Pool).'.',
                    '• Transient: '.self::cap(BookingType::Transient).'.',
                    '• Room (Living Area/Sala and Private CR): '.self::cap(BookingType::Room).'.',
                    '• Venue Hall: '.self::cap(BookingType::VenueHall).'.',
                    'You also choose Whole Day or Half Day. There are no fixed time blocks, and a Half Day still takes the whole date.',
                ],
            ],
            [
                'id' => 'whole-resort',
                'title' => 'Can two groups book the same date?',
                'priority' => 4,
                'keywords' => ['same day', 'same date', 'other group', 'another group', 'two groups', 'share', 'shared', 'exclusive', 'private', 'whole resort', 'one group', 'isang grupo', 'other guests'],
                'answer' => [
                    'No. The resort takes one group per date, and that group has the whole resort.',
                    'A reserved date is blocked for every Booking Type, including a Half Day booking. On the Availability page a date shows as Reserved, and it may open again if a hold expires.',
                ],
            ],
            [
                'id' => 'gcash',
                'title' => 'How do I pay by GCash?',
                'priority' => 5,
                'keywords' => ['gcash', 'g cash', 'pay', 'payment', 'downpayment', 'down payment', 'proof', 'screenshot', 'upload', 'bayad', 'bayaran'],
                'answer' => [
                    '1. Send your downpayment by GCash'.($gcash !== '' ? ' to '.$gcash.'.' : ' to the resort\'s GCash account. If you do not have the details yet, please ask the resort.'),
                    '2. Open My Reservations, choose your reservation and upload a clear screenshot of the payment (JPG, PNG or WebP, up to '.$proofMb.' MB).',
                    '3. Your reservation becomes Verifying Payment while the resort checks it, then Confirmed.',
                    'If the screenshot is not clear, the status changes to Action Needed. Your date stays held and you upload a new screenshot.',
                    'For how much to pay, '.lcfirst(self::contact()),
                ],
            ],
            [
                'id' => 'hold',
                'title' => 'What is the 24-hour hold?',
                'priority' => 5,
                'keywords' => ['hold', 'held', 'expire', 'expired', 'expiry', 'slot held', 'time left', 'countdown', 'reminder', 'hours', '24'],
                'answer' => [
                    'When you submit a reservation, the date is held for you for '.$hold.' hours (the status is Slot Held) and nobody else can book it. Your reservation page shows the time left.',
                    'You get a notification about '.$remind.' hours before it ends. If no payment screenshot arrives in time, the status becomes Expired and the date opens up again.',
                    'Once you upload your screenshot, the date stays held while the resort checks it. While a hold is still running you can cancel it yourself from My Reservations.',
                ],
            ],
            [
                'id' => 'change-cancel',
                'title' => 'Can I reschedule or cancel?',
                'priority' => 6,
                'keywords' => ['cancel', 'cancellation', 'reschedule', 'resched', 'change date', 'move my', 'refund', 'forfeit', 'non refundable', 'kanselahin', 'ilipat', 'palitan'],
                'answer' => [
                    'Open My Reservations and send a request. The resort reviews it and tells you the result by notification.',
                    'It must be at least '.$notice.' days before your reserved date. Closer than that, the request is blocked, so please contact the resort. A cancellation may forfeit the downpayment.',
                    'A reschedule is for a Confirmed reservation and needs the new date to be free. Your old date is released only when the resort approves.',
                    'A reservation that is still Slot Held can be cancelled right away from My Reservations.',
                ],
            ],
            [
                'id' => 'rates',
                'title' => 'How much is it?',
                'priority' => 6,
                'keywords' => ['price', 'prices', 'rate', 'rates', 'how much', 'magkano', 'package', 'packages', 'fee', 'cost', 'discount', 'promo', 'inclusion'],
                'answer' => [
                    'Rates and packages are not listed in this system. '.self::contact(),
                ],
            ],
        ];
    }

    /** What the chat page script needs, in one place. */
    public static function chatData(): array
    {
        return [
            'topics' => self::topics(),
            'intro' => 'Hi! Pick a topic or type a question about booking at ZKs Private Resort.',
            'fallback' => 'I am not sure about that one. Pick a topic below, or '.lcfirst(self::contact()),
        ];
    }
}
