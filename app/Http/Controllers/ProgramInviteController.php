<?php

namespace App\Http\Controllers;

use App\Services\LogSnag\LogSnagClient;
use App\Services\Program\ProgramInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ProgramInviteController extends Controller
{
    public function __invoke(string $token, ProgramInvitationService $invitations, LogSnagClient $logSnag): RedirectResponse
    {
        $program = $invitations->accept(Auth::user(), $token);

        $logSnag->log('programs', 'Membre rejoint', Auth::user()->name.' → '.$program->name.' (lien)', '👥', true);

        return redirect()
            ->route('programs')
            ->with('program-status', "Tu as rejoint le programme « {$program->name} ».");
    }
}
