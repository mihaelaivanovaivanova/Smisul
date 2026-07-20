import { useState } from 'react';
import { useAuth } from '../hooks/useAuth';
import { acceptOutstandingLegalDocuments } from '../api/legal';
import { legalUpdateModal } from '../content/copy';

/**
 * Blocking modal shown whenever the signed-in account has outstanding
 * Terms/Privacy acceptances (see User.outstanding_legal_documents,
 * populated from ConsentService::outstandingForAccount on every auth
 * response) — either a document was published after they last agreed, or
 * their account pre-dates this tracking entirely. Unlike CookieBanner,
 * this is a full-screen overlay with no dismiss/close action: re-accepting
 * is the only way past it, since Terms/Privacy acceptance is a legal
 * requirement for the account relationship, not an optional preference.
 * Mounted in PublicLayout, outside the normal page flow.
 */
export default function LegalDocumentUpdateModal() {
  const { user, setUser } = useAuth();
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const outstanding = user?.outstanding_legal_documents ?? [];

  if (!user || outstanding.length === 0) {
    return null;
  }

  const handleAccept = async () => {
    setIsSubmitting(true);
    setError(null);

    try {
      const stillOutstanding = await acceptOutstandingLegalDocuments();
      setUser({ ...user, outstanding_legal_documents: stillOutstanding });
    } catch {
      setError(legalUpdateModal.error);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div
      className="position-fixed top-0 start-0 end-0 bottom-0 d-flex align-items-center justify-content-center p-3"
      style={{ zIndex: 1080, backgroundColor: 'rgba(0, 0, 0, 0.6)' }}
      role="dialog"
      aria-modal="true"
      aria-live="assertive"
    >
      <div className="bg-white rounded shadow-lg p-4" style={{ maxWidth: 480, width: '100%' }}>
        <h2 className="h5 mb-2">{legalUpdateModal.title}</h2>
        <p className="small text-muted mb-3">{legalUpdateModal.intro}</p>

        <ul className="list-unstyled mb-3">
          {outstanding.map((document) => (
            <li key={document.id} className="mb-1">
              {/* Opens in a new tab so accepting isn't blocked on losing this modal's state mid-read. */}
              <a href={`/legal/${document.slug}`} target="_blank" rel="noreferrer">
                {document.title} — {legalUpdateModal.review}
              </a>
            </li>
          ))}
        </ul>

        {error && <p className="text-danger small mb-3">{error}</p>}

        <button type="button" className="btn btn-primary w-100" onClick={() => void handleAccept()} disabled={isSubmitting}>
          {isSubmitting ? legalUpdateModal.accepting : legalUpdateModal.accept}
        </button>
      </div>
    </div>
  );
}
