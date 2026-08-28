<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\FunnelLead;
use App\Services\AdminActionLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin view over the funnel landing page's captured leads — list, delete
 * (GDPR erasure requests), and a CSV export for handing the list to
 * whatever mailing tool actually sends the campaigns.
 */
class FunnelLeadController extends Controller
{
    public function __construct(private readonly AdminActionLogger $actionLogger) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FunnelLead::class);

        // Substring match, not exact — an admin acting on an "Отпиши ме"
        // reply usually has the whole address to hand anyway, but partial
        // typing (or checking "did we already have anyone @thatdomain")
        // should still work. No dedicated search index needed at this
        // table's size (a marketing lead list, not customer accounts).
        $email = trim((string) $request->query('email', ''));

        // Newest first; id rather than created_at so same-second signups
        // still order deterministically.
        $leads = FunnelLead::query()
            ->when($email !== '', fn ($query) => $query->where('email', 'like', '%'.$email.'%'))
            ->orderByDesc('id')
            ->paginate(25);

        return response()->json([
            'data' => $leads->items(),
            'meta' => [
                'current_page' => $leads->currentPage(),
                'last_page' => $leads->lastPage(),
                'per_page' => $leads->perPage(),
                'total' => $leads->total(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', FunnelLead::class);

        $this->actionLogger->log($request->user(), 'funnel.leads.exported');

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'created_at']);

            FunnelLead::query()->orderBy('created_at')->chunk(500, function ($leads) use ($out): void {
                foreach ($leads as $lead) {
                    fputcsv($out, [$lead->email, $lead->created_at?->toIso8601String()]);
                }
            });

            fclose($out);
        }, 'funnel-leads.csv', ['Content-Type' => 'text/csv']);
    }

    public function destroy(Request $request, FunnelLead $lead): Response
    {
        $this->authorize('delete', $lead);

        $lead->delete();

        $this->actionLogger->log($request->user(), 'funnel.leads.deleted', changes: ['email' => $lead->email]);

        return response()->noContent();
    }
}
