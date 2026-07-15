<?php

namespace App\Livewire;

use App\Enums\ProgramMemberRole;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Services\LogSnag\LogSnagClient;
use App\Services\Program\ProgramInvitationService;
use App\Services\Program\ProgramPlanService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProgramManager extends Component
{
    public string $name = '';

    public string $description = '';

    public string $invite_code = '';

    public function createProgram(LogSnagClient $logSnag, ProgramPlanService $programPlan): void
    {
        $this->validate(['name' => 'required|string|max:255']);

        $program = Program::create([
            'owner_id' => Auth::id(),
            'name' => $this->name,
            'description' => $this->description,
            'week_starts_on' => now()->startOfWeek(),
        ]);

        ProgramMember::create([
            'program_id' => $program->id,
            'user_id' => Auth::id(),
            'role' => ProgramMemberRole::Owner,
            'share_metrics' => true,
        ]);

        $programPlan->ensureProgramPlan($program, Auth::user());

        $logSnag->log('programs', 'Programme créé', $program->name, '📋');

        $this->reset(['name', 'description']);
        $this->dispatch('program-created');
    }

    public function joinProgram(LogSnagClient $logSnag): void
    {
        $this->validate(['invite_code' => 'required|string']);

        $program = Program::where('invite_code', strtoupper(trim($this->invite_code)))->firstOrFail();

        $count = $program->members()->count();
        abort_if($count >= config('futurmeal.max_program_members'), 422, 'Programme complet.');

        ProgramMember::firstOrCreate(
            ['program_id' => $program->id, 'user_id' => Auth::id()],
            ['role' => ProgramMemberRole::Member, 'share_metrics' => false]
        );

        $logSnag->log('programs', 'Membre rejoint', Auth::user()->name.' → '.$program->name, '👥', true);

        $this->invite_code = '';
        $this->dispatch('program-joined');
    }

    public function toggleShareMetrics(int $programId): void
    {
        $member = ProgramMember::where('program_id', $programId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $member->update(['share_metrics' => ! $member->share_metrics]);
    }

    public function toggleLockPortions(int $programId): void
    {
        $program = Program::findOrFail($programId);
        abort_unless($program->owner_id === Auth::id(), 403);

        $program->update(['lock_portions' => ! $program->lock_portions]);
    }

    public function generateInviteLink(int $programId, ProgramInvitationService $invitations): void
    {
        $program = Program::findOrFail($programId);
        abort_unless($program->owner_id === Auth::id(), 403);

        $invitation = $invitations->createLink($program);
        session()->flash('program-invite-link', $invitations->joinUrl($invitation));
    }

    public function render(ProgramPlanService $programPlan)
    {
        $user = Auth::user();
        $memberships = $user->programMemberships()->with(['program.owner', 'program.members.user'])->get();

        $enriched = $memberships->map(function ($membership) use ($user, $programPlan) {
            $program = $membership->program;
            $isOwner = $membership->role->value === 'owner';

            return [
                'membership' => $membership,
                'program' => $program,
                'adherence' => $programPlan->adherenceRate($user, $program),
                'shared_metrics' => $programPlan->sharedMemberMetrics($program),
                'member_adherence' => $isOwner ? $programPlan->memberAdherenceRates($program) : collect(),
                'member_count' => $program->members->count(),
                'max_members' => config('futurmeal.max_program_members'),
            ];
        });

        return view('livewire.program-manager', [
            'memberships' => $enriched,
        ]);
    }
}
