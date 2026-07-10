<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StoredPaymentMethod;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StoredPaymentMethodController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->payments->storedMethodsFor($request->user())->map(fn (StoredPaymentMethod $method) => [
            'id' => $method->id, 'brand' => $method->brand, 'last_four' => $method->last_four,
            'expiry_month' => $method->expiry_month, 'expiry_year' => $method->expiry_year,
        ]);
        return response()->json(['data' => $data]);
    }

    public function destroy(Request $request, StoredPaymentMethod $storedPaymentMethod): Response
    {
        $this->payments->removeStoredMethod($request->user(), $storedPaymentMethod);
        return response()->noContent();
    }
}
