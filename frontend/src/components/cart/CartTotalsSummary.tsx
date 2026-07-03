import type { CartTotals } from '../../types/cart';
import { formatPrice } from '../../services/productCatalog';
import { cart as cartCopy } from '../../content/copy';

interface CartTotalsSummaryProps {
  totals: CartTotals;
}

export default function CartTotalsSummary({ totals }: CartTotalsSummaryProps) {
  return (
    <div className="card shadow-sm">
      <div className="card-body">
        <h2 className="h6 mb-3">{cartCopy.grandTotal}</h2>

        <div className="d-flex justify-content-between small mb-2">
          <span className="text-muted">{cartCopy.subtotal}</span>
          <span>{formatPrice(totals.subtotal)}</span>
        </div>

        {totals.discount_total > 0 && (
          <div className="d-flex justify-content-between small mb-2">
            <span className="text-muted">{cartCopy.discount}</span>
            <span>&minus;{formatPrice(totals.discount_total)}</span>
          </div>
        )}

        <div className="d-flex justify-content-between small mb-2">
          <span className="text-muted">{cartCopy.shipping}</span>
          <span className="text-muted">{cartCopy.shippingCalculatedLater}</span>
        </div>

        <div className="d-flex justify-content-between small mb-3">
          <span className="text-muted">{cartCopy.tax}</span>
          <span className="text-muted">{cartCopy.shippingCalculatedLater}</span>
        </div>

        <hr />

        <div className="d-flex justify-content-between fw-bold fs-5">
          <span>{cartCopy.grandTotal}</span>
          <span>{formatPrice(totals.grand_total)}</span>
        </div>
      </div>
    </div>
  );
}
