import { Link } from 'react-router-dom';
import { useCart } from '../../hooks/useCart';
import { formatPrice } from '../../services/productCatalog';
import { cart as cartCopy } from '../../content/copy';
import CartBadge from './CartBadge';

export default function MiniCart() {
  const { cart, isLoading } = useCart();
  const quantity = cart?.total_quantity ?? 0;
  const items = cart?.items ?? [];

  return (
    <div className="dropdown minicart-wrapper">
      <button
        type="button"
        className="btn btn-outline-secondary btn-sm"
        data-bs-toggle="dropdown"
        aria-expanded="false"
        aria-label={cartCopy.badgeAria(quantity)}
      >
        <CartBadge quantity={quantity} />
      </button>

      <div className="dropdown-menu dropdown-menu-end minicart-dropdown p-3 shadow">
        <h2 className="h6 mb-3">{cartCopy.title}</h2>

        {isLoading && <p className="text-muted small mb-0">{cartCopy.loading}</p>}

        {!isLoading && items.length === 0 && <p className="text-muted small mb-0">{cartCopy.empty.title}</p>}

        {!isLoading && items.length > 0 && (
          <>
            <ul className="list-unstyled mb-3" style={{ maxHeight: 280, overflowY: 'auto' }}>
              {items.map((item) => {
                const image = item.product_variant.product?.primary_image;

                return (
                  <li key={item.id} className="d-flex align-items-center justify-content-between gap-2 mb-2 small">
                    <span className="d-flex align-items-center gap-2 text-truncate">
                      <span className="ratio ratio-1x1 bg-white rounded-2 border flex-shrink-0" style={{ width: 32 }}>
                        {image && <img src={image.url} alt="" className="object-fit-cover" />}
                      </span>
                      <span className="text-truncate">
                        {item.product_variant.product?.name ?? item.product_variant.name} &times; {item.quantity}
                      </span>
                    </span>
                    <span className="flex-shrink-0">{formatPrice(item.line_total)}</span>
                  </li>
                );
              })}
            </ul>
            {cart && (
              <div className="d-flex justify-content-between fw-semibold small mb-3">
                <span>{cartCopy.grandTotal}</span>
                <span>{formatPrice(cart.totals.grand_total)}</span>
              </div>
            )}
          </>
        )}

        <Link to="/cart" className="btn btn-primary btn-sm w-100">
          {cartCopy.viewCart}
        </Link>
      </div>
    </div>
  );
}
