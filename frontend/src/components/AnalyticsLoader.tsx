import { useEffect } from 'react';
import { useCookieConsent } from '../hooks/useCookieConsent';
import { initAnalytics } from '../services/analytics';

/**
 * Bridges cookie consent to the pixel layer: (re)runs the idempotent
 * initAnalytics whenever the visitor's choices change, so tags load the
 * moment consent is granted — including right after clicking "accept" on
 * the banner, not just on the next page load. Renders nothing. Mounted in
 * PublicLayout only, so the admin panel stays pixel-free.
 */
export default function AnalyticsLoader() {
  const { choices } = useCookieConsent();

  useEffect(() => {
    initAnalytics(choices);
  }, [choices]);

  return null;
}
