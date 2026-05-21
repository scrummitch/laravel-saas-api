<?php

namespace App\Http\Controllers\API\Actions;

use App\Http\Controllers\Controller;
use App\Models\Management\Operation;

class CreateOperationAttemptController extends Controller
{
    public function __invoke(Operation $operation)
    {
        $operation->retry();

        return $operation;
    }
}
