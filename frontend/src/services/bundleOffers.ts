import { fetchBundles } from '../api/bundles';
import { describeBundleComponent } from './cartItemDisplay';
import { KIT_TAGLINES } from '../content/kitProducts';
import type { Bundle } from '../types/bundle';
import type { Currency, Media } from '../types/product';

/** A kit shaped for BundleRadioSelector - sibling to funnelOffers.ts's PackageOffer. */
export interface BundleOffer {
  bundle: Bundle;
  price: number;
  currency: Currency;
  /** The components' live separate-purchase total, only when it's actually higher than the kit price (never a fabricated anchor). */
  retailValue: number | null;
  /** retailValue - price, or null under the same condition. */
  savings: number | null;
  /** Round(savings / retailValue * 100), same rounding as funnelOffers.ts's computeSavingsPercent - null under the same condition as savings. */
  savingsPercent: number | null;
  /** One-line tagline (see content/kitProducts.ts's KIT_TAGLINES) - null for a kit not in that lookup. */
  description: string | null;
  /** "2 x Name (variant)" per component, in the kit's own order. */
  contents: string[];
  /** A component photo to represent the kit - the first component that has one. */
  image: Media | null;
  isAvailable: boolean;
}

export function toBundleOffer(bundle: Bundle): BundleOffer {
  const hasRealSaving = bundle.retail_value > bundle.amount;

  return {
    bundle,
    price: bundle.amount,
    currency: bundle.currency,
    retailValue: hasRealSaving ? bundle.retail_value : null,
    savings: hasRealSaving ? Math.round((bundle.retail_value - bundle.amount) * 100) / 100 : null,
    savingsPercent: hasRealSaving ? Math.round((1 - bundle.amount / bundle.retail_value) * 100) : null,
    description: KIT_TAGLINES[bundle.slug] ?? null,
    contents: bundle.items.map(describeBundleComponent),
    image: bundle.items.find((component) => component.product?.primary_image)?.product?.primary_image ?? null,
    isAvailable: bundle.is_available,
  };
}

/**
 * Every active kit for the landing page's "Complete Kits" view (GET
 * /v1/bundles), shaped for display. An out-of-stock kit stays in the list
 * (isAvailable false) so the layout doesn't jump; one with a deactivated
 * component is already left out by the backend.
 */
export async function fetchBundleOffers(): Promise<BundleOffer[]> {
  const bundles = await fetchBundles();
  return bundles.map(toBundleOffer);
}
