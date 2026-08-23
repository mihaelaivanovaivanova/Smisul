<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\DataTransferObjects\Admin\ComplaintFilterData;
use App\Enums\ComplaintStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ComplaintIndexRequest;
use App\Http\Requests\Admin\StoreComplaintRequest;
use App\Http\Requests\Admin\UpdateComplaintRequest;
use App\Http\Resources\Admin\ComplaintResource;
use App\Models\Complaint;
use App\Models\Order;
use App\Services\AdminActionLogger;
use App\Services\ComplaintService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The "регистър на предявените рекламации" ЗЗП чл. 128, ал. 4 requires -
 * distinct from the general contact form, which just emails a message and
 * persists nothing. No destroy() by design: a register entry, once
 * logged, isn't meant to be deletable (same reasoning as LegalDocument
 * never being edited/removed once published).
 */
class ComplaintController extends Controller
{
    public function __construct(
        private readonly ComplaintService $complaints,
        private readonly AdminActionLogger $actionLogger,
    ) {}

    public function index(ComplaintIndexRequest $request): AnonymousResourceCollection
    {
        return ComplaintResource::collection(
            $this->complaints->listForAdmin(ComplaintFilterData::fromArray($request->validated())),
        );
    }

    public function store(StoreComplaintRequest $request): JsonResponse
    {
        $order = Order::where('order_number', $request->validated('order_number'))->firstOrFail();
        $submittedAt = $request->validated('submitted_at');

        $complaint = $this->complaints->create(
            $order,
            $request->validated('description'),
            $submittedAt !== null ? CarbonImmutable::parse($submittedAt) : null,
        );

        $this->actionLogger->log($request->user(), 'complaint.logged', $complaint);

        return (new ComplaintResource($complaint->load('order')))->response()->setStatusCode(201);
    }

    public function show(Complaint $complaint): ComplaintResource
    {
        return new ComplaintResource($complaint->load('order'));
    }

    public function update(UpdateComplaintRequest $request, Complaint $complaint): ComplaintResource
    {
        $updated = $this->complaints->updateStatus(
            $complaint,
            ComplaintStatus::from($request->validated('status')),
            $request->validated('resolution'),
        );

        $this->actionLogger->log($request->user(), 'complaint.updated', $updated, ['status' => $updated->status->value]);

        return new ComplaintResource($updated->load('order'));
    }
}
