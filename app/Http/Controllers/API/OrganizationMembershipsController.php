<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Management\Organization;

class OrganizationMembershipsController extends Controller
{
    public function index(Organization $organization)
    {
        return $organization
            ->users()
            ->withPivot('role')
            ->paginate(9999);
    }
}
