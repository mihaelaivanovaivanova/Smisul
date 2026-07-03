import { fetchProducts } from '../api/products';
import { useAsync } from './useAsync';
import type { PaginatedResponse, Product, ProductFilters } from '../types/product';

interface UseProductsResult {
  products: Product[];
  meta: PaginatedResponse<Product>['meta'] | null;
  isLoading: boolean;
  error: string | null;
}

export function useProducts(filters: ProductFilters = {}): UseProductsResult {
  const { data, isLoading, error } = useAsync(
    () => fetchProducts(filters),
    [JSON.stringify(filters)],
    'Unable to load products.',
  );

  return {
    products: data?.data ?? [],
    meta: data?.meta ?? null,
    isLoading,
    error,
  };
}
