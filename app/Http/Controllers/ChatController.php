<?php

namespace App\Http\Controllers;

use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * Public. Answers one typed question with the AI, or says {"ai": false} so the page falls back to
     * its keyword answers. Nothing about the logged-in account is read or sent.
     */
    public function store(Request $request, ChatService $chat): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:'.config('zk.chat.max_chars')],
            'history' => ['sometimes', 'array', 'max:20'],
            'history.*.role' => ['required', 'string', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:1500'],
        ]);

        $history = array_map(
            fn (array $turn) => ['role' => $turn['role'], 'content' => $turn['content']],
            array_values($data['history'] ?? [])
        );

        $reply = $chat->reply(trim($data['message']), $history);

        return $reply === null
            ? response()->json(['ai' => false])
            : response()->json(['ai' => true, 'reply' => $reply]);
    }
}
