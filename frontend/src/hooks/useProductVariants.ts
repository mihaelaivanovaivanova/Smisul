import { fetchProductVariants } from '../api/products';
import { useAsync } from './useAsync';
import type { ProductVariant } from '../types/product';

interface UseProductVariantsResult {
  variants: ProductVariant[];
  isLoading: boolean;
  error: string | null;
}

export function useProductVariants(slug: string): UseProductVariantsResult {
  const { data, isLoading, error } = useAsync(
    () => fetchProductVariants(slug),
    [slug],
    'Unable to load product variants.',
  );

  return { variants: data ?? [], isLoading, error };
}
