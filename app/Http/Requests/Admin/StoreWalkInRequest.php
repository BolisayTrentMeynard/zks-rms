<?php

namespace App\Http\Requests\Admin;

use App\Enums\BookingType;
use App\Enums\Duration;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWalkInRequest extends FormRequest
{
    /** Only the Admin enters walk-ins. */
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'guest_name' => ['required', 'string', 'max:100'],
            'guest_contact' => ['required', 'string', 'max:50'],
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
            'guest_name.required' => 'Enter the guest\'s name.',
            'guest_contact.required' => 'Enter a contact number for the guest.',
            'reservation_date.required' => 'Pick a date.',
            'reservation_date.date_format' => 'Pick a valid date.',
            'duration.required' => 'Choose Whole Day or Half Day.',
            'booking_type.required' => 'Choose a booking type.',
            'pax.required' => 'Enter the number of guests.',
            'pax.integer' => 'Enter the number of guests as a whole number.',
            'pax.min' => 'There must be at least 1 guest.',
            'occasion.required' => 'Enter the occasion, for example Birthday.',
        ];
    }

    /** Checks that need the rules above to have passed first: today or later, and the pax cap. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $errors = $validator->errors();

                if (! $errors->has('reservation_date')
                    && Carbon::parse($this->input('reservation_date'))->lt(today())) {
                    $errors->add('reservation_date', 'A walk-in cannot be entered for a past date.');
                }

                if (! $errors->has('booking_type') && ! $errors->has('pax')) {
                    $type = BookingType::from($this->input('booking_type'));
                    $cap = $type->paxCap();

                    if ($cap !== null && (int) $this->input('pax') > $cap) {
                        $errors->add('pax', $type->label().' is limited to '.$cap.' guests.');
                    }
                }
            },
        ];
    }
}
