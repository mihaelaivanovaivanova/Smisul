import LoadingState from '../LoadingState';
import ErrorState from '../ErrorState';
import { formatPrice } from '../../services/productCatalog';
import { checkout as checkoutCopy } from '../../content/copy';
import type { Cart } from '../../types/cart';
import type { CustomerInfo, LegalDocument, ShippingAddress, ShippingMethod } from '../../types/checkout';

interface OrderReviewStepProps {
  cart: Cart;
  customer: CustomerInfo;
  address: ShippingAddress;
  billingSameAsShipping: boolean;
  billingAddress: ShippingAddress;
  shippingMethod: ShippingMethod | null;
  legalDocuments: LegalDocument[] | null;
  isLoadingLegalDocuments: boolean;
  legalDocumentsError: string | null;
  acceptedLegalDocumentIds: number[];
  onToggleLegalDocument: (documentId: number) => void;
  errors: Record<string, string>;
}

export default function OrderReviewStep({
  cart,
  customer,
  address,
  billingSameAsShipping,
  billingAddress,
  shippingMethod,
  legalDocuments,
  isLoadingLegalDocuments,
  legalDocumentsError,
  acceptedLegalDocumentIds,
  onToggleLegalDocument,
  errors,
}: OrderReviewStepProps) {
  return (
    <div>
      <h2 className="h6 mb-3">{checkoutCopy.review.itemsHeading}</h2>
      <ul className="list-unstyled mb-4">
        {cart.items.map((item) => (
          <li key={item.id} className="d-flex justify-content-between border-bottom py-2">
            <span>
              {item.product_variant.product?.name ?? item.product_variant.name}
              {item.product_variant.name ? ` (${item.product_variant.name})` : ''} × {item.quantity}
            </span>
            <span>{formatPrice(item.line_total)}</span>
          </li>
        ))}
      </ul>

      <div className="row mb-4">
        <div className="col-12 col-sm-6">
          <h2 className="h6 mb-2">{checkoutCopy.review.customerHeading}</h2>
          <p className="mb-0 small text-muted">
            {customer.first_name} {customer.last_name}
            <br />
            {customer.email}
            <br />
            {customer.phone}
            {customer.company && (
              <>
                <br />
                {customer.company}
              </>
            )}
          </p>
        </div>
        <div className="col-12 col-sm-6">
          <h2 className="h6 mb-2">{checkoutCopy.review.addressHeading}</h2>
          <p className="mb-0 small text-muted">
            {address.address_line}
            {address.apartment && `, ${address.apartment}`}
            <br />
            {address.city}, {address.postal_code}
            <br />
            {address.country}
          </p>
        </div>
      </div>

      {!billingSameAsShipping && (
        <div className="mb-4">
          <h2 className="h6 mb-2">{checkoutCopy.review.billingAddressHeading}</h2>
          <p className="mb-0 small text-muted">
            {billingAddress.address_line}
            {billingAddress.apartment && `, ${billingAddress.apartment}`}
            <br />
            {billingAddress.city}, {billingAddress.postal_code}
            <br />
            {billingAddress.country}
          </p>
        </div>
      )}

      {shippingMethod && (
        <div className="mb-4">
          <h2 className="h6 mb-2">{checkoutCopy.review.deliveryHeading}</h2>
          <p className="mb-0 small text-muted">
            {shippingMethod.label} — {formatPrice(shippingMethod.price, shippingMethod.currency as 'EUR')}
          </p>
        </div>
      )}

      <h2 className="h6 mb-3">{checkoutCopy.legal.title}</h2>

      {isLoadingLegalDocuments && <LoadingState message={checkoutCopy.legal.loading} />}
      {!isLoadingLegalDocuments && legalDocumentsError && <ErrorState message={legalDocumentsError} />}

      {!isLoadingLegalDocuments && legalDocuments && (
        <div className="d-flex flex-column gap-2">
          {legalDocuments.map((document) => (
            <div className="form-check" key={document.id}>
              <input
                type="checkbox"
                className="form-check-input"
                id={`legal-doc-${document.id}`}
                checked={acceptedLegalDocumentIds.includes(document.id)}
                onChange={() => onToggleLegalDocument(document.id)}
              />
              <label htmlFor={`legal-doc-${document.id}`} className="form-check-label">
                {checkoutCopy.legal.acceptPrefix} {document.title}
              </label>
            </div>
          ))}
        </div>
      )}

      {errors.legal_document_ids && <div className="text-danger small mt-2">{errors.legal_document_ids}</div>}
    </div>
  );
}
