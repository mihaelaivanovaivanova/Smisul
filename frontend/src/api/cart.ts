import { apiClient, ensureCsrfCookie } from './client';
import { getGuestCartToken, setGuestCartToken } from '../services/guestCartToken';
import type { Cart, CartUpsellOffer } from '../types/cart';

const GUEST_TOKEN_HEADER = 'X-Guest-Cart-Token';

interface CartApiResponse {
  data: Cart;
  meta: { guest_token: string | null };
}

function guestTokenHeaders(): Record<string, string> {
  const token = getGuestCartToken();
  return token ? { [GUEST_TOKEN_HEADER]: token } : {};
}

/**
 * Every cart response reports the guest token currently in effect (or
 * null once the customer is authenticated) — persisting it here is what
 * makes the merge-on-login flow work: as long as the frontend keeps
 * sending the stored token, the backend merges it in on the first
 * authenticated cart request, then this starts reporting null and the
 * stale token is dropped automatically.
 */
function unwrap(response: CartApiResponse): Cart {
  setGuestCartToken(response.meta.guest_token);
  return response.data;
}

export async function fetchCart(): Promise<Cart> {
  const { data } = await apiClient.get<CartApiResponse>('/cart', { headers: guestTokenHeaders() });
  return unwrap(data);
}

/**
 * isUpsell is just an intent signal from a specific UI entry point (the
 * cart drawer's bamboo-case cross-sell card) — the backend re-validates
 * eligibility itself (cart must already contain Miswak) before ever
 * honoring it, see CartService::isEligibleForUpsellPrice().
 */
export async function addCartItem(productVariantId: number, quantity: number, isUpsell?: boolean): Promise<Cart> {
  await ensureCsrfCookie();
  const { data } = await apiClient.post<CartApiResponse>(
    '/cart/items',
    { product_variant_id: productVariantId, quantity, ...(isUpsell && { is_upsell: true }) },
    { headers: guestTokenHeaders() },
  );
  return unwrap(data);
}

export async function updateCartItem(itemId: number, quantity: number): Promise<Cart> {
  await ensureCsrfCookie();
  const { data } = await apiClient.patch<CartApiResponse>(
    `/cart/items/${itemId}`,
    { quantity },
    { headers: guestTokenHeaders() },
  );
  return unwrap(data);
}

export async function removeCartItem(itemId: number): Promise<Cart> {
  await ensureCsrfCookie();
  const { data } = await apiClient.delete<CartApiResponse>(`/cart/items/${itemId}`, {
    headers: guestTokenHeaders(),
  });
  return unwrap(data);
}

export async function clearCart(): Promise<Cart> {
  await ensureCsrfCookie();
  const { data } = await apiClient.delete<CartApiResponse>('/cart', { headers: guestTokenHeaders() });
  return unwrap(data);
}

/**
 * The bamboo-case cross-sell card's current offer for this cart (see
 * backend's CartService::upsellOffer()) — null whenever it isn't eligible
 * right now. The sole source of the price CartDrawer.tsx's upsell card
 * shows, so it can never display something addItem() wouldn't also honor.
 */
export async function fetchCartUpsell(): Promise<CartUpsellOffer | null> {
  const { data } = await apiClient.get<{ data: CartUpsellOffer | null }>('/cart/upsell', { headers: guestTokenHeaders() });
  return data.data;
}
