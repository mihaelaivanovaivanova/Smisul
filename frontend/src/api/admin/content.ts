import { apiClient } from '../client';
import type { HomepageContent, HomepageSection } from '../../types/content';

export async function fetchAdminHomepageContent(): Promise<HomepageContent> {
  const { data } = await apiClient.get<{ data: HomepageContent }>('/admin/content/homepage');
  return data.data;
}

export async function updateHomepageSection<K extends HomepageSection>(
  section: K,
  payload: HomepageContent[K],
): Promise<HomepageContent[K]> {
  const { data } = await apiClient.put<{ data: HomepageContent[K] }>(`/admin/content/homepage/${section}`, payload);
  return data.data;
}
