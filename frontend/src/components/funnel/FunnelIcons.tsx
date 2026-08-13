import Icon from '../icons/Icon';
import type { IconName } from '../icons/Icon';

/**
 * Trust-item icons that have a real cropped photo/icon match (see
 * public/funnel/v2) render that instead of the hand-drawn Icon.tsx SVG —
 * closer to the reference layout. Icon names with no match (e.g. the
 * final CTA's truck/lock/check-badge/undo) fall back to Icon.tsx.
 *
 * Relocated from FunnelLandingPage.tsx unchanged as part of the section
 * split — used by HeroSection and DeliveryPaymentReturnsSection.
 */
const TRUST_ICON_IMAGES: Partial<Record<IconName, string>> = {
  leaf: 'icon-natural-100-circle',
  recycle: 'icon-biodegradable-circle',
  'no-plastic': 'icon-no-plastic-circle',
  envelope: 'icon-easy-carry-circle',
};

export function TrustIcon({ icon }: { icon: IconName }) {
  const image = TRUST_ICON_IMAGES[icon];

  if (image) {
    // No loading="lazy": also used in the hero's trust row, above the fold.
    return (
      <span className="funnel-trust-item__icon funnel-trust-item__icon--photo">
        <img src={`/funnel/v2/${image}.webp`} alt="" />
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
