import { apiClient } from '../client';
import type { Media, PaginatedResponse, ProductFilters, ProductStatus } from '../../types/product';
import type { AdminProduct } from '../../types/admin';

export interface ProductPayload {
  name: string;
  short_description?: string | null;
  description?: string | null;
  status?: ProductStatus;
  category_ids?: number[];
  /** Applied to the product's default variant (created automatically if it doesn't have one yet). */
  quantity?: number | null;
  price?: number | null;
}

export async function fetchAdminProducts(filters: ProductFilters): Promise<PaginatedResponse<AdminProduct>> {
  const { data } = await apiClient.get<PaginatedResponse<AdminProduct>>('/admin/products', { params: filters });
  return data;
}

export async function fetchAdminProduct(id: number): Promise<AdminProduct> {
  const { data } = await apiClient.get<{ data: AdminProduct }>(`/admin/products/${id}`);
  return data.data;
}

export async function createProduct(payload: ProductPayload): Promise<AdminProduct> {
  const { data } = await apiClient.post<{ data: AdminProduct }>('/admin/products', payload);
  return data.data;
}

export async function updateProduct(id: number, payload: ProductPayload): Promise<AdminProduct> {
  const { data } = await apiClient.put<{ data: AdminProduct }>(`/admin/products/${id}`, payload);
  return data.data;
}

export async function deleteProduct(id: number): Promise<void> {
  await apiClient.delete(`/admin/products/${id}`);
}

export async function uploadProductMedia(productId: number, file: File, isPrimary = false): Promise<Media> {
  const form = new FormData();
  form.append('file', file);
  if (isPrimary) {
    form.append('is_primary', '1');
  }

  const { data } = await apiClient.post<{ data: Media }>(`/admin/products/${productId}/media`, form);
  return data.data;
}

export async function makeProductMediaPrimary(productId: number, mediaId: number): Promise<Media> {
  const { data } = await apiClient.patch<{ data: Media }>(`/admin/products/${productId}/media/${mediaId}/primary`);
  return data.data;
}

export async function deleteProductMedia(productId: number, mediaId: number): Promise<void> {
  await apiClient.delete(`/admin/products/${productId}/media/${mediaId}`);
}
