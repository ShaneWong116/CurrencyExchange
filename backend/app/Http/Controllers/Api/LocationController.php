<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;

class LocationController extends Controller
{
    public function index()
    {
        $locations = Location::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'status']);

        return response()->json($locations);
    }
}


