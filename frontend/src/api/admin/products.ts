import { apiClient } from '../client';
import type { PaginatedResponse, Product, ProductFilters, ProductStatus } from '../../types/product';

export interface ProductPayload {
  name: string;
  short_description?: string | null;
  description?: string | null;
  status?: ProductStatus;
  category_ids?: number[];
}

export async function fetchAdminProducts(filters: ProductFilters): Promise<PaginatedResponse<Product>> {
  const { data } = await apiClient.get<PaginatedResponse<Product>>('/admin/products', { params: filters });
  return data;
}

export async function fetchAdminProduct(id: number): Promise<Product> {
  const { data } = await apiClient.get<{ data: Product }>(`/admin/products/${id}`);
  return data.data;
}

export async function createProduct(payload: ProductPayload): Promise<Product> {
  const { data } = await apiClient.post<{ data: Product }>('/admin/products', payload);
  return data.data;
}

export async function updateProduct(id: number, payload: ProductPayload): Promise<Product> {
  const { data } = await apiClient.put<{ data: Product }>(`/admin/products/${id}`, payload);
  return data.data;
}

export async function deleteProduct(id: number): Promise<void> {
  await apiClient.delete(`/admin/products/${id}`);
}
