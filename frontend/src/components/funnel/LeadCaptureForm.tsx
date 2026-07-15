import { useState } from 'react';
import type { FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { submitFunnelLead } from '../../api/funnel';
import { getErrorMessage } from '../../api/errors';
import { trackLead } from '../../services/analytics';
import { funnelLead } from '../../content/copy';

/**
 * The funnel landing page's email opt-in — captures visitors who aren't
 * buying today so the funnel still produces a lead. One field, one button;
 * consent is the explicit submission itself, spelled out by the privacy
 * note underneath (GDPR soft opt-in for the stated purpose only).
 */
export default function LeadCaptureForm() {
  const [email, setEmail] = useState('');
  const [isPending, setIsPending] = useState(false);
  const [isSubmitted, setIsSubmitted] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setIsPending(true);
    setError(null);

    try {
      await submitFunnelLead(email.trim());
      trackLead();
      setIsSubmitted(true);
    } catch (err) {
      setError(getErrorMessage(err, funnelLead.error));
    } finally {
      setIsPending(false);
    }
  }

  if (isSubmitted) {
    return (
      <p className="funnel-lead__success mb-0" role="status">
        {funnelLead.success}
      </p>
    );
  }

  return (
    <form className="funnel-lead__form" onSubmit={(event) => void handleSubmit(event)}>
      <div className="funnel-lead__row">
        <input
          type="email"
          className="form-control"
          placeholder={funnelLead.placeholder}
          aria-label={funnelLead.emailAria}
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          required
          disabled={isPending}
        />
        <button type="submit" className="btn btn-primary" disabled={isPending}>
          {isPending && <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" />}
          {funnelLead.submit}
        </button>
      </div>
      <div aria-live="polite">{error && <div className="text-danger small mt-2">{error}</div>}</div>
      <p className="funnel-lead__consent mb-0">
        {funnelLead.consentPrefix}
        <Link to="/legal/privacy-policy">{funnelLead.consentLinkLabel}</Link>
        {funnelLead.consentSuffix}
      </p>
    </form>
  );
}
