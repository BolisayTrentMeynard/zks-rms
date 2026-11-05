<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentProof;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    /** Serves a proof image from the private disk. Admin only (route middleware); never a public URL. */
    public function show(PaymentProof $proof): StreamedResponse
    {
        $disk = Storage::disk(config('zk.proof_disk'));

        abort_unless($disk->exists($proof->path), 404);

        return $disk->response($proof->path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
