import { useCallback, useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import * as favoritesApi from '../api/favorites';
import { getErrorMessage } from '../api/errors';
import { useAuth } from '../hooks/useAuth';
import { FavoritesContext } from './favorites-context';
import type { FavoritesContextValue } from './favorites-context';
import type { Favorite } from '../types/favorite';
import { favorites as favoritesCopy } from '../content/copy';

/**
 * Loads the customer's full favorites list once (skipped entirely for
 * guests/admins, who'd just get a 401/403) so FavoriteButton can do an
 * O(1) membership check across a whole product grid instead of one
 * "is this favorited" request per card.
 */
export function FavoritesProvider({ children }: { children: ReactNode }) {
  const { isAuthenticated, isLoading: isAuthLoading } = useAuth();
  const [favorites, setFavorites] = useState<Favorite[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (isAuthLoading) {
      return;
    }

    if (!isAuthenticated) {
      setFavorites([]);
      setIsLoading(false);
      return;
    }

    let isMounted = true;
    setIsLoading(true);
    setError(null);

    favoritesApi
      .fetchFavorites()
      .then((current) => {
        if (isMounted) {
          setFavorites(current);
        }
      })
      .catch((err: unknown) => {
        if (isMounted) {
          setError(getErrorMessage(err, favoritesCopy.loadError));
        }
      })
      .finally(() => {
        if (isMounted) {
          setIsLoading(false);
        }
      });

    return () => {
      isMounted = false;
    };
  }, [isAuthenticated, isAuthLoading]);

  const refresh = useCallback(async () => {
    const current = await favoritesApi.fetchFavorites();
    setFavorites(current);
  }, []);

  const toggleFavorite = useCallback(
    async (productVariantId: number) => {
      const existing = favorites.find((favorite) => favorite.product_variant.id === productVariantId);

      if (existing) {
        await favoritesApi.removeFavorite(existing.id);
        setFavorites((prev) => prev.filter((favorite) => favorite.id !== existing.id));
      } else {
        const created = await favoritesApi.addFavorite(productVariantId);
        setFavorites((prev) => [...prev, created]);
      }
    },
    [favorites],
  );

  const favoriteVariantIds = useMemo(
    () => new Set(favorites.map((favorite) => favorite.product_variant.id)),
    [favorites],
  );

  const value: FavoritesContextValue = { favorites, favoriteVariantIds, isLoading, error, toggleFavorite, refresh };

  return <FavoritesContext.Provider value={value}>{children}</FavoritesContext.Provider>;
}
