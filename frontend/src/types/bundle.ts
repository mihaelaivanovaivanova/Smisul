import type { Currency, Media, ProductSummary, VariantStatus } from './product';

/** One component of a kit, resolved against its live variant - see backend's BundleResource. */
export interface BundleComponent {
  product_variant_id: number;
  /** How many of this variant one kit contains. */
  quantity: number;
  sku: string | null;
  variant_name: string | null;
  pack_size: number | null;
  /** This variant's own live single-purchase price - null if it has none in the kit's currency. */
  unit_price: number | null;
  product: ProductSummary | null;
}

/**
 * A kit (1-Person Kit, 2-Person Kit, ...) as the backend's BundleResource
 * returns it - GET /v1/bundles, the admin bundle endpoints, and nested as
 * `bundle` on a kit cart line (see CartItem). A kit is ONE product: one
 * stored price, one cart/order line, one quantity.
 */
export interface Bundle {
  id: number;
  slug: string;
  name: string;
  /** e.g. "НАЙ-ПОПУЛЯРЕН" / "НАЙ-ИЗГОДЕН" - the same optional badge FunnelConfig's own packages carry (see PackageRadioSelector). Null when unset. */
  badge: string | null;
  currency: Currency;
  /** The kit's single stored price. */
  amount: number;
  /** Live sum of the components' own separate-purchase prices x quantities - never a stored/fabricated anchor. */
  retail_value: number;
  /** retail_value - amount when that's positive, otherwise 0. */
  savings: number;
  status: VariantStatus;
  sort_order: number;
  /** False when any component can't currently cover even one kit. */
  is_available: boolean;
  /** The kit's own lightweight catalog "shell" product variant (see backend's KitShellProductService) - what a kit order line's own product_variant_id points at, purely so the review system can treat a kit purchase like any other product purchase. Null only for a kit that predates this (shouldn't happen after the upgrade catch-up runs). */
  shell_product_variant_id: number | null;
  /** The shell product's own gallery - admin-managed via /admin/products (ProductMediaManager), same as any other product's photos. Empty until an admin uploads some. */
  gallery: Media[];
  items: BundleComponent[];
}
