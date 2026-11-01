import { getVariantImage } from './productCatalog';
import type { BundleComponent } from '../types/bundle';
import type { CartItem } from '../types/cart';
import type { Media } from '../types/product';

export interface CartItemDisplay {
  /** Headline name - the product's name for an ordinary line, the kit's own name for a kit line. */
  name: string;
  /** Secondary label under the name - the variant/pack-size name, or the kit's contents summary. Null if there's nothing to add. */
  detail: string | null;
  image: Media | undefined;
  /** The line's own product page - /products/{slug} for an ordinary line's product or a kit's own slug. */
  href: string | null;
}

/** A kit's components in one line, e.g. "Miswak (5 бр.) + Калъф (2 бр.)" - see describeBundleComponent. */
export function summarizeBundleContents(items: BundleComponent[]): string {
  return items.map(describeBundleComponent).join(' + ');
}

/**
 * One component as "Name (N бр.)" - N is the real total piece count (the
 * kit's own quantity of this component x that variant's own pack size), not
 * just the kit's quantity or just the variant's own pack-size label alone.
 * E.g. the 2-Person Kit's two single-piece scrapers (component.quantity=2,
 * pack_size=1) read "Стъргалка за език (2 бр.)", and its one 10-piece
 * Miswak pack (component.quantity=1, pack_size=10) reads "Miswak (10 бр.)".
 */
export function describeBundleComponent(component: BundleComponent): string {
  const name = component.product?.name ?? component.variant_name ?? component.sku ?? '';
  const totalUnits = component.quantity * (component.pack_size ?? 1);
  return `${name} (${totalUnits} бр.)`;
}

/**
 * A compact one-line label for summary lists (checkout summary/review):
 * "Product (variant)" for an ordinary line, just the kit's name for a kit
 * line - its contents summary is too long for these tight rows.
 */
export function getCartItemLabel(item: CartItem): string {
  const { name, detail } = getCartItemDisplay(item);
  return item.bundle || !detail ? name : `${name} (${detail})`;
}

/**
 * What any cart-line UI (drawer, cart page, checkout summary/review) shows
 * for a line, from whichever of product_variant (ordinary line) or bundle
 * (kit line) the backend set - see CartItem. A kit line is one product as
 * far as the UI is concerned; only these display fields differ, the
 * quantity stepper/remove button work exactly the same.
 */
export function getCartItemDisplay(item: CartItem): CartItemDisplay {
  if (item.bundle) {
    const firstWithImage = item.bundle.items.find((component) => component.product?.primary_image);

    return {
      name: item.bundle.name,
      detail: item.bundle.items.length > 0 ? summarizeBundleContents(item.bundle.items) : null,
      image: firstWithImage?.product?.primary_image ?? undefined,
      href: `/products/${item.bundle.slug}`,
    };
  }

  const variant = item.product_variant;
  if (!variant) {
    return { name: '', detail: null, image: undefined, href: null };
  }

  const product = variant.product;
  return {
    name: product?.name ?? variant.name,
    detail: variant.name || null,
    image: getVariantImage(variant),
    href: product?.slug ? `/products/${product.slug}` : null,
  };
}
