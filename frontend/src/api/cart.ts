import { apiClient, ensureCsrfCookie } from './client';
import { getGuestCartToken, setGuestCartToken } from '../services/guestCartToken';
import type { Cart } from '../types/cart';

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

export async function addCartItem(productVariantId: number, quantity: number): Promise<Cart> {
  await ensureCsrfCookie();
  const { data } = await apiClient.post<CartApiResponse>(
    '/cart/items',
    { product_variant_id: productVariantId, quantity },
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
