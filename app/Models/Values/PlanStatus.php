<?php

namespace App\Models\Values;

enum PlanStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
