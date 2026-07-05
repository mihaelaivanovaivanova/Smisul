import { apiClient } from './client';
import type { Favorite } from '../types/favorite';

export async function fetchFavorites(): Promise<Favorite[]> {
  const { data } = await apiClient.get<{ data: Favorite[] }>('/customer/favorites');
  return data.data;
}

export async function addFavorite(productVariantId: number): Promise<Favorite> {
  const { data } = await apiClient.post<{ data: Favorite }>('/customer/favorites', {
    product_variant_id: productVariantId,
  });
  return data.data;
}

export async function removeFavorite(favoriteId: number): Promise<void> {
  await apiClient.delete(`/customer/favorites/${favoriteId}`);
}

export async function checkFavorited(productVariantId: number): Promise<{ is_favorited: boolean; favorite_id: number | null }> {
  const { data } = await apiClient.get<{ data: { is_favorited: boolean; favorite_id: number | null } }>(
    `/customer/favorites/check/${productVariantId}`,
  );
  return data.data;
}

export async function fetchFavoritesCount(): Promise<number> {
  const { data } = await apiClient.get<{ data: { count: number } }>('/customer/favorites/count');
  return data.data.count;
}
