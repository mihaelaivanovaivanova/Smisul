<?php

namespace App\Services;

use App\DataTransferObjects\Admin\ComplaintFilterData;
use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ComplaintService
{
    public function __construct(private readonly ComplaintNumberGenerator $numbers) {}

    public function create(Order $order, string $description, ?CarbonInterface $submittedAt = null): Complaint
    {
        return Complaint::create([
            'order_id' => $order->id,
            'complaint_number' => $this->numbers->next(),
            'description' => $description,
            'status' => ComplaintStatus::Received,
            'submitted_at' => $submittedAt ?? now(),
        ]);
    }

    public function listForAdmin(ComplaintFilterData $filters): LengthAwarePaginator
    {
        $query = Complaint::query()->with('order');

        if ($filters->search !== null && $filters->search !== '') {
            $query->where('complaint_number', 'like', "%{$filters->search}%");
        }

        if ($filters->status !== null) {
            $query->where('status', $filters->status);
        }

        match ($filters->sort) {
            'submitted_asc' => $query->orderBy('submitted_at'),
            'number_desc' => $query->orderByDesc('complaint_number'),
            'number_asc' => $query->orderBy('complaint_number'),
            'status_asc' => $query->orderBy('status'),
            'status_desc' => $query->orderByDesc('status'),
            default => $query->orderByDesc('submitted_at'), // 'submitted_desc'
        };

        return $query->paginate(20, page: $filters->page);
    }

    /**
     * resolved_at is stamped the first time the complaint reaches a
     * terminal status, and never overwritten on a later edit (e.g.
     * correcting the resolution text after the fact shouldn't shift the
     * date the register shows it was actually resolved).
     */
    public function updateStatus(Complaint $complaint, ComplaintStatus $status, ?string $resolution): Complaint
    {
        $complaint->status = $status;

        if ($resolution !== null) {
            $complaint->resolution = $resolution;
        }

        if ($status->isTerminal() && $complaint->resolved_at === null) {
            $complaint->resolved_at = now();
        }

        $complaint->save();

        return $complaint;
    }
}
