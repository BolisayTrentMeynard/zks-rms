<?php

namespace App\Http\Controllers;

use App\Support\Faq;
use Illuminate\Http\JsonResponse;

class FaqController extends Controller
{
    /** The FAQ for the chat panel. Public, the same for everyone, and holds no guest data. */
    public function data(): JsonResponse
    {
        return response()->json(Faq::chatData());
    }
}
