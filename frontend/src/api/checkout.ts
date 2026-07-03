import { apiClient, ensureCsrfCookie } from './client';
import { getGuestCartToken } from '../services/guestCartToken';
import type { LegalDocument, Order, PlaceOrderPayload, ShippingMethod } from '../types/checkout';

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
}

export interface PlaceOrderResult {
  order: Order;
  guestAccessToken: string | null;
}

export async function placeOrder(payload: PlaceOrderPayload): Promise<PlaceOrderResult> {
  await ensureCsrfCookie();
  const { data } = await apiClient.post<PlaceOrderResponse>('/checkout/orders', payload, {
    headers: guestTokenHeaders(),
  });
  return { order: data.data, guestAccessToken: data.meta.guest_access_token };
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
