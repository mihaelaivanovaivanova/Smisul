import type { Bundle } from './bundle';
import type { Currency, Media, ProductVariant } from './product';

export interface CartItem {
  id: number;
  quantity: number;
  /**
   * Exactly one of product_variant / bundle is non-null: an ordinary line
   * has product_variant, a kit line has bundle (see backend's
   * CartItemResource). Use services/cartItemDisplay.ts rather than reading
   * either directly for name/image/link.
   */
  product_variant: ProductVariant | null;
  bundle: Bundle | null;
  unit_price: number | null;
  compare_at_unit_price: number | null;
  is_on_sale: boolean;
  line_total: number;
  /** False if the product/variant is no longer purchasable or stock can no longer cover this line's quantity. */
  is_available: boolean;
  /** The highest quantity this line could currently be set to — drives the quantity stepper's upper bound. */
  max_quantity: number;
}

export interface CartTotals {
  subtotal: number;
  /** Always 0 today — discount codes aren't implemented yet; the field exists so the shape won't change when they are. */
  discount_total: number;
  /** Always 0 today — shipping calculation isn't implemented yet. */
  shipping_total: number;
  /** Always 0 today — tax/VAT calculation isn't implemented yet. */
  tax_total: number;
  grand_total: number;
  currency: string;
}

export interface Cart {
  id: number;
  currency: string;
  items: CartItem[];
  items_count: number;
  total_quantity: number;
  totals: CartTotals;
}

/** One cross-sell card's current offer (there can be several at once) — see api/cart.ts's fetchCartUpsell(). */
export interface CartUpsellOffer {
  product: {
    id: number;
    name: string;
    slug: string;
    primary_image: Media | null;
  };
  variant_id: number;
  amount: number;
  compare_at_amount: number;
  currency: Currency;
}
