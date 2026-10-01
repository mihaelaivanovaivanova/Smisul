export interface Review {
  id: number;
  rating: number;
  /** Null for reviews submitted through the guest wizard, which doesn't collect one. */
  title: string | null;
  body: string;
  author_name: string;
  verified_purchase: boolean;
  helpful_count: number;
  created_at: string;
  admin_reply: string | null;
  admin_reply_at: string | null;
  is_own: boolean;
  /** Only present when `is_own` is true — the public list is pre-filtered to approved. */
  status?: 'pending' | 'approved' | 'rejected' | 'hidden';
  /** Only present when `is_own` is true. */
  order_id?: number;
}

export interface ReviewSummary {
  average_rating: number;
  review_count: number;
  verified_count: number;
  distribution: Record<string, number>;
}

export type ReviewSortOption = 'newest' | 'highest' | 'lowest' | 'helpful';

export interface CreateReviewPayload {
  order_id: number;
  product_variant_id: number;
  rating: number;
  title: string;
  body: string;
}

export interface UpdateReviewPayload {
  rating?: number;
  title?: string;
  body?: string;
}

/** Payload for the guest-friendly "Add a review" wizard — eligibility is checked by `email`, not a login session. No title: the wizard collects just a rating and free-text review. */
export interface SubmitReviewPayload {
  rating: number;
  body: string;
  email: string;
  display_name: string;
  is_anonymous: boolean;
}
