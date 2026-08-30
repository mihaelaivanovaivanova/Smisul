import { getVariantPrice } from './productCatalog';
import type { FunnelPackage } from '../types/funnel';
import type { Price, Product, ProductVariant } from '../types/product';

export interface PackageOffer {
  pkg: FunnelPackage;
  variant: ProductVariant;
  price: Price;
}

/**
 * Resolves the admin-configured funnel packages against the live product:
 * each package must point at an existing variant with a price, otherwise
 * it's dropped (a stale variant_id shouldn't break the buy section — the
 * admin UI is where stale picks are surfaced, see FunnelAdminPayload).
 * Lives here rather than in PackageOffers.tsx so FunnelLandingPage can
 * check for an empty result and fall back to its single default-variant
 * buy button.
 */
export function resolvePackageOffers(product: Product, packages: FunnelPackage[]): PackageOffer[] {
  return packages.flatMap((pkg) => {
    const variant = product.variants.find((candidate) => candidate.id === pkg.variant_id);
    const price = variant ? getVariantPrice(variant) : undefined;

    return variant && price ? [{ pkg, variant, price }] : [];
  });
}

/**
 * The real, non-fabricated savings badge every package card shows (funnel
 * PackageOffers.tsx and the product page's VariantPicker.tsx both use
 * this) — computed against the live 1-pack price from the same offers
 * list, not a hardcoded/compare-at anchor. See PackageOffers.tsx's own
 * comment for why compare_at_amount was rejected as the source for this.
 */
export function computeSavingsPercent(offers: PackageOffer[], variant: ProductVariant, price: Price): number | null {
  const singleStickPrice = offers.find(({ variant: candidate }) => candidate.pack_size === 1)?.price.amount;

  return singleStickPrice && variant.pack_size > 1
    ? Math.round((1 - price.amount / variant.pack_size / singleStickPrice) * 100)
    : null;
}
