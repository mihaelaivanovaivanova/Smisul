import { useState } from 'react';
import { Link } from 'react-router-dom';
import { deleteReview, fetchMyReviews } from '../api/reviews';
import { getErrorMessage } from '../api/errors';
import { useAsync } from '../hooks/useAsync';
import LoadingState from '../components/LoadingState';
import ErrorState from '../components/ErrorState';
import EmptyState from '../components/EmptyState';
import Seo from '../components/Seo';
import Breadcrumbs from '../components/Breadcrumbs';
import ReviewCard from '../components/reviews/ReviewCard';
import ReviewForm from '../components/reviews/ReviewForm';
import { breadcrumbLabels, reviews as reviewsCopy } from '../content/copy';
import type { Review } from '../types/review';

export default function ReviewsPage() {
  const [refreshKey, setRefreshKey] = useState(0);
  const [editingReview, setEditingReview] = useState<Review | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  const { data: myReviews, isLoading, error } = useAsync(() => fetchMyReviews(), [refreshKey], reviewsCopy.loadError);

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
    <div className="container py-4">
      <Seo title={reviewsCopy.seoTitle} description={reviewsCopy.seoDescription} />
      <Breadcrumbs items={[{ label: breadcrumbLabels.home, to: '/' }, { label: reviewsCopy.myReviews.title }]} />
      <h1 className="mb-4 mt-3">{reviewsCopy.myReviews.title}</h1>

      {isLoading && <LoadingState message={reviewsCopy.loading} />}
      {!isLoading && error && <ErrorState message={error} />}

      {!isLoading && !error && myReviews && myReviews.length === 0 && (
        <>
          <EmptyState title={reviewsCopy.myReviews.emptyTitle} message={reviewsCopy.myReviews.emptyMessage} />
          <div className="mt-3">
            <Link to="/profile/orders" className="btn btn-primary">
              {reviewsCopy.myReviews.emptyCta}
            </Link>
          </div>
        </>
      )}

      {actionError && <ErrorState message={actionError} />}

      {editingReview && (
        <ReviewForm
          existingReview={editingReview}
          onSaved={() => {
            setEditingReview(null);
            setRefreshKey((key) => key + 1);
          }}
          onCancel={() => setEditingReview(null)}
        />
      )}

      {!isLoading && !error && myReviews && myReviews.length > 0 && (
        <div>
          {myReviews.map((review) => (
            <ReviewCard key={review.id} review={review} onEdit={setEditingReview} onDelete={(r) => void handleDelete(r)} />
          ))}
        </div>
      )}
    </div>
  );
}
