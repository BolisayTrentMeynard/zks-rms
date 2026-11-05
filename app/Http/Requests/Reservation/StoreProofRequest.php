<?php

namespace App\Http\Requests\Reservation;

use Illuminate\Foundation\Http\FormRequest;

class StoreProofRequest extends FormRequest
{
    /** Only the guest who owns the reservation can send its proof. */
    public function authorize(): bool
    {
        $reservation = $this->route('reservation');

        return $this->user()?->isGuest() && $reservation->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        return [
            'proof' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('zk.proof_max_kb')],
        ];
    }

    public function messages(): array
    {
        $mb = round(config('zk.proof_max_kb') / 1024);

        return [
            'proof.required' => 'Choose a screenshot of your GCash payment.',
            'proof.image' => 'The proof must be a picture (JPG, PNG or WebP).',
            'proof.mimes' => 'The proof must be a JPG, PNG or WebP picture.',
            'proof.max' => "The picture is too large. Please keep it under {$mb} MB.",
            'proof.uploaded' => 'The picture could not be uploaded. Please try a smaller one.',
        ];
    }
}
