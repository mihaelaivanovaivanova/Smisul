import { apiClient } from '../client';
import type { PaginatedResponse, Promotion, PromotionType } from '../../types/product';

export interface PromotionPayload {
  name: string;
  description?: string | null;
  type: PromotionType;
  value: number;
  code?: string | null;
  starts_at?: string | null;
  ends_at?: string | null;
  usage_limit?: number | null;
  is_active?: boolean;
  product_ids?: number[];
  category_ids?: number[];
}

export async function fetchAdminPromotions(page = 1): Promise<PaginatedResponse<Promotion>> {
  const { data } = await apiClient.get<PaginatedResponse<Promotion>>('/admin/promotions', { params: { page } });
  return data;
}

export async function createPromotion(payload: PromotionPayload): Promise<Promotion> {
  const { data } = await apiClient.post<{ data: Promotion }>('/admin/promotions', payload);
  return data.data;
}

export async function updatePromotion(id: number, payload: PromotionPayload): Promise<Promotion> {
  const { data } = await apiClient.put<{ data: Promotion }>(`/admin/promotions/${id}`, payload);
  return data.data;
}

export async function deletePromotion(id: number): Promise<void> {
  await apiClient.delete(`/admin/promotions/${id}`);
}
