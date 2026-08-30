import type { Currency, Media, Price, Product, ProductVariant, Promotion } from '../types/product';

const CURRENCY_SYMBOLS: Record<Currency, string> = {
  EUR: '€',
};

/**
 * Dual EUR/BGN price labelling per Закона за въвеждане на еврото — the
 * obligation ran 8 Aug 2025 – 8 Aug 2026 (fixed rate 1 EUR = 1.95583
 * лв.) and has now ended, so this stays off. Flip back to true only if
 * a legal or business reason to show BGN again comes up.
 */
export const DUAL_PRICE_BGN = false;
const BGN_PER_EUR = 1.95583;

/**
 * Formats a raw amount as a display string, e.g. formatPrice(19.99, 'EUR')
 * -> "19.99 € (39.10 лв.)" while dual labelling is on, "19.99 €" after.
 */
export function formatPrice(amount: number, currency: Currency = 'EUR'): string {
  const primary = `${amount.toFixed(2)} ${CURRENCY_SYMBOLS[currency]}`;

  if (!DUAL_PRICE_BGN || currency !== 'EUR') {
    return primary;
  }

  return `${primary} (${(amount * BGN_PER_EUR).toFixed(2)} лв.)`;
}

/** The variant to preselect: the one flagged default, or the first available as a fallback. */
export function getDefaultVariant(product: Product): ProductVariant | undefined {
  return product.variants.find((variant) => variant.is_default) ?? product.variants[0];
}

export function getVariantPrice(variant: ProductVariant, currency: Currency = 'EUR'): Price | undefined {
  return variant.prices.find((price) => price.currency === currency);
}

/** The price shown on a product card/listing: the default variant's price. */
export function getDisplayPrice(product: Product, currency: Currency = 'EUR'): Price | undefined {
  const variant = getDefaultVariant(product);
  return variant ? getVariantPrice(variant, currency) : undefined;
}

export function isProductInStock(product: Product): boolean {
  return product.variants.some((variant) => variant.inventory?.is_in_stock ?? false);
}

export function sortVariantsByPackSize(variants: ProductVariant[]): ProductVariant[] {
  return [...variants].sort((a, b) => a.pack_size - b.pack_size);
}

function sortMedia(media: Media[]): Media[] {
  return [...media].sort((a, b) => a.sort_order - b.sort_order);
}

/**
 * Image media from a raw media array, primary image first. Media with no
 * mime_type on record (older/seed data predating that field) is treated as
 * an image rather than hidden, so existing catalog entries still render.
 */
function imagesFromMedia(media: Media[]): Media[] {
  const images = sortMedia(media.filter((item) => item.mime_type?.startsWith('image/') ?? true));
  const primaryIndex = images.findIndex((image) => image.is_primary);

  if (primaryIndex <= 0) {
    return images;
  }

  const [primary] = images.splice(primaryIndex, 1);
  return [primary, ...images];
}

/** A product's own gallery images, primary image first. */
export function getGalleryImages(product: Product): Media[] {
  return imagesFromMedia(product.media);
}

/**
 * The gallery to show for a specific selected pack size: that variant's own
 * photo(s) if it has any (e.g. a real packaging shot per pack count — see
 * FunnelSeeder's per-variant seedVariantImage calls), otherwise the
 * product's own gallery as a fallback for pack sizes without a dedicated
 * photo yet.
 */
export function getGalleryImagesForVariant(product: Product, variant: ProductVariant | null | undefined): Media[] {
  const variantImages = variant?.media ? imagesFromMedia(variant.media) : [];
  return variantImages.length > 0 ? variantImages : getGalleryImages(product);
}

/** The primary product image, or the first gallery image as a fallback. */
export function getPrimaryImage(product: Product): Media | undefined {
  return getGalleryImages(product)[0];
}

/**
 * The photo to show for a variant referenced outside a full Product context
 * (cart lines, mini-cart, favorites) — its own pack-size photo if it has
 * one, otherwise the parent product's primary image (product.primary_image
 * on the slim ProductSummary these contexts nest under `variant.product`).
 */
export function getVariantImage(variant: ProductVariant): Media | undefined {
  const variantImages = variant.media ? imagesFromMedia(variant.media) : [];
  return variantImages[0] ?? variant.product?.primary_image ?? undefined;
}

export function getVideos(product: Product): Media[] {
  return sortMedia(product.media.filter((media) => media.mime_type?.startsWith('video/') ?? false));
}

export function getDownloads(product: Product): Media[] {
  return sortMedia(product.media.filter((media) => media.mime_type === 'application/pdf'));
}

/** The first currently-valid promotion applying to this product, if any — for badge display. */
export function getActivePromotion(product: Product): Promotion | undefined {
  return product.active_promotions?.[0];
}

/** Display label for a promotion's discount, e.g. "-20%" or "-5.00 €" */
export function formatPromotionValue(promotion: Promotion): string {
  return promotion.type === 'percentage' ? `-${promotion.value}%` : `-${formatPrice(promotion.value)}`;
}
