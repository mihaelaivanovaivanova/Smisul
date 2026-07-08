/**
 * Thin, dependency-free bridge to whatever ad-conversion pixels are loaded
 * on the page (Meta Pixel's `fbq`, Google's `gtag`) — both are declared
 * `unknown` here because neither library is bundled; a real pixel script
 * tag (added separately, per campaign) is what actually defines them on
 * `window`. Every call is a no-op until one is present, so this is safe to
 * call unconditionally from the funnel landing page regardless of whether
 * a pixel is configured for a given deployment.
 */
declare global {
  interface Window {
    fbq?: (...args: unknown[]) => void;
    gtag?: (...args: unknown[]) => void;
  }
}

export function trackFunnelAddToCart(value: number, currency: string): void {
  window.fbq?.('track', 'AddToCart', { value, currency });
  window.gtag?.('event', 'add_to_cart', { value, currency });
}
