<?php

namespace App\Convert\Enums\Conditions;

enum Combinator: string
{
    case And = 'and';
    case Or = 'or';
}
