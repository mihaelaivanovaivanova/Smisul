import { fetchCategory } from '../api/categories';
import { useAsync } from './useAsync';
import type { Category } from '../types/product';

interface UseCategoryResult {
  category: Category | null;
  isLoading: boolean;
  error: string | null;
}

export function useCategory(slug: string): UseCategoryResult {
  const { data, isLoading, error } = useAsync(() => fetchCategory(slug), [slug], 'Unable to load this category.');

  return { category: data, isLoading, error };
}
