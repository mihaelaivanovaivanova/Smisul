import { useEffect, useRef } from 'react';
import { useLocation } from 'react-router-dom';
import { useCookieConsent } from '../hooks/useCookieConsent';
import { initAnalytics, trackPageView } from '../services/analytics';

/**
 * Bridges cookie consent to the pixel layer: (re)runs the idempotent
 * initAnalytics whenever the visitor's choices change, so tags load the
 * moment consent is granted — including right after clicking "accept" on
 * the banner, not just on the next page load. Renders nothing. Mounted in
 * PublicLayout only, so the admin panel stays pixel-free.
 */
export default function AnalyticsLoader() {
  const { choices } = useCookieConsent();
  const { pathname, search } = useLocation();
  const previousPage = useRef(`${pathname}${search}`);

  useEffect(() => {
    initAnalytics(choices);
  }, [choices]);

  useEffect(() => {
    const currentPage = `${pathname}${search}`;

    // loadMetaPixel sends the first PageView when consent initializes the
    // tag. Only subsequent SPA navigations need an additional virtual view.
    if (previousPage.current !== currentPage) {
      previousPage.current = currentPage;
      trackPageView();
    }
  }, [pathname, search]);

  return null;
}
