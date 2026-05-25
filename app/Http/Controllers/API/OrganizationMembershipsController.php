<?php

namespace App\Http\Controllers\API;

use App\Http\Concerns\ResolvesPerPage;
use App\Http\Controllers\Controller;
use App\Models\Management\Organization;
use Illuminate\Http\Request;

class OrganizationMembershipsController extends Controller
{
    use ResolvesPerPage;

    public function index(Organization $organization, Request $request)
    {
        return $organization
            ->users()
            ->withPivot('role')
            ->paginate($this->perPage($request));
    }
}
