<?php

namespace App\Http\Requests\Reservation;

use App\Enums\BookingType;
use App\Enums\Duration;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReservationRequest extends FormRequest
{
    /** Only guest accounts book through this form. The Admin gets a 403. */
    public function authorize(): bool
    {
        return (bool) $this->user()?->isGuest();
    }

    public function rules(): array
    {
        return [
            'reservation_date' => ['required', 'date_format:Y-m-d'],
            'duration' => ['required', Rule::enum(Duration::class)],
            'booking_type' => ['required', Rule::enum(BookingType::class)],
            'pax' => ['required', 'integer', 'min:1', 'max:9999'],
            'occasion' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'reservation_date.required' => 'Pick a date.',
            'reservation_date.date_format' => 'Pick a valid date.',
            'duration.required' => 'Choose Whole Day or Half Day.',
            'booking_type.required' => 'Choose a booking type.',
            'pax.required' => 'Enter the number of guests.',
            'pax.integer' => 'Enter the number of guests as a whole number.',
            'pax.min' => 'There must be at least 1 guest.',
            'occasion.required' => 'Tell us the occasion, for example Birthday.',
        ];
    }

    /** Checks that need the rules above to have passed first: earliest date and pax cap. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $errors = $validator->errors();

                if (! $errors->has('reservation_date')) {
                    $earliest = ReservationService::earliestDate();

                    if (Carbon::parse($this->input('reservation_date'))->lt($earliest)) {
                        $validator->errors()->add(
                            'reservation_date',
                            'Online bookings start from '.$earliest->format('F j, Y').'.'
                        );
                    }
                }

                if (! $errors->has('booking_type') && ! $errors->has('pax')) {
                    $type = BookingType::from($this->input('booking_type'));
                    $cap = $type->paxCap();

                    if ($cap !== null && (int) $this->input('pax') > $cap) {
                        $validator->errors()->add('pax', $type->label().' is limited to '.$cap.' guests.');
                    }
                }
            },
        ];
    }
}
