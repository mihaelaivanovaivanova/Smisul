import { createContext } from 'react';
import type { Favorite } from '../types/favorite';

export interface FavoritesContextValue {
  favorites: Favorite[];
  /** Variant ids currently favorited — for O(1) lookups from FavoriteButton across a product grid. */
  favoriteVariantIds: Set<number>;
  isLoading: boolean;
  error: string | null;
  toggleFavorite: (productVariantId: number) => Promise<void>;
  refresh: () => Promise<void>;
}

export const FavoritesContext = createContext<FavoritesContextValue | undefined>(undefined);
