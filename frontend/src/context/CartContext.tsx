import { useCallback, useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import * as cartApi from '../api/cart';
import { getErrorMessage } from '../api/errors';
import { useAuth } from '../hooks/useAuth';
import { CartContext } from './cart-context';
import type { CartContextValue } from './cart-context';
import type { Cart } from '../types/cart';

const FALLBACK_ERROR = 'Неуспешно зареждане на количката.';

export function CartProvider({ children }: { children: ReactNode }) {
  const { isAuthenticated, isLoading: isAuthLoading } = useAuth();
  const [cart, setCart] = useState<Cart | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Re-fetches whenever auth state settles or changes — this is also what
  // drives the guest-cart-merge-on-login flow: as long as the frontend
  // still has a stored guest token, sending it on the first authenticated
  // fetch is what triggers the backend to merge it in (see api/cart.ts).
  useEffect(() => {
    if (isAuthLoading) {
      return;
    }

    let isMounted = true;
    setIsLoading(true);
    setError(null);

    cartApi
      .fetchCart()
      .then((current) => {
        if (isMounted) {
          setCart(current);
        }
      })
      .catch((err: unknown) => {
        if (isMounted) {
          setError(getErrorMessage(err, FALLBACK_ERROR));
        }
      })
      .finally(() => {
        if (isMounted) {
          setIsLoading(false);
        }
      });

    return () => {
      isMounted = false;
    };
  }, [isAuthenticated, isAuthLoading]);

  const addItem = useCallback(async (productVariantId: number, quantity: number) => {
    const updated = await cartApi.addCartItem(productVariantId, quantity);
    setCart(updated);
  }, []);

  const updateItem = useCallback(async (itemId: number, quantity: number) => {
    const updated = await cartApi.updateCartItem(itemId, quantity);
    setCart(updated);
  }, []);

  const removeItem = useCallback(async (itemId: number) => {
    const updated = await cartApi.removeCartItem(itemId);
    setCart(updated);
  }, []);

  const clear = useCallback(async () => {
    const updated = await cartApi.clearCart();
    setCart(updated);
  }, []);

  const value: CartContextValue = { cart, isLoading, error, addItem, updateItem, removeItem, clear };

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}
