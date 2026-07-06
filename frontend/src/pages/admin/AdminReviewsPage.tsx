import { useState } from 'react';
import {
  approveReview,
  bulkModerateReviews,
  fetchAdminReviewStatistics,
  fetchAdminReviews,
  hideReview,
  rejectReview,
  replyToReview,
} from '../../api/admin/reviews';
import type { AdminReviewFilters } from '../../api/admin/reviews';
import { useAsync } from '../../hooks/useAsync';
import { getErrorMessage } from '../../api/errors';
import LoadingState from '../../components/LoadingState';
import ErrorState from '../../components/ErrorState';
import EmptyState from '../../components/EmptyState';
import Pagination from '../../components/listing/Pagination';
import type { AdminReview } from '../../types/admin';

interface StatCardProps {
  label: string;
  value: string | number;
}

function StatCard({ label, value }: StatCardProps) {
  return (
    <div className="col-sm-6 col-lg-3">
      <div className="card h-100">
        <div className="card-body">
          <div className="text-muted small mb-1">{label}</div>
          <div className="fs-4 fw-semibold">{value}</div>
        </div>
      </div>
    </div>
  );
}

const DEFAULT_FILTERS: AdminReviewFilters = { status: '', rating: undefined, search: '', sort: 'newest' };

export default function AdminReviewsPage() {
  const [page, setPage] = useState(1);
  const [filters, setFilters] = useState<AdminReviewFilters>(DEFAULT_FILTERS);
  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [replyingTo, setReplyingTo] = useState<number | null>(null);
  const [replyText, setReplyText] = useState('');
  const [actionError, setActionError] = useState<string | null>(null);
  const [refreshKey, setRefreshKey] = useState(0);

  const { data: stats } = useAsync(() => fetchAdminReviewStatistics(), [refreshKey], 'Could not load review statistics.');

  const { data, isLoading, error } = useAsync(
    () => fetchAdminReviews({ ...filters, status: filters.status || undefined, search: filters.search || undefined, page }),
    [page, filters, refreshKey],
    'Could not load reviews.',
  );

  function handleFiltersChange(next: Partial<AdminReviewFilters>) {
    setFilters((prev) => ({ ...prev, ...next }));
    setPage(1);
    setSelectedIds([]);
  }

  function refresh() {
    setRefreshKey((key) => key + 1);
  }

  async function runAction(action: () => Promise<AdminReview>) {
    setActionError(null);
    try {
      await action();
      refresh();
    } catch (err) {
      setActionError(getErrorMessage(err, 'Action failed.'));
    }
  }

  async function handleBulk(status: 'approved' | 'rejected' | 'hidden') {
    if (selectedIds.length === 0) {
      return;
    }
    setActionError(null);
    try {
      await bulkModerateReviews(selectedIds, status);
      setSelectedIds([]);
      refresh();
    } catch (err) {
      setActionError(getErrorMessage(err, 'Bulk action failed.'));
    }
  }

  function toggleSelected(id: number) {
    setSelectedIds((prev) => (prev.includes(id) ? prev.filter((existing) => existing !== id) : [...prev, id]));
  }

  async function submitReply(id: number) {
    if (replyText.trim() === '') {
      return;
    }
    await runAction(() => replyToReview(id, replyText));
    setReplyingTo(null);
    setReplyText('');
  }

  return (
    <div>
      <h1 className="h3 mb-4">Reviews</h1>

      {stats && (
        <div className="row g-3 mb-4">
          <StatCard label="Total reviews" value={stats.total_reviews} />
          <StatCard label="Pending" value={stats.pending_count} />
          <StatCard label="Average rating" value={stats.average_rating} />
          <StatCard label="Approved" value={stats.reviews_by_status.approved ?? 0} />
        </div>
      )}

      <div className="d-flex flex-wrap gap-2 mb-3">
        <select
          className="form-select form-select-sm w-auto"
          value={filters.status ?? ''}
          onChange={(event) => handleFiltersChange({ status: event.target.value })}
        >
          <option value="">All statuses</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
          <option value="hidden">Hidden</option>
        </select>

        <select
          className="form-select form-select-sm w-auto"
          value={filters.rating ?? ''}
          onChange={(event) => handleFiltersChange({ rating: event.target.value ? Number(event.target.value) : undefined })}
        >
          <option value="">Any rating</option>
          {[5, 4, 3, 2, 1].map((star) => (
            <option key={star} value={star}>
              {star} stars
            </option>
          ))}
        </select>

        <input
          type="search"
          className="form-control form-control-sm w-auto"
          placeholder="Search title, body, customer…"
          value={filters.search ?? ''}
          onChange={(event) => handleFiltersChange({ search: event.target.value })}
        />

        {selectedIds.length > 0 && (
          <div className="d-flex gap-2 ms-auto">
            <span className="align-self-center small text-muted">{selectedIds.length} selected</span>
            <button type="button" className="btn btn-outline-success btn-sm" onClick={() => void handleBulk('approved')}>
              Approve
            </button>
            <button type="button" className="btn btn-outline-danger btn-sm" onClick={() => void handleBulk('rejected')}>
              Reject
            </button>
            <button type="button" className="btn btn-outline-secondary btn-sm" onClick={() => void handleBulk('hidden')}>
              Hide
            </button>
          </div>
        )}
      </div>

      {actionError && <ErrorState message={actionError} />}

      {isLoading && <LoadingState message="Loading reviews..." />}
      {!isLoading && error && <ErrorState message={error} />}
      {!isLoading && !error && data && data.data.length === 0 && <EmptyState title="No reviews found" />}

      {!isLoading && !error && data && data.data.length > 0 && (
        <>
          <div className="table-responsive">
            <table className="table align-middle">
              <thead>
                <tr>
                  <th></th>
                  <th>Product</th>
                  <th>Customer</th>
                  <th>Rating</th>
                  <th>Title / Body</th>
                  <th>Status</th>
                  <th>Helpful</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {data.data.map((review) => (
                  <tr key={review.id}>
                    <td>
                      <input
                        type="checkbox"
                        className="form-check-input"
                        checked={selectedIds.includes(review.id)}
                        onChange={() => toggleSelected(review.id)}
                      />
                    </td>
                    <td>{review.product.name}</td>
                    <td>
                      {review.customer.name}
                      <div className="text-muted small">{review.customer.email}</div>
                    </td>
                    <td>{review.rating} / 5</td>
                    <td style={{ maxWidth: '20rem' }}>
                      <div className="fw-semibold">{review.title}</div>
                      <div className="text-muted small text-truncate">{review.body}</div>
                      {review.admin_reply && (
                        <div className="small mt-1">
                          <span className="fw-semibold">Reply:</span> {review.admin_reply}
                        </div>
                      )}
                    </td>
                    <td>
                      <span
                        className={`badge text-bg-${
                          review.status === 'approved'
                            ? 'success'
                            : review.status === 'rejected'
                              ? 'danger'
                              : review.status === 'hidden'
                                ? 'secondary'
                                : 'warning'
                        }`}
                      >
                        {review.status}
                      </span>
                    </td>
                    <td>{review.helpful_count}</td>
                    <td>
                      <div className="d-flex flex-column gap-1">
                        {review.status !== 'approved' && (
                          <button type="button" className="btn btn-outline-success btn-sm" onClick={() => void runAction(() => approveReview(review.id))}>
                            Approve
                          </button>
                        )}
                        {review.status !== 'rejected' && (
                          <button type="button" className="btn btn-outline-danger btn-sm" onClick={() => void runAction(() => rejectReview(review.id))}>
                            Reject
                          </button>
                        )}
                        {review.status !== 'hidden' && (
                          <button type="button" className="btn btn-outline-secondary btn-sm" onClick={() => void runAction(() => hideReview(review.id))}>
                            Hide
                          </button>
                        )}
                        <button
                          type="button"
                          className="btn btn-outline-primary btn-sm"
                          onClick={() => {
                            setReplyingTo(review.id);
                            setReplyText(review.admin_reply ?? '');
                          }}
                        >
                          Reply
                        </button>
                      </div>

                      {replyingTo === review.id && (
                        <div className="mt-2" style={{ minWidth: '16rem' }}>
                          <textarea
                            className="form-control form-control-sm mb-1"
                            rows={2}
                            value={replyText}
                            onChange={(event) => setReplyText(event.target.value)}
                          />
                          <div className="d-flex gap-1">
                            <button type="button" className="btn btn-primary btn-sm" onClick={() => void submitReply(review.id)}>
                              Send
                            </button>
                            <button
                              type="button"
                              className="btn btn-outline-secondary btn-sm"
                              onClick={() => {
                                setReplyingTo(null);
                                setReplyText('');
                              }}
                            >
                              Cancel
                            </button>
                          </div>
                        </div>
                      )}
                    </td>
                  </tr>
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
