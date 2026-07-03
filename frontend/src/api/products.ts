import { apiClient } from './client';
import type { ApiResource, PaginatedResponse, Product, ProductFilters, ProductVariant } from '../types/product';

export async function fetchProducts(filters: ProductFilters = {}): Promise<PaginatedResponse<Product>> {
  const { data } = await apiClient.get<PaginatedResponse<Product>>('/products', { params: filters });
  return data;
}

export async function fetchProduct(slug: string): Promise<Product> {
  const { data } = await apiClient.get<ApiResource<Product>>(`/products/${slug}`);
  return data.data;
}

export async function fetchProductVariants(slug: string): Promise<ProductVariant[]> {
  const { data } = await apiClient.get<{ data: ProductVariant[] }>(`/products/${slug}/variants`);
  return data.data;
}
