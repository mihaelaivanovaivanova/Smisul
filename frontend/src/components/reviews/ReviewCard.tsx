import { useState } from 'react';
import StarRating from './StarRating';
import { getErrorMessage } from '../../api/errors';
import { markReviewHelpful } from '../../api/reviews';
import { reviews as reviewsCopy } from '../../content/copy';
import { useAuth } from '../../hooks/useAuth';
import type { Review } from '../../types/review';

interface ReviewCardProps {
  review: Review;
  onEdit?: (review: Review) => void;
  onDelete?: (review: Review) => void;
}

const STATUS_LABEL: Record<string, string> = {
  pending: reviewsCopy.statusPending,
  approved: reviewsCopy.statusApproved,
  rejected: reviewsCopy.statusRejected,
  hidden: reviewsCopy.statusHidden,
};

export default function ReviewCard({ review, onEdit, onDelete }: ReviewCardProps) {
  const { isAuthenticated } = useAuth();
  const [helpfulCount, setHelpfulCount] = useState(review.helpful_count);
  const [isVoting, setIsVoting] = useState(false);
  const [voteError, setVoteError] = useState<string | null>(null);

  async function handleHelpful() {
    setIsVoting(true);
    setVoteError(null);
    try {
      const result = await markReviewHelpful(review.id);
      setHelpfulCount(result.helpful_count);
    } catch (err) {
      setVoteError(getErrorMessage(err, reviewsCopy.helpfulError));
    } finally {
      setIsVoting(false);
    }
  }

  return (
    <div className="border-bottom py-3">
      <div className="d-flex justify-content-between align-items-start gap-2 flex-wrap">
        <div>
          <StarRating rating={review.rating} />
          <div className="fw-semibold mt-1">{review.title}</div>
        </div>
        <div className="text-muted small text-end">
          <div>{review.author_name}</div>
          <div>{new Date(review.created_at).toLocaleDateString('bg-BG')}</div>
        </div>
      </div>

      <div className="d-flex align-items-center gap-2 mt-1">
        {review.verified_purchase && <span className="badge text-bg-success-subtle text-success-emphasis">{reviewsCopy.verifiedPurchase}</span>}
        {review.is_own && review.status && (
          <span className="badge text-bg-secondary-subtle text-secondary-emphasis">{STATUS_LABEL[review.status] ?? review.status}</span>
        )}
      </div>

      <p className="mb-2 mt-2">{review.body}</p>

      {review.admin_reply && (
        <div className="bg-body-tertiary rounded p-2 mb-2 small">
          <div className="fw-semibold">{reviewsCopy.adminReplyLabel}</div>
          <div>{review.admin_reply}</div>
        </div>
      )}

      <div className="d-flex align-items-center gap-3">
        {isAuthenticated && !review.is_own && (
          <button type="button" className="btn btn-outline-secondary btn-sm" disabled={isVoting} onClick={() => void handleHelpful()}>
            {reviewsCopy.helpfulCount(helpfulCount)}
          </button>
        )}
        {!isAuthenticated && helpfulCount > 0 && <span className="text-muted small">{reviewsCopy.helpfulCount(helpfulCount)}</span>}

        {review.is_own && onEdit && (
          <button type="button" className="btn btn-link btn-sm p-0" onClick={() => onEdit(review)}>
            {reviewsCopy.editReview}
          </button>
        )}
        {review.is_own && onDelete && (
          <button type="button" className="btn btn-link btn-sm p-0 text-danger" onClick={() => onDelete(review)}>
            {reviewsCopy.delete}
          </button>
        )}
      </div>

      {voteError && (
        <div className="text-danger small mt-1" aria-live="polite">
          {voteError}
        </div>
      )}
    </div>
  );
}
