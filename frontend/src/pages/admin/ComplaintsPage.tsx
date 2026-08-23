import { useState } from 'react';
import type { FormEvent } from 'react';
import { fetchComplaints, logComplaint, updateComplaint } from '../../api/admin/complaints';
import type { Complaint, ComplaintSort, ComplaintStatus } from '../../types/admin';
import { useAsync } from '../../hooks/useAsync';
import { getErrorMessage } from '../../api/errors';
import LoadingState from '../../components/LoadingState';
import ErrorState from '../../components/ErrorState';
import EmptyState from '../../components/EmptyState';
import Pagination from '../../components/listing/Pagination';

const STATUS_TABS: { key: ComplaintStatus | 'all'; label: string }[] = [
  { key: 'all', label: 'All' },
  { key: 'received', label: 'Received' },
  { key: 'in_review', label: 'In Review' },
  { key: 'resolved', label: 'Resolved' },
  { key: 'rejected', label: 'Rejected' },
];

const SORT_OPTIONS: { value: ComplaintSort; label: string }[] = [
  { value: 'submitted_desc', label: 'Date registered: newest first' },
  { value: 'submitted_asc', label: 'Date registered: oldest first' },
  { value: 'number_desc', label: 'Complaint number: highest first' },
  { value: 'number_asc', label: 'Complaint number: lowest first' },
  { value: 'status_asc', label: 'Status: A–Z' },
  { value: 'status_desc', label: 'Status: Z–A' },
];

/**
 * Backs the ЗЗП чл. 128, ал. 4 complaints register — see
 * backend ComplaintController's docblock. Distinct from the general
 * contact form (which just emails a message and persists nothing);
 * every row here is a numbered, tracked register entry with a status and
 * eventual resolution, not deletable once logged (no destroy route).
 */
export default function ComplaintsPage() {
  const [status, setStatus] = useState<ComplaintStatus | 'all'>('all');
  const [search, setSearch] = useState('');
  const [sort, setSort] = useState<ComplaintSort>('submitted_desc');
  const [page, setPage] = useState(1);
  const [reloadKey, setReloadKey] = useState(0);

  const { data, isLoading, error } = useAsync(
    () => fetchComplaints({ status: status === 'all' ? undefined : status, search: search || undefined, sort, page }),
    [status, search, sort, page, reloadKey],
    'Could not load the complaints register.',
  );

  function switchTab(nextStatus: ComplaintStatus | 'all') {
    setStatus(nextStatus);
    setPage(1);
  }

  return (
    <div>
      <h1 className="h3 mb-4">Complaints Register</h1>

      <LogComplaintForm onLogged={() => setReloadKey((key) => key + 1)} />

      <ul className="nav nav-tabs mb-3">
        {STATUS_TABS.map((tab) => (
          <li className="nav-item" key={tab.key}>
            <button type="button" className={`nav-link ${status === tab.key ? 'active' : ''}`} onClick={() => switchTab(tab.key)}>
              {tab.label}
            </button>
          </li>
        ))}
      </ul>

      <div className="row g-2 mb-4">
        <div className="col-sm-6 col-lg-4">
          <input
            type="search"
            className="form-control"
            placeholder="Search by complaint number..."
            value={search}
            onChange={(event) => {
              setSearch(event.target.value);
              setPage(1);
            }}
          />
        </div>
        <div className="col-sm-6 col-lg-4">
          <select
            className="form-select"
            aria-label="Sort by"
            value={sort}
            onChange={(event) => {
              setSort(event.target.value as ComplaintSort);
              setPage(1);
            }}
          >
            {SORT_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </div>
      </div>

      {isLoading && <LoadingState message="Loading complaints..." />}
      {!isLoading && error && <ErrorState message={error} />}
      {!isLoading && !error && data && data.data.length === 0 && <EmptyState title="No complaints logged yet" />}

      {!isLoading && !error && data && data.data.length > 0 && (
        <>
          <div className="table-responsive">
            <table className="table align-middle">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Order</th>
                  <th>Customer</th>
                  <th>Description</th>
                  <th>Submitted</th>
                  <th>Status</th>
                  <th>Resolution</th>
                </tr>
              </thead>
              <tbody>
                {data.data.map((complaint) => (
                  <ComplaintRow key={complaint.id} complaint={complaint} onUpdated={() => setReloadKey((key) => key + 1)} />
                ))}
              </tbody>
            </table>
          </div>
          <Pagination meta={data.meta} onPageChange={setPage} />
        </>
      )}
    </div>
  );
}

function LogComplaintForm({ onLogged }: { onLogged: () => void }) {
  const [orderNumber, setOrderNumber] = useState('');
  const [description, setDescription] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setIsSubmitting(true);
    setSubmitError(null);

    try {
      await logComplaint(orderNumber, description);
      setOrderNumber('');
      setDescription('');
      onLogged();
    } catch (err) {
      setSubmitError(getErrorMessage(err, 'Could not log the complaint.'));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <form className="card mb-4" onSubmit={(event) => void handleSubmit(event)}>
      <div className="card-body d-flex flex-column gap-3">
        <h2 className="h6 mb-0">Log a new complaint</h2>
        {submitError && <div className="alert alert-danger mb-0">{submitError}</div>}
        <div className="row g-3">
          <div className="col-sm-3">
            <label className="form-label">Order number</label>
            <input
              type="text"
              className="form-control"
              value={orderNumber}
              onChange={(event) => setOrderNumber(event.target.value)}
              placeholder="e.g. SM-20260822-AB12"
              required
            />
          </div>
          <div className="col-sm-9">
            <label className="form-label">Description</label>
            <textarea
              className="form-control"
              rows={2}
              value={description}
              onChange={(event) => setDescription(event.target.value)}
              required
            />
          </div>
        </div>
        <div>
          <button type="submit" className="btn btn-primary" disabled={isSubmitting}>
            {isSubmitting ? 'Logging...' : 'Log complaint'}
          </button>
        </div>
      </div>
    </form>
  );
}

function ComplaintRow({ complaint, onUpdated }: { complaint: Complaint; onUpdated: () => void }) {
  const [status, setStatus] = useState<ComplaintStatus>(complaint.status);
  const [resolution, setResolution] = useState(complaint.resolution ?? '');
  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  const isDirty = status !== complaint.status || resolution !== (complaint.resolution ?? '');

  async function handleSave() {
    setIsSaving(true);
    setSaveError(null);

    try {
      await updateComplaint(complaint.id, status, resolution || undefined);
      onUpdated();
    } catch (err) {
      setSaveError(getErrorMessage(err, 'Could not update the complaint.'));
    } finally {
      setIsSaving(false);
    }
  }

  return (
    <tr>
      <td>
        <code>{complaint.complaint_number}</code>
      </td>
      <td>{complaint.order.order_number}</td>
      <td>
        {complaint.order.customer_name}
        <br />
        <span className="text-muted small">{complaint.order.customer_email}</span>
      </td>
      <td style={{ maxWidth: 260 }}>{complaint.description}</td>
      <td>{new Date(complaint.submitted_at).toLocaleDateString('bg-BG')}</td>
      <td>
        <select className="form-select form-select-sm" value={status} onChange={(event) => setStatus(event.target.value as ComplaintStatus)}>
          <option value="received">Received</option>
          <option value="in_review">In Review</option>
          <option value="resolved">Resolved</option>
          <option value="rejected">Rejected</option>
        </select>
      </td>
      <td>
        <textarea
          className="form-control form-control-sm mb-2"
          rows={2}
          value={resolution}
          onChange={(event) => setResolution(event.target.value)}
          placeholder="Resolution notes"
        />
        {saveError && <div className="text-danger small mb-1">{saveError}</div>}
        {isDirty && (
          <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => void handleSave()} disabled={isSaving}>
            {isSaving ? 'Saving...' : 'Save'}
          </button>
        )}
      </td>
    </tr>
  );
}
