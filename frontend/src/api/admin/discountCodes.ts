import { apiClient } from '../client';
import type { AdminDiscountCode } from '../../types/admin';
import type { PaginatedResponse } from '../../types/product';

export interface DiscountCodePayload {
  code: string;
  discount_percent: number;
  max_uses: number;
  /** Plain Y-m-d - interpreted on the backend as a full day in the store's own local time (Europe/Sofia), not UTC. Empty/omitted = no lower/upper bound. */
  starts_at?: string | null;
  expires_at?: string | null;
  is_active?: boolean;
}

export async function fetchAdminDiscountCodes(page = 1): Promise<PaginatedResponse<AdminDiscountCode>> {
  const { data } = await apiClient.get<PaginatedResponse<AdminDiscountCode>>('/admin/discount-codes', { params: { page } });
  return data;
}

export async function createDiscountCode(payload: DiscountCodePayload): Promise<AdminDiscountCode> {
  const { data } = await apiClient.post<{ data: AdminDiscountCode }>('/admin/discount-codes', payload);
  return data.data;
}

export async function updateDiscountCode(id: number, payload: DiscountCodePayload): Promise<AdminDiscountCode> {
  const { data } = await apiClient.put<{ data: AdminDiscountCode }>(`/admin/discount-codes/${id}`, payload);
  return data.data;
}

export async function deleteDiscountCode(id: number): Promise<void> {
  await apiClient.delete(`/admin/discount-codes/${id}`);
}
