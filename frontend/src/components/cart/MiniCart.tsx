import { useCart } from '../../hooks/useCart';
import { cart as cartCopy } from '../../content/copy';
import CartBadge from './CartBadge';

/**
 * Navbar cart icon — opens the same slide-in CartDrawer (see CartDrawer.tsx,
 * mounted once in PublicLayout) that AddToCartButton opens after a
 * successful add, instead of a separate dropdown preview.
 */
export default function MiniCart() {
  const { cart, openDrawer } = useCart();
  const quantity = cart?.total_quantity ?? 0;

  return (
    <button
      type="button"
      className="btn btn-outline-secondary btn-sm"
      onClick={openDrawer}
      aria-label={cartCopy.badgeAria(quantity)}
    >
      <CartBadge quantity={quantity} />
    </button>
  );
}
