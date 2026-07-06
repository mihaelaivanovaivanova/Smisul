import { useState } from 'react';
import ReviewCard from './ReviewCard';
import ReviewForm from './ReviewForm';
import StarRating from './StarRating';
import RatingDistribution from './RatingDistribution';
import { deleteReview, fetchMyReviews, fetchProductReviews, fetchReviewSummary } from '../../api/reviews';
import { getErrorMessage } from '../../api/errors';
import { useAsync } from '../../hooks/useAsync';
import { useAuth } from '../../hooks/useAuth';
import LoadingState from '../LoadingState';
import ErrorState from '../ErrorState';
import EmptyState from '../EmptyState';
import Pagination from '../listing/Pagination';
import { reviews as reviewsCopy } from '../../content/copy';
import type { Review, ReviewSortOption } from '../../types/review';

interface ReviewsSectionProps {
  productSlug: string;
  /** Set when arriving from a delivered order's "write a review" link (see OrderConfirmationPage). */
  writePrompt?: { orderId: number; productVariantId: number };
}

export default function ReviewsSection({ productSlug, writePrompt }: ReviewsSectionProps) {
  const { isAuthenticated } = useAuth();
  const [sort, setSort] = useState<ReviewSortOption>('newest');
  const [page, setPage] = useState(1);
  const [isFormOpen, setIsFormOpen] = useState(Boolean(writePrompt));
  const [editingReview, setEditingReview] = useState<Review | undefined>(undefined);
  const [refreshKey, setRefreshKey] = useState(0);
  const [actionError, setActionError] = useState<string | null>(null);

  const { data: summary, error: summaryError } = useAsync(
    () => fetchReviewSummary(productSlug),
    [productSlug, refreshKey],
    reviewsCopy.summaryLoadError,
  );

  const { data: list, isLoading, error } = useAsync(
    () => fetchProductReviews(productSlug, sort, page),
    [productSlug, sort, page, refreshKey],
    reviewsCopy.loadError,
  );

  const { data: myReviews } = useAsync<Review[]>(
    () => (isAuthenticated && writePrompt ? fetchMyReviews() : Promise.resolve([])),
    [isAuthenticated, writePrompt, refreshKey],
    reviewsCopy.loadError,
  );

  const existingReviewForPrompt = writePrompt
    ? myReviews?.find((review) => review.order_id === writePrompt.orderId)
    : undefined;

  function handleSaved() {
    setIsFormOpen(false);
    setEditingReview(undefined);
    setRefreshKey((key) => key + 1);
  }

  function handleEdit(review: Review) {
    setEditingReview(review);
    setIsFormOpen(true);
  }

  async function handleDelete(review: Review) {
    if (!window.confirm(reviewsCopy.deleteConfirm)) {
      return;
    }
    setActionError(null);
    try {
      await deleteReview(review.id);
      setRefreshKey((key) => key + 1);
    } catch (err) {
      setActionError(getErrorMessage(err, reviewsCopy.deleteError));
    }
  }

  return (
    <div>
      <h2 className="h5 mb-3">{reviewsCopy.title}</h2>

      {summaryError && <ErrorState message={summaryError} />}
      {summary && (
        <div className="row g-4 mb-4">
          <div className="col-12 col-sm-4">
            <div className="display-6 mb-1">{summary.average_rating}</div>
            <StarRating rating={summary.average_rating} size="lg" />
            <div className="text-muted small mt-1">{reviewsCopy.reviewCount(summary.review_count)}</div>
          </div>
          <div className="col-12 col-sm-8">
            <RatingDistribution summary={summary} />
          </div>
        </div>
      )}

      {writePrompt && !isFormOpen && (
        <div className="alert alert-light border d-flex justify-content-between align-items-center flex-wrap gap-2">
          <span>{existingReviewForPrompt ? reviewsCopy.writePrompt.alreadyReviewed : reviewsCopy.writePrompt.title}</span>
          <button
            type="button"
            className="btn btn-primary btn-sm"
            onClick={() => {
              setEditingReview(existingReviewForPrompt);
              setIsFormOpen(true);
            }}
          >
            {existingReviewForPrompt ? reviewsCopy.writePrompt.editCta : reviewsCopy.writePrompt.cta}
          </button>
        </div>
      )}

      {isFormOpen && writePrompt && (
        <ReviewForm
          createContext={editingReview ? undefined : { orderId: writePrompt.orderId, productVariantId: writePrompt.productVariantId }}
          existingReview={editingReview}
          onSaved={handleSaved}
          onCancel={() => {
            setIsFormOpen(false);
            setEditingReview(undefined);
          }}
        />
      )}

      {actionError && <ErrorState message={actionError} />}

      <div className="d-flex justify-content-end mb-3">
        <label className="d-flex align-items-center gap-2 small">
          {reviewsCopy.sortLabel}
          <select
            className="form-select form-select-sm w-auto"
            value={sort}
            onChange={(event) => {
              setSort(event.target.value as ReviewSortOption);
              setPage(1);
            }}
          >
            <option value="newest">{reviewsCopy.sortOptions.newest}</option>
            <option value="highest">{reviewsCopy.sortOptions.highest}</option>
            <option value="lowest">{reviewsCopy.sortOptions.lowest}</option>
            <option value="helpful">{reviewsCopy.sortOptions.helpful}</option>
          </select>
        </label>
      </div>

      {isLoading && <LoadingState message={reviewsCopy.loading} />}
      {!isLoading && error && <ErrorState message={error} />}
      {!isLoading && !error && list && list.data.length === 0 && (
        <EmptyState title={reviewsCopy.emptyTitle} message={reviewsCopy.emptyMessage} />
      )}

      {!isLoading && !error && list && list.data.length > 0 && (
        <>
          <div>
            {list.data.map((review) => (
              <ReviewCard key={review.id} review={review} onEdit={handleEdit} onDelete={(r) => void handleDelete(r)} />
            ))}
          </div>
          <div className="mt-3">
            <Pagination meta={list.meta} onPageChange={setPage} />
          </div>
        </>
      )}
    </div>
  );
}
