import Icon from '../icons/Icon';
import { formatPrice } from '../../services/productCatalog';
import { cart as cartCopy } from '../../content/copy';

interface FreeShippingBarProps {
  /** Whole-EUR cart subtotal that unlocks free shipping — admin-configured, see general.free_shipping_threshold. Caller should only render this when the threshold is > 0. */
  threshold: number;
  subtotal: number;
}

/**
 * The "spend €X more for free shipping" progress nudge. Originally only the
 * cart drawer's own CartDrawer.tsx (shown right under its header); extracted
 * so the checkout review step can show the same live progress next to its
 * own last-chance upsell cards, using the same cart total both places read.
 */
export default function FreeShippingBar({ threshold, subtotal }: FreeShippingBarProps) {
  const remaining = Math.max(0, threshold - subtotal);
  const isUnlocked = remaining === 0;
  const percent = Math.min(100, (subtotal / threshold) * 100);
  // Keeps the truck marker's circle visually inside the track at the
  // extremes instead of half-clipping past either edge.
  const markerPosition = Math.min(96, Math.max(4, percent));

  return (
    <div className="cart-drawer-shipping-bar">
      <p className="cart-drawer-shipping-bar__message">
        {isUnlocked ? (
          <>
            {cartCopy.freeShipping.unlockedPrefix}{' '}
            <span className="cart-drawer-shipping-bar__unlocked">{cartCopy.freeShipping.unlockedHighlight}</span>
          </>
        ) : (
          cartCopy.freeShipping.remaining(formatPrice(remaining))
        )}
      </p>
      <div className="cart-drawer-shipping-bar__track">
        <div className="cart-drawer-shipping-bar__fill" style={{ width: `${percent}%` }} />
        <div className="cart-drawer-shipping-bar__marker" style={{ left: `${markerPosition}%` }}>
          <Icon name="truck" />
        </div>
      </div>
      <p className="cart-drawer-shipping-bar__goal">{cartCopy.freeShipping.goal(formatPrice(threshold))}</p>
    </div>
  );
}
