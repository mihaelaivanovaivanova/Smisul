import { fetchProduct } from '../api/products';
import { useAsync } from './useAsync';
import type { Product } from '../types/product';

interface UseProductResult {
  product: Product | null;
  isLoading: boolean;
  error: string | null;
}

export function useProduct(slug: string): UseProductResult {
  const { data, isLoading, error } = useAsync(() => fetchProduct(slug), [slug], 'Unable to load this product.');

  return { product: data, isLoading, error };
}
