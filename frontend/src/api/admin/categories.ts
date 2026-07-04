import { apiClient } from '../client';
import type { Category } from '../../types/product';

export interface CategoryPayload {
  parent_id?: number | null;
  name: string;
  description?: string | null;
  is_active?: boolean;
  sort_order?: number;
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
