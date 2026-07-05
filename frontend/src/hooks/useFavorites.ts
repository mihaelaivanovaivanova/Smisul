import { useContext } from 'react';
import { FavoritesContext } from '../context/favorites-context';
import type { FavoritesContextValue } from '../context/favorites-context';

export function useFavorites(): FavoritesContextValue {
  const context = useContext(FavoritesContext);

  if (!context) {
    throw new Error('useFavorites must be used within a FavoritesProvider');
  }

  return context;
}
