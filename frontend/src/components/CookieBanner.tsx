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
    <div className="cookie-banner" role="dialog" aria-live="polite" aria-label={cookieConsent.modal.title}>
      <p className="cookie-banner__message">
        {cookieConsent.banner.message} <Link to="/legal/cookie-policy">{cookieConsent.banner.privacyLinkLabel}</Link>
      </p>
      <div className="cookie-banner__actions">
        <button type="button" className="btn btn-primary btn-sm" onClick={() => void acceptAll()}>
          {cookieConsent.banner.acceptAll}
        </button>
        <div className="cookie-banner__secondary-actions">
          <button type="button" className="btn btn-outline-secondary btn-sm" onClick={() => void rejectAll()}>
            {cookieConsent.banner.rejectAll}
          </button>
          <button type="button" className="btn btn-outline-secondary btn-sm" onClick={openPreferencesModal}>
            {cookieConsent.banner.customize}
          </button>
        </div>
      </div>
    </div>
  );
}
