import type { Currency, Media, Price, Product, ProductVariant } from '../types/product';

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

/** The primary product image, or the first image as a fallback. */
export function getPrimaryImage(product: Product): Media | undefined {
  return product.media.find((media) => media.is_primary) ?? product.media[0];
}
