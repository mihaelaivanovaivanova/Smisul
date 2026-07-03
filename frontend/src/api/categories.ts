import { apiClient } from './client';
import type { ApiResource, Category, PaginatedResponse, Product, ProductFilters } from '../types/product';

export async function fetchCategories(): Promise<Category[]> {
  const { data } = await apiClient.get<{ data: Category[] }>('/categories');
  return data.data;
}

export async function fetchCategory(slug: string): Promise<Category> {
  const { data } = await apiClient.get<ApiResource<Category>>(`/categories/${slug}`);
  return data.data;
}

export async function fetchCategoryProducts(
  slug: string,
  filters: Omit<ProductFilters, 'category'> = {},
): Promise<PaginatedResponse<Product>> {
  const { data } = await apiClient.get<PaginatedResponse<Product>>(`/categories/${slug}/products`, {
    params: filters,
  });
  return data;
}
