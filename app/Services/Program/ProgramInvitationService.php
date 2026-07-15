<?php

namespace App\Services\Program;

use App\Enums\ProgramMemberRole;
use App\Models\Program;
use App\Models\ProgramInvitation;
use App\Models\ProgramMember;
use App\Models\User;
use Illuminate\Support\Str;

class ProgramInvitationService
{
    public function createLink(Program $program, ?string $email = null, int $daysValid = 7): ProgramInvitation
    {
        return ProgramInvitation::create([
            'program_id' => $program->id,
            'email' => $email,
            'token' => Str::random(48),
            'expires_at' => now()->addDays($daysValid),
        ]);
    }

    public function accept(User $user, string $token): Program
    {
        $invitation = ProgramInvitation::query()
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->firstOrFail();

        $program = $invitation->program;
        $count = $program->members()->count();
        abort_if($count >= config('futurmeal.max_program_members'), 422, 'Programme complet.');

        ProgramMember::firstOrCreate(
            ['program_id' => $program->id, 'user_id' => $user->id],
            ['role' => ProgramMemberRole::Member, 'share_metrics' => false]
        );

        $invitation->update(['accepted_at' => now()]);

        return $program;
    }

    public function joinUrl(ProgramInvitation $invitation): string
    {
        return route('programs.join', ['token' => $invitation->token]);
    }
}
