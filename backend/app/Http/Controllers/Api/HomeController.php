<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BalanceCarryForward;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * 首页本金（昨日结余总和，人民币）
     */
    public function principal(Request $request)
    {
        $yesterday = now()->subDay()->toDateString();
        $principal = (float) BalanceCarryForward::whereDate('date', $yesterday)->sum('balance_cny');
        return response()->json([
            'success' => true,
            'data' => [
                'date' => $yesterday,
                'principal_cny' => round($principal, 2),
            ]
        ]);
    }
}


