import Icon from '../icons/Icon';
import { cart as cartCopy } from '../../content/copy';

interface CartBadgeProps {
  quantity: number;
}

export default function CartBadge({ quantity }: CartBadgeProps) {
  return (
    <span className="position-relative d-inline-flex" aria-label={cartCopy.badgeAria(quantity)}>
      <Icon name="cart" />
      {quantity > 0 && (
        <span
          className="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
          style={{ fontSize: '0.6rem' }}
        >
          {quantity > 99 ? '99+' : quantity}
        </span>
      )}
    </span>
  );
}
