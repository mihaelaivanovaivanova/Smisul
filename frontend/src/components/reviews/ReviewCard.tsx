import StarRating from './StarRating';
import { reviews as reviewsCopy } from '../../content/copy';
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
  return (
    <div className="border-bottom py-3">
      <div className="d-flex justify-content-between align-items-start gap-2 flex-wrap">
        <StarRating rating={review.rating} />
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

      {review.is_own && (onEdit || onDelete) && (
        <div className="d-flex align-items-center gap-3">
          {onEdit && (
            <button type="button" className="btn btn-link btn-sm p-0" onClick={() => onEdit(review)}>
              {reviewsCopy.editReview}
            </button>
          )}
          {onDelete && (
            <button type="button" className="btn btn-link btn-sm p-0 text-danger" onClick={() => onDelete(review)}>
              {reviewsCopy.delete}
            </button>
          )}
        </div>
      )}
    </div>
  );
}
