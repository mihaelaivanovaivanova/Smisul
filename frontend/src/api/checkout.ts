import { apiClient, ensureCsrfCookie } from './client';
import { getGuestCartToken } from '../services/guestCartToken';
import type { LegalDocument, Order, PlaceOrderPayload, ShippingMethod } from '../types/checkout';
import type { PaginatedResponse } from '../types/product';
import type { Payment } from '../types/payment';

const GUEST_TOKEN_HEADER = 'X-Guest-Cart-Token';

function guestTokenHeaders(): Record<string, string> {
  const token = getGuestCartToken();
  return token ? { [GUEST_TOKEN_HEADER]: token } : {};
}

export async function fetchShippingMethods(): Promise<ShippingMethod[]> {
  const { data } = await apiClient.get<{ data: ShippingMethod[] }>('/checkout/shipping-methods');
  return data.data;
}

export async function fetchLegalDocuments(): Promise<LegalDocument[]> {
  const { data } = await apiClient.get<{ data: LegalDocument[] }>('/checkout/legal-documents');
  return data.data;
}

interface PlaceOrderResponse {
  data: Order;
  meta: { guest_access_token: string | null };
  payment: Payment;
}

export interface PlaceOrderResult {
  order: Order;
  guestAccessToken: string | null;
  payment: Payment;
}

export async function placeOrder(payload: PlaceOrderPayload): Promise<PlaceOrderResult> {
  await ensureCsrfCookie();
  const { data } = await apiClient.post<PlaceOrderResponse>('/checkout/orders', payload, {
    headers: guestTokenHeaders(),
  });
  return { order: data.data, guestAccessToken: data.meta.guest_access_token, payment: data.payment };
}

/**
 * `token` is the guest_access_token returned by placeOrder — required to
 * reload a guest's own confirmation page (see OrderController::show on the
 * backend); omitted for authenticated customers, whose ownership is
 * checked via their session instead.
 */
export async function fetchOrder(orderId: number, token?: string | null): Promise<Order> {
  const { data } = await apiClient.get<{ data: Order }>(`/orders/${orderId}`, {
    params: token ? { token } : undefined,
  });
  return data.data;
}

/** The signed-in customer's own order history — requires an authenticated session. */
export async function fetchOrders(page = 1): Promise<PaginatedResponse<Order>> {
  const { data } = await apiClient.get<PaginatedResponse<Order>>('/orders', { params: { page } });
  return data;
}
