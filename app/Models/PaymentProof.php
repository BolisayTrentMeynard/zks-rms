<?php

namespace App\Models;

use App\Enums\ProofStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentProof extends Model
{
    protected $fillable = ['reservation_id', 'path', 'status', 'reviewed_at'];

    protected function casts(): array
    {
        return [
            'status' => ProofStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
