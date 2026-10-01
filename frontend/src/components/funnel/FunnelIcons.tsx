import Icon from '../icons/Icon';
import type { IconName } from '../icons/Icon';

/**
 * Trust-item icons that have a real cropped photo match, or a real custom
 * line-icon replacement (see public/funnel/v2), render that instead of the
 * hand-drawn Icon.tsx SVG — closer to the reference layout. Filename
 * includes its own extension since the two sets mix .webp (photos) and
 * .svg (the delivery/card/cash/quality-guarantee line icons — supplied
 * directly, not a Icon.tsx-style single-path fill, so rendered as a plain
 * img rather than forced into that component). Every current trust item has
 * a match now; the fallback stays in place for any future icon name added
 * without one.
 *
 * Relocated from FunnelLandingPage.tsx unchanged as part of the section
 * split — used by HeroSection and DeliveryPaymentReturnsSection.
 */
const TRUST_ICON_IMAGES: Partial<Record<IconName, string>> = {
  leaf: 'icon-natural-100-circle.webp',
  recycle: 'icon-biodegradable-circle.webp',
  'no-plastic': 'icon-no-plastic-circle.webp',
  envelope: 'icon-easy-carry-circle.webp',
  truck: 'icon-delivery.svg',
  card: 'icon-secure-card-payment.svg',
  cash: 'icon-cash-on-delivery.svg',
  'check-badge': 'icon-quality-guarantee.svg',
  undo: 'icon-returns.svg',
};

export function TrustIcon({ icon }: { icon: IconName }) {
  const image = TRUST_ICON_IMAGES[icon];

  if (image) {
    // The .svg set is a full illustration (lines reach close to the
    // viewBox edges), not a pre-cropped circular photo like the .webp
    // set — --photo's own img rule clips to a circle, which would cut
    // real content off these, so they skip that modifier.
    const isSvg = image.endsWith('.svg');

    // No loading="lazy": also used in the hero's trust row, above the fold.
    return (
      <span className={`funnel-trust-item__icon ${isSvg ? 'funnel-trust-item__icon--svg' : 'funnel-trust-item__icon--photo'}`}>
        <img src={`/funnel/v2/${image}`} alt="" />
      </span>
    );
  }

  return (
    <span className="funnel-trust-item__icon">
      <Icon name={icon} />
    </span>
  );
}

/**
 * Same idea as TRUST_ICON_IMAGES, for CoreBenefitsSection's cards — real
 * cropped, background-removed icon art instead of the hand-drawn Icon.tsx
 * SVG for the 3 icons that have a photo match.
 */
const WHY_ICON_IMAGES: Partial<Record<IconName, string>> = {
  clock: 'icon-why-clock',
  tooth: 'icon-why-tooth',
  globe: 'icon-why-globe',
};

export function WhyIcon({ icon }: { icon: IconName }) {
  const image = WHY_ICON_IMAGES[icon];

  if (image) {
    return (
      <span className="funnel-why-card__icon funnel-why-card__icon--photo">
        <img src={`/funnel/v2/${image}.webp`} alt="" loading="lazy" decoding="async" />
      </span>
    );
  }

  return (
    <span className="funnel-why-card__icon">
      <Icon name={icon} />
    </span>
  );
}
