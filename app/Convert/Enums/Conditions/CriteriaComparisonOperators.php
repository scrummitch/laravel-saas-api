<?php

namespace App\Convert\Enums\Conditions;

enum CriteriaComparisonOperators: string
{
    case GreaterThan = 'gt';
    case LessThan = 'lt';
    case Equal = 'eq';
    case In = 'in';
    case NotEqual = 'ne';
    case NotIn = 'nin';
}
