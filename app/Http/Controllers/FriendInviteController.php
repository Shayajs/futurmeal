<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Social\FriendshipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class FriendInviteController extends Controller
{
    public function __invoke(string $code, FriendshipService $friendships): RedirectResponse
    {
        $target = User::where('friend_code', strtoupper($code))->first();

        if (! $target || $target->id === Auth::id()) {
            return redirect()->route('friends')->with('friends-status', 'Code ami invalide.');
        }

        $result = $friendships->sendRequest(Auth::user(), $target);

        return redirect()->route('friends')->with('friends-status', $result
            ? "Demande d'ami envoyée à {$target->name}."
            : "Vous êtes déjà liés à {$target->name}.");
    }
}
