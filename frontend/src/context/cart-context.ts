import { createContext } from 'react';
import type { Cart } from '../types/cart';

export interface CartContextValue {
  cart: Cart | null;
  isLoading: boolean;
  error: string | null;
  addItem: (productVariantId: number, quantity: number) => Promise<void>;
  updateItem: (itemId: number, quantity: number) => Promise<void>;
  removeItem: (itemId: number) => Promise<void>;
  clear: () => Promise<void>;
  /** Re-fetches the cart from the server — used after checkout places an order server-side, without going through any of the mutation methods above. */
  refresh: () => Promise<void>;
}

export const CartContext = createContext<CartContextValue | undefined>(undefined);
