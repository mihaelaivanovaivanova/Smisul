import { apiClient, ensureCsrfCookie } from './client';
import { getGuestCartToken } from '../services/guestCartToken';
import type {
  LegalDocument,
  Order,
  PlaceOrderPayload,
  Settlement,
  Shipment,
  ShippingCarrier,
  ShippingMethod,
  ShippingOffice,
} from '../types/checkout';
import type { PaginatedResponse } from '../types/product';
import type { Payment, PaymentMethodOption } from '../types/payment';

const GUEST_TOKEN_HEADER = 'X-Guest-Cart-Token';

function guestTokenHeaders(): Record<string, string> {
  const token = getGuestCartToken();
  return token ? { [GUEST_TOKEN_HEADER]: token } : {};
}

export async function fetchShippingMethods(): Promise<ShippingMethod[]> {
  const { data } = await apiClient.get<{ data: ShippingMethod[] }>('/checkout/shipping-methods');
  return data.data;
}

/** Offices/lockers for a carrier, optionally filtered by city (see the Sprint 8 shipping engine). */
export async function fetchShippingOffices(carrier: ShippingCarrier, city?: string): Promise<ShippingOffice[]> {
  const { data } = await apiClient.get<{ data: ShippingOffice[] }>('/checkout/shipping-offices', {
    params: { carrier, city: city || undefined },
  });
  return data.data;
}

export async function fetchLegalDocuments(): Promise<LegalDocument[]> {
  const { data } = await apiClient.get<{ data: LegalDocument[] }>('/checkout/legal-documents');
  return data.data;
}

/**
 * The full Bulgarian settlement list (towns, cities, villages) for the
 * "Населено място" picker — a large (~1MB), rarely-changing payload, so
 * callers should fetch it once and hold onto the result rather than
 * re-fetching on every render (see CheckoutPage's lazy fetch-on-first-need).
 */
export async function fetchSettlements(): Promise<Settlement[]> {
  const { data } = await apiClient.get<{ data: Settlement[] }>('/checkout/settlements');
  return data.data;
}

/** Card is the only method offered (see PaymentService::availablePaymentMethods). */
export async function fetchPaymentMethods(): Promise<PaymentMethodOption[]> {
  const { data } = await apiClient.get<{ data: PaymentMethodOption[] }>('/checkout/payment-methods');
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

/**
 * The order's shipment + tracking history, if one has been created yet (see
 * ShipmentController — this reads persisted state, it does not poll the
 * carrier live). `token` mirrors fetchOrder's guest-access pattern.
 */
export async function fetchShipment(orderId: number, token?: string | null): Promise<Shipment> {
  const { data } = await apiClient.get<{ data: Shipment }>(`/orders/${orderId}/shipment`, {
    params: token ? { token } : undefined,
  });
  return data.data;
}
