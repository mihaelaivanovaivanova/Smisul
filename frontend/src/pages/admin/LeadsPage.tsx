import { useState } from 'react';
import { deleteFunnelLead, downloadFunnelLeadsCsv, fetchFunnelLeads } from '../../api/admin/funnelLeads';
import { getErrorMessage } from '../../api/errors';
import type { FunnelLead } from '../../types/admin';
import { useAsync } from '../../hooks/useAsync';
import LoadingState from '../../components/LoadingState';
import ErrorState from '../../components/ErrorState';
import EmptyState from '../../components/EmptyState';
import Pagination from '../../components/listing/Pagination';
import ConfirmModal from '../../components/admin/ConfirmModal';

/**
 * Leads captured by the funnel landing page's email opt-in block. Read
 * plus two actions: CSV export (for the mailing tool that actually sends
 * campaigns) and delete (GDPR erasure requests).
 */
export default function LeadsPage() {
  const [page, setPage] = useState(1);
  const [refreshKey, setRefreshKey] = useState(0);
  const [pendingDelete, setPendingDelete] = useState<FunnelLead | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const [isExporting, setIsExporting] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);

  const { data, isLoading, error } = useAsync(
    () => fetchFunnelLeads(page),
    [page, refreshKey],
    'Could not load leads.',
  );

  async function handleExport() {
    setIsExporting(true);
    setActionError(null);

    try {
      await downloadFunnelLeadsCsv();
    } catch (err) {
      setActionError(getErrorMessage(err, 'Could not export leads.'));
    } finally {
      setIsExporting(false);
    }
  }

  async function handleDelete() {
    if (!pendingDelete) {
      return;
    }

    setIsDeleting(true);
    setActionError(null);

    try {
      await deleteFunnelLead(pendingDelete.id);
      setPendingDelete(null);
      setRefreshKey((key) => key + 1);
    } catch (err) {
      setActionError(getErrorMessage(err, 'Could not delete this lead.'));
    } finally {
      setIsDeleting(false);
    }
  }

  return (
    <div>
      <div className="d-flex align-items-center justify-content-between mb-4">
        <h1 className="h3 mb-0">Leads</h1>
        <button
          type="button"
          className="btn btn-outline-secondary"
          onClick={() => void handleExport()}
          disabled={isExporting || !data || data.data.length === 0}
        >
          {isExporting && <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" />}
          Export CSV
        </button>
      </div>

      {actionError && <div className="alert alert-danger">{actionError}</div>}

      {isLoading && <LoadingState message="Loading leads..." />}
      {!isLoading && error && <ErrorState message={error} />}
      {!isLoading && !error && data && data.data.length === 0 && (
        <EmptyState title="No leads yet" message="Emails captured by the funnel landing page will appear here." />
      )}

      {!isLoading && !error && data && data.data.length > 0 && (
        <>
          <div className="table-responsive">
            <table className="table align-middle">
              <thead>
                <tr>
                  <th>Email</th>
                  <th>Subscribed</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {data.data.map((lead) => (
                  <tr key={lead.id}>
                    <td>{lead.email}</td>
                    <td>{new Date(lead.created_at).toLocaleString('bg-BG')}</td>
                    <td className="text-end">
                      <button
                        type="button"
                        className="btn btn-outline-danger btn-sm"
                        onClick={() => setPendingDelete(lead)}
                      >
                        Delete
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination meta={data.meta} onPageChange={setPage} />
        </>
      )}

      <ConfirmModal
        show={pendingDelete !== null}
        title="Delete lead"
        message={`Remove ${pendingDelete?.email ?? ''} from the list? This cannot be undone.`}
        isLoading={isDeleting}
        onConfirm={() => void handleDelete()}
        onCancel={() => setPendingDelete(null)}
      />
    </div>
  );
}
