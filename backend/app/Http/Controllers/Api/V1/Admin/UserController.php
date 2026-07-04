<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTransferObjects\Admin\CustomerFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerIndexRequest;
use App\Http\Resources\Admin\CustomerResource;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(private readonly CustomerService $customers) {}

    public function index(CustomerIndexRequest $request): AnonymousResourceCollection
    {
        $customers = $this->customers->list(CustomerFilterData::fromArray($request->validated()));

        return CustomerResource::collection($customers);
    }

    /**
     * Order history is fetched separately by the frontend via
     * GET /admin/orders?user_id={id} (see OrderFilterData::$userId) rather
     * than embedded here — reuses the existing admin orders list/pagination
     * instead of a second, parallel implementation.
     */
    public function show(User $user): CustomerResource
    {
        $this->authorize('view', $user);

        return new CustomerResource($user->loadCount('orders'));
    }
}
