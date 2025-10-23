<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function index(Request $request)
    {
        $query = Channel::query();

        // 只返回启用的渠道
        if ($request->get('active_only', true)) {
            $query->active();
        }

        $channels = $query->orderBy('name')->get();

        return response()->json($channels);
    }

    public function show(Channel $channel)
    {
        return response()->json($channel);
    }
}
