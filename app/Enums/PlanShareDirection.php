<?php

namespace App\Enums;

enum PlanShareDirection: string
{
    case ViewerRequests = 'viewer_requests';
    case OwnerInvites = 'owner_invites';

    public function label(): string
    {
        return match ($this) {
            self::ViewerRequests => 'Demande de suivi',
            self::OwnerInvites => 'Invitation à suivre',
        };
    }
}
