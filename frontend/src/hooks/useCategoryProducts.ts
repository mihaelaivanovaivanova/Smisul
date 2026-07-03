import { fetchCategoryProducts } from '../api/categories';
import { useAsync } from './useAsync';
import type { PaginatedResponse, Product, ProductFilters } from '../types/product';

interface UseCategoryProductsResult {
  products: Product[];
  meta: PaginatedResponse<Product>['meta'] | null;
  isLoading: boolean;
  error: string | null;
}

export function useCategoryProducts(slug: string, filters: Omit<ProductFilters, 'category'> = {}): UseCategoryProductsResult {
  const { data, isLoading, error } = useAsync(
    () => fetchCategoryProducts(slug, filters),
    [slug, JSON.stringify(filters)],
    'Unable to load products for this category.',
  );

  return {
    products: data?.data ?? [],
    meta: data?.meta ?? null,
    isLoading,
    error,
  };
}
