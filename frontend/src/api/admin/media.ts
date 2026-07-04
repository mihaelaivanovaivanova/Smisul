import { apiClient } from '../client';
import type { MediaFilters, MediaItem } from '../../types/admin';
import type { PaginatedResponse } from '../../types/product';

export async function fetchAdminMedia(filters: MediaFilters): Promise<PaginatedResponse<MediaItem>> {
  const { data } = await apiClient.get<PaginatedResponse<MediaItem>>('/admin/media', { params: filters });
  return data;
}

export async function replaceMedia(id: number, file: File, altText?: string): Promise<MediaItem> {
  const form = new FormData();
  form.append('file', file);
  if (altText) {
    form.append('alt_text', altText);
  }

  const { data } = await apiClient.post<{ data: MediaItem }>(`/admin/media/${id}`, form);
  return data.data;
}

export async function deleteMedia(id: number): Promise<void> {
  await apiClient.delete(`/admin/media/${id}`);
}
