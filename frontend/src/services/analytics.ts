import type { CookieCategoryChoices } from '../types/consent';

/**
 * Ad/analytics pixel layer. Nothing loads by default: each tag needs both
 * its env-configured ID (VITE_META_PIXEL_ID / VITE_GA4_MEASUREMENT_ID) and
 * the visitor's matching cookie-consent category — Meta Pixel is an ad
 * tool, so it rides on `marketing`; GA4 rides on `analytics`. Scripts are
 * injected only after consent (see AnalyticsLoader), never before; a
 * mid-session revocation stops applying on the next full page load, which
 * is the standard load-after-consent model.
 *
 * Every track* helper is a safe no-op while its tag isn't loaded, so call
 * sites never need to know whether a pixel is configured.
 */
declare global {
  interface Window {
    fbq?: (...args: unknown[]) => void;
    gtag?: (...args: unknown[]) => void;
    dataLayer?: unknown[];
    _fbq?: unknown;
  }
}

const META_PIXEL_ID: string = import.meta.env.VITE_META_PIXEL_ID ?? '';
const GA4_MEASUREMENT_ID: string = import.meta.env.VITE_GA4_MEASUREMENT_ID ?? '';

let metaPixelLoaded = false;
let ga4Loaded = false;

function loadMetaPixel(): void {
  if (metaPixelLoaded || !META_PIXEL_ID) {
    return;
  }
  metaPixelLoaded = true;

  // Meta's own bootstrap, minus the IIFE wrapper: define a queuing fbq
  // stub immediately, then load the real script over it.
  const fbq: ((...args: unknown[]) => void) & { queue?: unknown[]; push?: unknown; loaded?: boolean; version?: string } = (
    ...args: unknown[]
  ) => {
    (fbq.queue as unknown[]).push(args);
  };
  fbq.queue = [];
  fbq.push = fbq;
  fbq.loaded = true;
  fbq.version = '2.0';
  window.fbq = fbq;
  window._fbq = fbq;

  const script = document.createElement('script');
  script.async = true;
  script.src = 'https://connect.facebook.net/en_US/fbevents.js';
  document.head.appendChild(script);

  window.fbq('init', META_PIXEL_ID);
  window.fbq('track', 'PageView');
}

function loadGa4(): void {
  if (ga4Loaded || !GA4_MEASUREMENT_ID) {
    return;
  }
  ga4Loaded = true;

  window.dataLayer = window.dataLayer ?? [];
  // gtag must push the raw arguments object, not a spread array — GA4's
  // snippet relies on that exact shape.
  // eslint-disable-next-line prefer-rest-params
  window.gtag = function gtag() {
    // eslint-disable-next-line prefer-rest-params
    window.dataLayer!.push(arguments);
  };

  const script = document.createElement('script');
  script.async = true;
  script.src = `https://www.googletagmanager.com/gtag/js?id=${GA4_MEASUREMENT_ID}`;
  document.head.appendChild(script);

  window.gtag('js', new Date());
  window.gtag('config', GA4_MEASUREMENT_ID);
}

/** Idempotent — call whenever consent choices change (see AnalyticsLoader). */
export function initAnalytics(choices: CookieCategoryChoices | null): void {
  if (!choices) {
    return;
  }
  if (choices.marketing) {
    loadMetaPixel();
  }
  if (choices.analytics) {
    loadGa4();
  }
}

/** Funnel landing page viewed with the product in hand. */
export function trackFunnelViewContent(name: string, value: number, currency: string): void {
  window.fbq?.('track', 'ViewContent', { content_name: name, value, currency });
  window.gtag?.('event', 'view_item', { currency, value, items: [{ item_name: name }] });
}

export function trackFunnelAddToCart(value: number, currency: string): void {
  window.fbq?.('track', 'AddToCart', { value, currency });
  window.gtag?.('event', 'add_to_cart', { value, currency });
}

export function trackBeginCheckout(value: number, currency: string): void {
  window.fbq?.('track', 'InitiateCheckout', { value, currency });
  window.gtag?.('event', 'begin_checkout', { value, currency });
}

const PURCHASE_TRACKED_KEY_PREFIX = 'analytics.purchase.';

/**
 * Fired from the order-confirmation page. Deduplicated per order via
 * sessionStorage so a refresh of the confirmation page doesn't report the
 * same revenue twice.
 */
export function trackPurchase(orderNumber: string, value: number, currency: string): void {
  const key = PURCHASE_TRACKED_KEY_PREFIX + orderNumber;

  try {
    if (sessionStorage.getItem(key)) {
      return;
    }
    sessionStorage.setItem(key, '1');
  } catch {
    // Storage unavailable (private mode edge cases) — better to risk a
    // duplicate on refresh than to never track the purchase.
  }

  window.fbq?.('track', 'Purchase', { value, currency });
  window.gtag?.('event', 'purchase', { transaction_id: orderNumber, value, currency });
}

/** The funnel landing page's email opt-in succeeded. */
export function trackLead(): void {
  window.fbq?.('track', 'Lead');
  window.gtag?.('event', 'generate_lead');
}
