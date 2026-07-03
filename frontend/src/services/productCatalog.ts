import type { Currency, Media, Price, Product, ProductVariant, Promotion } from '../types/product';

const CURRENCY_SYMBOLS: Record<Currency, string> = {
  BGN: 'лв.',
};

/** Formats a raw amount as a display string, e.g. formatPrice(19.99, 'BGN') -> "19.99 лв." */
export function formatPrice(amount: number, currency: Currency = 'BGN'): string {
  return `${amount.toFixed(2)} ${CURRENCY_SYMBOLS[currency]}`;
}

/** The variant to preselect: the one flagged default, or the first available as a fallback. */
export function getDefaultVariant(product: Product): ProductVariant | undefined {
  return product.variants.find((variant) => variant.is_default) ?? product.variants[0];
}

export function getVariantPrice(variant: ProductVariant, currency: Currency = 'BGN'): Price | undefined {
  return variant.prices.find((price) => price.currency === currency);
}

/** The price shown on a product card/listing: the default variant's price. */
export function getDisplayPrice(product: Product, currency: Currency = 'BGN'): Price | undefined {
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
 * Image media for a product's gallery, primary image first. Media with no
 * mime_type on record (older/seed data predating that field) is treated as
 * an image rather than hidden, so existing catalog entries still render.
 */
export function getGalleryImages(product: Product): Media[] {
  const images = sortMedia(product.media.filter((media) => media.mime_type?.startsWith('image/') ?? true));
  const primaryIndex = images.findIndex((image) => image.is_primary);

  if (primaryIndex <= 0) {
    return images;
  }

  const [primary] = images.splice(primaryIndex, 1);
  return [primary, ...images];
}

/** The primary product image, or the first gallery image as a fallback. */
export function getPrimaryImage(product: Product): Media | undefined {
  return getGalleryImages(product)[0];
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

/** Display label for a promotion's discount, e.g. "-20%" or "-5.00 лв." */
export function formatPromotionValue(promotion: Promotion): string {
  return promotion.type === 'percentage' ? `-${promotion.value}%` : `-${formatPrice(promotion.value)}`;
}
