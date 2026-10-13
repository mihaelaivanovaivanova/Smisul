import { apiClient } from './client';
import type { PaginatedResponse } from '../types/product';
import type {
  CreateReviewPayload,
  Review,
  ReviewSortOption,
  ReviewSummary,
  SubmitReviewPayload,
  UpdateReviewPayload,
} from '../types/review';
import type { ReviewReward } from '../components/reviews/ReviewRewardCard';

interface SubmitReviewResponse {
  message: string;
  /** Only present when the submission came through a pre-verified reminder-email link - see ProductController::submitReview(). */
  reward?: ReviewReward | null;
}

export async function fetchProductReviews(
  slug: string,
  sort: ReviewSortOption,
  page: number,
  perPage?: number,
): Promise<PaginatedResponse<Review>> {
  const { data } = await apiClient.get<PaginatedResponse<Review>>(`/products/${slug}/reviews`, {
    params: { sort, page, per_page: perPage },
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

export async function submitReviewForConfirmation(
  slug: string,
  payload: SubmitReviewPayload,
): Promise<{ message: string; reward: ReviewReward | null }> {
  const { data } = await apiClient.post<SubmitReviewResponse>(`/products/${slug}/reviews/submit`, payload);
  return { message: data.message, reward: data.reward ?? null };
}

export async function confirmReview(
  reviewId: string,
  params: { expires: string; signature: string },
): Promise<{ review: Review; reward: ReviewReward | null }> {
  const { data } = await apiClient.get<{ data: Review; reward: ReviewReward | null }>(`/reviews/${reviewId}/confirm`, { params });
  return { review: data.data, reward: data.reward };
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
