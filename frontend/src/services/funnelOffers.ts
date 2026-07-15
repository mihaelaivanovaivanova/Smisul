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
