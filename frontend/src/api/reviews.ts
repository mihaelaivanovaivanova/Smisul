import { apiClient } from './client';
import type { PaginatedResponse } from '../types/product';
import type { CreateReviewPayload, Review, ReviewSortOption, ReviewSummary, UpdateReviewPayload } from '../types/review';

export async function fetchProductReviews(
  slug: string,
  sort: ReviewSortOption,
  page: number,
): Promise<PaginatedResponse<Review>> {
  const { data } = await apiClient.get<PaginatedResponse<Review>>(`/products/${slug}/reviews`, {
    params: { sort, page },
  });
  return data;
}

export async function fetchReviewSummary(slug: string): Promise<ReviewSummary> {
  const { data } = await apiClient.get<{ data: ReviewSummary }>(`/products/${slug}/reviews/summary`);
  return data.data;
}

export async function fetchMyReviews(): Promise<Review[]> {
  const { data } = await apiClient.get<{ data: Review[] }>('/customer/reviews');
  return data.data;
}

export async function createReview(payload: CreateReviewPayload): Promise<Review> {
  const { data } = await apiClient.post<{ data: Review }>('/customer/reviews', payload);
  return data.data;
}

export async function updateReview(id: number, payload: UpdateReviewPayload): Promise<Review> {
  const { data } = await apiClient.put<{ data: Review }>(`/customer/reviews/${id}`, payload);
  return data.data;
}

export async function deleteReview(id: number): Promise<void> {
  await apiClient.delete(`/customer/reviews/${id}`);
}

export async function markReviewHelpful(id: number): Promise<{ is_helpful: boolean; helpful_count: number }> {
  const { data } = await apiClient.post<{ data: { is_helpful: boolean; helpful_count: number } }>(
    `/reviews/${id}/helpful`,
  );
  return data.data;
}
