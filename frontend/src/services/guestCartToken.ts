const STORAGE_KEY = 'smisul_guest_cart_token';

/**
 * The guest cart token is a server-minted UUID (see CartController) that
 * identifies an anonymous cart. Persisting it in localStorage is what
 * makes the guest cart survive a page refresh or browser restart. It's
 * cleared automatically once the backend reports no guest cart is active
 * (i.e. the customer is authenticated) — see api/cart.ts.
 */
export function getGuestCartToken(): string | null {
  return localStorage.getItem(STORAGE_KEY);
}

export function setGuestCartToken(token: string | null): void {
  if (token) {
    localStorage.setItem(STORAGE_KEY, token);
  } else {
    localStorage.removeItem(STORAGE_KEY);
  }
}
