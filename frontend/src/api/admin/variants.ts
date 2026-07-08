import { apiClient } from '../client';
import type { Inventory, Price, ProductVariant, VariantStatus } from '../../types/product';

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
