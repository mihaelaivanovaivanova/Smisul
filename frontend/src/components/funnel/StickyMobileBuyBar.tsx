import { funnelOffer } from '../../content/copy';

interface StickyMobileBuyBarProps {
  visible: boolean;
  fromPriceLabel: string | null;
}

/**
 * Persistent mobile buy bar — not one of the page's 20 numbered sections
 * (it's always-mounted chrome, not a scroll-order section). Visibility is
 * a three-zone toggle driven by FunnelLandingPage's IntersectionObserver
 * (hero / pricing / faq-and-below), not the old always-on bar: hidden
 * while the Hero's own primary CTA is on screen or while the pricing
 * cards (which carry their own CTAs) are in view, and hidden for good
 * once FAQ/newsletter/footer are reached so it never sits over their
 * controls. Conditionally rendering (rather than hiding via CSS) keeps
 * this consistent with StickyDesktopBuyBar and avoids any layout shift —
 * the bar is `position: fixed`, so mounting/unmounting it doesn't affect
 * document flow; the reserved bottom padding on <body> is constant for
 * the whole page regardless of this toggle.
 */
export default function StickyMobileBuyBar({ visible, fromPriceLabel }: StickyMobileBuyBarProps) {
  if (!visible || !fromPriceLabel) {
    return null;
  }

  return (
    <div className="funnel-sticky-bar d-md-none">
      <a href="#pricing" className="funnel-sticky-bar__cta">
        <span className="funnel-sticky-bar__label">{funnelOffer.mobileCtaLabel}</span>
        <span className="funnel-sticky-bar__arrow" aria-hidden="true">
          →
        </span>
        <span className="funnel-sticky-bar__price">{fromPriceLabel}</span>
      </a>
    </div>
  );
}
