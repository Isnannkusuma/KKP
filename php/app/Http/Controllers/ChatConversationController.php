<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatConversationController extends Controller
{
    /** Hapus satu percakapan beserta seluruh pesannya (milik user sendiri). */
    public function destroy(Request $request, string $conversation): JsonResponse
    {
        $deleted = ChatConversation::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($conversation)
            ->delete();

        abort_unless($deleted, 404);

        return response()->json(['deleted' => true]);
    }

    /** Hapus seluruh riwayat percakapan milik user yang login. */
    public function destroyAll(Request $request): JsonResponse
    {
        ChatConversation::query()->where('user_id', $request->user()->id)->delete();

        return response()->json(['deleted' => true]);
    }
}
