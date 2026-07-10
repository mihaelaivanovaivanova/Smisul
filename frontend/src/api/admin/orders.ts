import { apiClient } from '../client';
import type { AdminOrder } from '../../types/admin';
import type { Payment } from '../../types/payment';
import type { PaginatedResponse } from '../../types/product';

export interface AdminOrderFilters {
  search?: string;
  user_id?: number;
  status?: string;
  date_from?: string;
  date_to?: string;
  sort?: 'newest' | 'oldest' | 'total_asc' | 'total_desc';
  page?: number;
}

export async function fetchAdminOrders(filters: AdminOrderFilters): Promise<PaginatedResponse<AdminOrder>> {
  const { data } = await apiClient.get<PaginatedResponse<AdminOrder>>('/admin/orders', { params: filters });
  return data;
}

export async function fetchAdminOrder(id: number): Promise<AdminOrder> {
  const { data } = await apiClient.get<{ data: AdminOrder }>(`/admin/orders/${id}`);
  return data.data;
}

export async function updateOrderStatus(id: number, status: string, note?: string): Promise<AdminOrder> {
  const { data } = await apiClient.patch<{ data: AdminOrder }>(`/admin/orders/${id}/status`, { status, note });
  return data.data;
}

export async function reversePayment(paymentId: number): Promise<Payment> {
  const { data } = await apiClient.post<{ data: Payment }>(`/admin/payments/${paymentId}/reverse`);
  return data.data;
}

export async function refundPayment(paymentId: number, amount: number): Promise<Payment> {
  const { data } = await apiClient.post<{ data: Payment }>(`/admin/payments/${paymentId}/refund`, { amount });
  return data.data;
}
