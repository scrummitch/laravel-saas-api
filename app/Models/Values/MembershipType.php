<?php

namespace App\Models\Values;

enum MembershipType: string
{
    case owner = 'owner';
    case admin = 'admin';
    case developer = 'developer';
    case member = 'member';
    case support = 'support';
}
