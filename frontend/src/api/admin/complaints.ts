import { apiClient } from '../client';
import type { PaginatedResponse } from '../../types/product';
import type { Complaint, ComplaintSort, ComplaintStatus } from '../../types/admin';

export interface ComplaintFilters {
  status?: ComplaintStatus;
  search?: string;
  sort?: ComplaintSort;
  page?: number;
}

export async function fetchComplaints(filters: ComplaintFilters): Promise<PaginatedResponse<Complaint>> {
  const { data } = await apiClient.get<PaginatedResponse<Complaint>>('/admin/complaints', { params: filters });
  return data;
}

export async function logComplaint(orderNumber: string, description: string): Promise<Complaint> {
  const { data } = await apiClient.post<{ data: Complaint }>('/admin/complaints', { order_number: orderNumber, description });
  return data.data;
}

export async function updateComplaint(id: number, status: ComplaintStatus, resolution?: string): Promise<Complaint> {
  const { data } = await apiClient.patch<{ data: Complaint }>(`/admin/complaints/${id}`, { status, resolution });
  return data.data;
}
