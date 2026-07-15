<?php

namespace App\Enums;

enum ProgramMemberRole: string
{
    case Owner = 'owner';
    case Member = 'member';
}
