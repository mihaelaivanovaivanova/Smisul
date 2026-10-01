import { useEffect, useState } from 'react';
import StarRating from './StarRating';
import { getErrorMessage } from '../../api/errors';
import { submitReviewForConfirmation } from '../../api/reviews';
import { reviews as reviewsCopy } from '../../content/copy';

interface AddReviewWizardProps {
  productSlug: string;
  productName: string;
  productImageUrl?: string;
  onClose: () => void;
}

type Step = 'rating' | 'review' | 'about' | 'done';

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * Guest-friendly "Add a review" flow — no login required. Eligibility is
 * checked server-side by the typed email against orders.customer_email
 * (see ReviewService::findEligibleOrderForEmail), not by who's logged in,
 * so the wizard always ends on the same generic "check your email" step
 * regardless of whether that email actually matched a delivered order —
 * the backend never reveals which (see ProductController::submitReview).
 */
export default function AddReviewWizard({ productSlug, productName, productImageUrl, onClose }: AddReviewWizardProps) {
  const [step, setStep] = useState<Step>('rating');
  const [rating, setRating] = useState(0);
  const [body, setBody] = useState('');
  const [email, setEmail] = useState('');
  const [displayName, setDisplayName] = useState('');
  const [isAnonymous, setIsAnonymous] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    function handleKeydown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        onClose();
      }
    }
    document.addEventListener('keydown', handleKeydown);
    return () => document.removeEventListener('keydown', handleKeydown);
  }, [onClose]);

  const isEmailValid = EMAIL_PATTERN.test(email.trim());
  const canSubmit = isEmailValid && displayName.trim() !== '' && !isSubmitting;

  async function handleSubmit() {
    if (!canSubmit) {
      if (!isEmailValid) {
        setError(reviewsCopy.addReviewWizard.invalidEmail);
      }
      return;
    }

    setIsSubmitting(true);
    setError(null);

    try {
      await submitReviewForConfirmation(productSlug, {
        rating,
        body,
        email: email.trim(),
        display_name: displayName.trim(),
        is_anonymous: isAnonymous,
      });
      setStep('done');
    } catch (err) {
      setError(getErrorMessage(err, reviewsCopy.addReviewWizard.submitError));
    } finally {
      setIsSubmitting(false);
    }
  }

  const stepTitle = {
    rating: reviewsCopy.addReviewWizard.ratingStepTitle,
    review: reviewsCopy.addReviewWizard.reviewStepTitle,
    about: reviewsCopy.addReviewWizard.aboutTitle,
    done: reviewsCopy.addReviewWizard.doneTitle,
  }[step];

  return (
    <>
      <div
        className="modal d-block"
        tabIndex={-1}
        role="dialog"
        aria-modal="true"
        aria-labelledby="add-review-wizard-title"
        onClick={onClose}
      >
        <div className="modal-dialog modal-dialog-centered" role="document" onClick={(event) => event.stopPropagation()}>
          <div className="modal-content">
            <div className="modal-header border-0 pb-0">
              <h2 className="modal-title h5" id="add-review-wizard-title">
                {stepTitle}
              </h2>
              <button
                type="button"
                className="btn-close"
                aria-label={reviewsCopy.addReviewWizard.closeAria}
                onClick={onClose}
              />
            </div>

            <div className="modal-body">
              {step === 'rating' && (
                <div className="text-center">
                  <p className="text-muted">{reviewsCopy.addReviewWizard.ratingStepSubtitle}</p>
                  {productImageUrl && (
                    <img src={productImageUrl} alt={productName} className="img-fluid mb-3" style={{ maxHeight: 160 }} />
                  )}
                  <div className="mb-3 fw-semibold">{productName}</div>
                  <div className="d-flex justify-content-center">
                    <StarRating rating={rating} onChange={setRating} size="lg" ariaLabel={reviewsCopy.ratingLabel} />
                  </div>
                </div>
              )}

              {step === 'review' && (
                <div className="mb-3">
                  <label htmlFor="wizard-review-body" className="form-label">
                    {reviewsCopy.bodyLabel}
                  </label>
                  <textarea
                    id="wizard-review-body"
                    className="form-control"
                    rows={4}
                    maxLength={5000}
                    value={body}
                    onChange={(event) => setBody(event.target.value)}
                  />
                </div>
              )}

              {step === 'about' && (
                <>
                  <p className="text-muted">{reviewsCopy.addReviewWizard.aboutSubtitle}</p>
                  <div className="mb-3">
                    <label htmlFor="wizard-email" className="form-label">
                      {reviewsCopy.addReviewWizard.emailLabel}
                    </label>
                    <input
                      id="wizard-email"
                      type="email"
                      className="form-control"
                      placeholder={reviewsCopy.addReviewWizard.emailPlaceholder}
                      value={email}
                      onChange={(event) => setEmail(event.target.value)}
                    />
                    <div className="form-text">{reviewsCopy.addReviewWizard.emailPrivacyNote}</div>
                  </div>
                  <div className="mb-3">
                    <label htmlFor="wizard-display-name" className="form-label">
                      {reviewsCopy.addReviewWizard.displayNameLabel}
                    </label>
                    <input
                      id="wizard-display-name"
                      type="text"
                      className="form-control"
                      placeholder={reviewsCopy.addReviewWizard.displayNamePlaceholder}
                      value={displayName}
                      onChange={(event) => setDisplayName(event.target.value)}
                    />
                  </div>
                  <div className="form-check mb-3">
                    <input
                      id="wizard-anonymous"
                      type="checkbox"
                      className="form-check-input"
                      checked={isAnonymous}
                      onChange={(event) => setIsAnonymous(event.target.checked)}
                    />
                    <label htmlFor="wizard-anonymous" className="form-check-label">
                      {reviewsCopy.addReviewWizard.anonymousLabel}
                    </label>
                  </div>
                  {error && (
                    <div className="text-danger small mb-2" aria-live="polite">
                      {error}
                    </div>
                  )}
                </>
              )}

              {step === 'done' && (
                <>
                  <p className="mb-2">{reviewsCopy.addReviewWizard.doneMessage}</p>
                  <p className="mb-0">{reviewsCopy.addReviewWizard.doneMessageDetail}</p>
                </>
              )}
            </div>

            <div className="modal-footer border-0 pt-0">
              {(step === 'review' || step === 'about') && (
                <button
                  type="button"
                  className="btn btn-outline-secondary"
                  disabled={isSubmitting}
                  onClick={() => setStep(step === 'review' ? 'rating' : 'review')}
                >
                  {reviewsCopy.addReviewWizard.back}
                </button>
              )}

              {step === 'rating' && (
                <button type="button" className="btn btn-primary" disabled={rating < 1} onClick={() => setStep('review')}>
                  {reviewsCopy.addReviewWizard.next}
                </button>
              )}

              {step === 'review' && (
                <button
                  type="button"
                  className="btn btn-primary"
                  disabled={body.trim() === ''}
                  onClick={() => setStep('about')}
                >
                  {reviewsCopy.addReviewWizard.next}
                </button>
              )}

              {step === 'about' && (
                <button type="button" className="btn btn-primary" disabled={!canSubmit} onClick={() => void handleSubmit()}>
                  {isSubmitting ? reviewsCopy.addReviewWizard.submitting : reviewsCopy.addReviewWizard.submit}
                </button>
              )}

              {step === 'done' && (
                <button type="button" className="btn btn-primary" onClick={onClose}>
                  {reviewsCopy.addReviewWizard.close}
                </button>
              )}
            </div>
          </div>
        </div>
      </div>
      <div className="modal-backdrop show"></div>
    </>
  );
}
