import type { Category } from '../types/product';

/** Depth-first flatten of a category tree, keeping each node's nesting depth for indentation. */
export function flattenCategories(categories: Category[], depth = 0): { category: Category; depth: number }[] {
  return categories.flatMap((category) => [{ category, depth }, ...flattenCategories(category.children, depth + 1)]);
}
