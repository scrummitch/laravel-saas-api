<?php

namespace App\Intel\Enums;

enum ScenarioState: int
{
    case Draft = 0;
    case Staged = 1;
    case Active = 2;
    case Archived = 3;
}
