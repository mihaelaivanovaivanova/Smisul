import { createPortal } from 'react-dom';
import { discountBadge } from '../../content/copy';

/**
 * Floating discount-code promo sticker - same always-mounted, never
 * scroll-toggled corner seal as BoxNowBadge.tsx, just a flat image
 * instead of hand-drawn SVG (the source graphic, discount-badge.webp, is
 * a complete ready-made badge with its own baked-in shadow/border - kept
 * exactly as supplied, no cropping into a circle). Links to #pricing so a
 * shopper who notices it lands straight on the buy box to use the code.
 *
 * Portaled to document.body for the same reason as BoxNowBadge.tsx:
 * .funnel-page's own `overflow-x: hidden` (for the horizontal
 * scroll-reveal animations elsewhere) clips position:fixed descendants in
 * Chromium/WebKit otherwise.
 */
export default function DiscountBadge() {
  return createPortal(
    <a href="#pricing" className="funnel-discount-badge" aria-label={discountBadge.aria}>
      <img src="/promo/discount-badge.webp" alt="" className="funnel-discount-badge__img" />
    </a>,
    document.body,
  );
}
