import { useState } from 'react';
import type { FormEvent } from 'react';
import StarRating from './StarRating';
import { getErrorMessage } from '../../api/errors';
import { createReview, updateReview } from '../../api/reviews';
import { reviews as reviewsCopy } from '../../content/copy';
import type { Review } from '../../types/review';

interface ReviewFormProps {
  /** Create mode when provided; edit mode when `existingReview` is set instead. */
  createContext?: { orderId: number; productVariantId: number };
  existingReview?: Review;
  onSaved: (review: Review) => void;
  onCancel: () => void;
}

export default function ReviewForm({ createContext, existingReview, onSaved, onCancel }: ReviewFormProps) {
  const [rating, setRating] = useState(existingReview?.rating ?? 0);
  const [title, setTitle] = useState(existingReview?.title ?? '');
  const [body, setBody] = useState(existingReview?.body ?? '');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const isEdit = existingReview !== undefined;

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (rating < 1) {
      setError(reviewsCopy.ratingRequired);
      return;
    }
    if (title.trim() === '') {
      setError(reviewsCopy.titleRequired);
      return;
    }
    if (body.trim() === '') {
      setError(reviewsCopy.bodyRequired);
      return;
    }

    setIsSubmitting(true);
    setError(null);

    try {
      const saved =
        isEdit && existingReview
          ? await updateReview(existingReview.id, { rating, title, body })
          : createContext
            ? await createReview({
                order_id: createContext.orderId,
                product_variant_id: createContext.productVariantId,
                rating,
                title,
                body,
              })
            : null;

      if (saved) {
        onSaved(saved);
      }
    } catch (err) {
      setError(getErrorMessage(err, isEdit ? reviewsCopy.updateError : reviewsCopy.submitError));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <form onSubmit={(event) => void handleSubmit(event)} className="border rounded p-3 mb-3">
      <div className="mb-3">
        <label className="form-label d-block">{reviewsCopy.ratingLabel}</label>
        <StarRating rating={rating} onChange={setRating} size="lg" ariaLabel={reviewsCopy.ratingLabel} />
      </div>

      <div className="mb-3">
        <label htmlFor="review-title" className="form-label">
          {reviewsCopy.titleLabel}
        </label>
        <input
          id="review-title"
          type="text"
          className="form-control"
          maxLength={150}
          value={title}
          onChange={(event) => setTitle(event.target.value)}
        />
      </div>

      <div className="mb-3">
        <label htmlFor="review-body" className="form-label">
          {reviewsCopy.bodyLabel}
        </label>
        <textarea
          id="review-body"
          className="form-control"
          rows={4}
          maxLength={5000}
          value={body}
          onChange={(event) => setBody(event.target.value)}
        />
      </div>

      {error && (
        <div className="text-danger small mb-2" aria-live="polite">
          {error}
        </div>
      )}

      <div className="d-flex gap-2">
        <button type="submit" className="btn btn-primary" disabled={isSubmitting}>
          {isSubmitting ? reviewsCopy.submitting : isEdit ? reviewsCopy.saveChanges : reviewsCopy.submit}
        </button>
        <button type="button" className="btn btn-outline-secondary" onClick={onCancel} disabled={isSubmitting}>
          {reviewsCopy.cancel}
        </button>
      </div>
    </form>
  );
}
