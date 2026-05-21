<?php

namespace App\Models\Values;

enum ProductStatus: string
{
    case draft = 'draft';
    case active = 'active';
    case archived = 'archived';
    case deleted = 'deleted';
}
