import { apiClient } from '../client';
import type { Inventory, Media, Price, ProductVariant, VariantStatus } from '../../types/product';

export interface VariantPayload {
  sku: string;
  name: string;
  pack_size: number;
  barcode?: string | null;
  is_default?: boolean;
  status?: VariantStatus;
}

export interface VariantPricePayload {
  currency: 'EUR';
  amount: number;
  compare_at_amount?: number | null;
}

export interface VariantInventoryPayload {
  quantity_on_hand: number;
  backorders_allowed?: boolean;
}

export async function createVariant(productId: number, payload: VariantPayload): Promise<ProductVariant> {
  const { data } = await apiClient.post<{ data: ProductVariant }>(`/admin/products/${productId}/variants`, payload);
  return data.data;
}

export async function updateVariant(productId: number, variantId: number, payload: VariantPayload): Promise<ProductVariant> {
  const { data } = await apiClient.put<{ data: ProductVariant }>(`/admin/products/${productId}/variants/${variantId}`, payload);
  return data.data;
}

export async function deleteVariant(productId: number, variantId: number): Promise<void> {
  await apiClient.delete(`/admin/products/${productId}/variants/${variantId}`);
}

export async function updateVariantPrice(productId: number, variantId: number, payload: VariantPricePayload): Promise<Price> {
  const { data } = await apiClient.put<{ data: Price }>(`/admin/products/${productId}/variants/${variantId}/price`, payload);
  return data.data;
}

export async function updateVariantInventory(
  productId: number,
  variantId: number,
  payload: VariantInventoryPayload,
): Promise<Inventory> {
  const { data } = await apiClient.put<{ data: Inventory }>(`/admin/products/${productId}/variants/${variantId}/inventory`, payload);
  return data.data;
}

/** The pack-size-specific "view photo" shown when this variant is selected — replaces any existing one in place (see the backend's attachOrReplaceVariantPhoto). */
export async function uploadVariantPhoto(productId: number, variantId: number, file: File): Promise<Media> {
  const form = new FormData();
  form.append('file', file);

  const { data } = await apiClient.post<{ data: Media }>(`/admin/products/${productId}/variants/${variantId}/media`, form);
  return data.data;
}

/** Clears the variant's own photo — the storefront falls back to the product's own gallery for this pack size. */
export async function deleteVariantPhoto(productId: number, variantId: number): Promise<void> {
  await apiClient.delete(`/admin/products/${productId}/variants/${variantId}/media`);
}

/** @param focusX @param focusY Fraction (0-1) of the photo's own width/height to keep centered when it's displayed cropped to fit — see ProductGallery.tsx. */
export async function updateVariantPhotoFocus(
  productId: number,
  variantId: number,
  mediaId: number,
  focusX: number,
  focusY: number,
): Promise<Media> {
  const { data } = await apiClient.patch<{ data: Media }>(
    `/admin/products/${productId}/variants/${variantId}/media/${mediaId}/focus`,
    { focus_x: focusX, focus_y: focusY },
  );
  return data.data;
}
