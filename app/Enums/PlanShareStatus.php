<?php

namespace App\Enums;

enum PlanShareStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Revoked = 'revoked';
}
