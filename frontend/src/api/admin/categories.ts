import { apiClient } from '../client';
import type { Category } from '../../types/product';

export interface CategorySeoPayload {
  meta_title?: string | null;
  meta_description?: string | null;
  meta_keywords?: string | null;
  og_title?: string | null;
  og_description?: string | null;
  og_image_path?: string | null;
  canonical_url?: string | null;
}

export interface CategoryPayload {
  parent_id?: number | null;
  name: string;
  description?: string | null;
  is_active?: boolean;
  sort_order?: number;
  /** Omitting this key entirely (not sending empty strings) leaves any existing SEO data untouched - see CategoryService::syncSeo(). */
  seo?: CategorySeoPayload;
}

export async function fetchAdminCategories(): Promise<Category[]> {
  const { data } = await apiClient.get<{ data: Category[] }>('/admin/categories');
  return data.data;
}

export async function createCategory(payload: CategoryPayload): Promise<Category> {
  const { data } = await apiClient.post<{ data: Category }>('/admin/categories', payload);
  return data.data;
}

export async function updateCategory(id: number, payload: CategoryPayload): Promise<Category> {
  const { data } = await apiClient.put<{ data: Category }>(`/admin/categories/${id}`, payload);
  return data.data;
}

export async function deleteCategory(id: number): Promise<void> {
  await apiClient.delete(`/admin/categories/${id}`);
}
