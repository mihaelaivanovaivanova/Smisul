import { Link } from 'react-router-dom';
import { useCookieConsent } from '../hooks/useCookieConsent';
import { cookieConsent } from '../content/copy';

/**
 * Only the banner itself — CookiePreferencesModal is mounted as its own
 * sibling in PublicLayout (not nested here), since it must stay reachable
 * from the footer's "cookie settings" link even after the banner has
 * already been dismissed for good.
 */
export default function CookieBanner() {
  const { choices, acceptAll, rejectAll, openPreferencesModal } = useCookieConsent();

  if (choices !== null) {
    return null;
  }

  return (
    <div
      className="position-fixed bottom-0 start-0 end-0 bg-white border-top shadow-lg p-3 p-md-4"
      style={{ zIndex: 1050 }}
      role="dialog"
      aria-live="polite"
      aria-label={cookieConsent.modal.title}
    >
      <div className="container d-flex flex-column flex-lg-row align-items-lg-center gap-3">
        <p className="mb-0 flex-grow-1 small">
          {cookieConsent.banner.message} <Link to="/legal/cookie-policy">{cookieConsent.banner.privacyLinkLabel}</Link>
        </p>
        <div className="d-flex flex-wrap gap-2">
          <button type="button" className="btn btn-outline-secondary btn-sm" onClick={openPreferencesModal}>
            {cookieConsent.banner.customize}
          </button>
          <button type="button" className="btn btn-outline-secondary btn-sm" onClick={() => void rejectAll()}>
            {cookieConsent.banner.rejectAll}
          </button>
          <button type="button" className="btn btn-primary btn-sm" onClick={() => void acceptAll()}>
            {cookieConsent.banner.acceptAll}
          </button>
        </div>
      </div>
    </div>
  );
}
