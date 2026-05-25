<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CreateSanctumTokenController extends Controller
{
    public function __invoke(Request $request): string
    {
        return $request->user()
            ->createToken($request->userAgent() ?? 'unknown', ['read:paywalls'])
            ->plainTextToken;
    }
}
