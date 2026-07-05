import { apiClient } from './client';
import type { HomepageContent } from '../types/content';

/** Public, unauthenticated — used by the storefront homepage. */
export async function fetchHomepageContent(): Promise<HomepageContent> {
  const { data } = await apiClient.get<{ data: HomepageContent }>('/content/homepage');
  return data.data;
}
