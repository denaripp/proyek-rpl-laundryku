import apiClient, { ApiSuccessResponse } from './api';

export interface RatingItem {
  id: string;
  order_id: string;
  order_number: string;
  customer_id: string;
  customer_name: string;
  customer_avatar?: string | null;
  store_id: string;
  store_name: string;
  score: number;
  review: string | null;
  created_at: string;
  updated_at: string;
}

export interface RatingSummary {
  average_score: number;
  total_ratings: number;
  breakdown: {
    '5': number;
    '4': number;
    '3': number;
    '2': number;
    '1': number;
  };
}

export interface StoreRatingsResponse {
  success: true;
  data: RatingItem[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    summary: RatingSummary;
  };
}

export interface StoreAdminRatingsResponse {
  success: true;
  data: RatingItem[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    summary: {
      average_score: number;
      total_ratings: number;
    };
  };
}

/**
 * Customer submits rating & review for a completed order
 */
export async function submitOrderRating(
  orderId: string,
  score: number,
  review?: string
): Promise<ApiSuccessResponse<RatingItem>> {
  return apiClient<ApiSuccessResponse<RatingItem>>(`/orders/${orderId}/rating`, {
    method: 'POST',
    body: JSON.stringify({
      score,
      review: review ? review.trim() : undefined,
    }),
  });
}

/**
 * Get rating for a specific order (to check if already rated)
 */
export async function getOrderRating(orderId: string): Promise<ApiSuccessResponse<RatingItem | null>> {
  return apiClient<ApiSuccessResponse<RatingItem | null>>(`/orders/${orderId}/rating`);
}

/**
 * Public: Get reviews & ratings statistics for a store
 */
export async function getStoreRatings(
  storeId: string,
  params?: {
    score?: number;
    has_review?: boolean;
    page?: number;
  }
): Promise<StoreRatingsResponse> {
  const query = new URLSearchParams();
  if (params?.score) query.append('score', params.score.toString());
  if (params?.has_review) query.append('has_review', 'true');
  if (params?.page) query.append('page', params.page.toString());

  const qs = query.toString() ? `?${query.toString()}` : '';
  return apiClient<StoreRatingsResponse>(`/stores/${storeId}/ratings${qs}`);
}

/**
 * Store Admin: Get reviews for their store
 */
export async function getStoreAdminRatings(params?: {
  score?: number;
  page?: number;
}): Promise<StoreAdminRatingsResponse> {
  const query = new URLSearchParams();
  if (params?.score) query.append('score', params.score.toString());
  if (params?.page) query.append('page', params.page.toString());

  const qs = query.toString() ? `?${query.toString()}` : '';
  return apiClient<StoreAdminRatingsResponse>(`/store-admin/ratings${qs}`);
}

