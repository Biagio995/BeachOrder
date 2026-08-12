<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    public function __construct(private LoyaltyService $loyalty) {}

    public function balance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_session' => ['required', 'uuid'],
        ]);

        $account = $this->loyalty->balanceForSession($data['customer_session']);

        return response()->json([
            'customer_session' => $data['customer_session'],
            'points' => $account?->points ?? 0,
            'customer_name' => $account?->customer_name,
        ]);
    }
}
