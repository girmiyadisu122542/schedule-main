<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Helper\Response\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Translation\Message;

class DeviceController extends Controller {

    /**
     * Retrieve the authenticated user's active device sessions.
     *
     * @return mixed
     */
    public function index(): mixed {
        $user = Auth::user();

        $currentTokenId = $user->token()->id;
        $devices = DB::table('oauth_access_tokens')
            ->where('user_id', $user->id)
            ->where('revoked', false)
            ->orderByDesc('created_at')
            ->get();

        $devices = $devices->map(fn ($token) => [
            'id' => $token->id,
            'device' => $token->device_name ?: Message::get('unknown_device'),
            'ip' => $token->ip_address,
            'user_agent' => $token->user_agent,
            'login_time' => $token->created_at,
            'last_used' => $token->last_used_at,
            'is_active' => true,
            'is_current' => $token->id == $currentTokenId,
        ]);

        return Response::_200([
            'data' => $devices,
        ]);
    }

    /**
     * Terminate a specific session for the authenticated user.
     *
     * @param string $tokenId
     * @return mixed
     */
    public function terminateSession(string $tokenId): mixed {
        $user = Auth::user();
        if ($user->token()->id == $tokenId) {
            return Response::_422(Message::get('cannot_terminate_current_device'));
        }

        DB::table('oauth_access_tokens')
            ->where('id', $tokenId)
            ->where('user_id', $user->id)
            ->update([
                'revoked' => true,
            ]);

        return Response::_200(Message::get('session_terminated'));
    }

    /**
     * Terminate all other active sessions for the authenticated user.
     *
     * @return mixed
     */
    public function terminateAllSessions(): mixed {
        $user = Auth::user();

        $currentTokenId = $user->token()->id;
        DB::table('oauth_access_tokens')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentTokenId)
            ->update([
                'revoked' => true,
            ]);

        return Response::_200(Message::get('all_sessions_terminated'));
    }
}
