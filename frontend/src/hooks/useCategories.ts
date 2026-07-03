import { fetchCategories } from '../api/categories';
import { useAsync } from './useAsync';
import type { Category } from '../types/product';

interface UseCategoriesResult {
  categories: Category[];
  isLoading: boolean;
  error: string | null;
}

export function useCategories(): UseCategoriesResult {
  const { data, isLoading, error } = useAsync(() => fetchCategories(), [], 'Unable to load categories.');

  return { categories: data ?? [], isLoading, error };
}
