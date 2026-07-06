import { apiClient } from '../client';
import type { PaginatedResponse } from '../../types/product';
import type { AdminReview, AdminReviewStatistics } from '../../types/admin';

export interface AdminReviewFilters {
  status?: string;
  product_id?: number;
  rating?: number;
  search?: string;
  sort?: 'newest' | 'oldest' | 'highest' | 'lowest' | 'helpful';
  page?: number;
}

export async function fetchAdminReviews(filters: AdminReviewFilters): Promise<PaginatedResponse<AdminReview>> {
  const { data } = await apiClient.get<PaginatedResponse<AdminReview>>('/admin/reviews', { params: filters });
  return data;
}

export async function fetchAdminReviewStatistics(): Promise<AdminReviewStatistics> {
  const { data } = await apiClient.get<{ data: AdminReviewStatistics }>('/admin/reviews/statistics');
  return data.data;
}

export async function approveReview(id: number): Promise<AdminReview> {
  const { data } = await apiClient.post<{ data: AdminReview }>(`/admin/reviews/${id}/approve`);
  return data.data;
}

export async function rejectReview(id: number, reason?: string): Promise<AdminReview> {
  const { data } = await apiClient.post<{ data: AdminReview }>(`/admin/reviews/${id}/reject`, { reason });
  return data.data;
}

export async function hideReview(id: number): Promise<AdminReview> {
  const { data } = await apiClient.post<{ data: AdminReview }>(`/admin/reviews/${id}/hide`);
  return data.data;
}

export async function replyToReview(id: number, reply: string): Promise<AdminReview> {
  const { data } = await apiClient.post<{ data: AdminReview }>(`/admin/reviews/${id}/reply`, { reply });
  return data.data;
}

export async function bulkModerateReviews(
  reviewIds: number[],
  status: 'approved' | 'rejected' | 'hidden',
): Promise<number> {
  const { data } = await apiClient.post<{ data: { updated: number } }>('/admin/reviews/bulk-moderate', {
    review_ids: reviewIds,
    status,
  });
  return data.data.updated;
}
