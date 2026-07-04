import FormField from '../FormField';
import LoadingState from '../LoadingState';
import ErrorState from '../ErrorState';
import { formatPrice } from '../../services/productCatalog';
import { checkout as checkoutCopy } from '../../content/copy';
import type { ShippingAddress, ShippingMethod, ShippingOffice } from '../../types/checkout';

interface DeliveryStepProps {
  address: ShippingAddress;
  onAddressChange: <K extends keyof ShippingAddress>(field: K, value: ShippingAddress[K]) => void;
  billingSameAsShipping: boolean;
  onToggleBillingSameAsShipping: (value: boolean) => void;
  billingAddress: ShippingAddress;
  onBillingAddressChange: <K extends keyof ShippingAddress>(field: K, value: ShippingAddress[K]) => void;
  shippingMethods: ShippingMethod[] | null;
  isLoadingShippingMethods: boolean;
  shippingMethodsError: string | null;
  selectedMethod: ShippingMethod | null;
  onSelectMethod: (method: ShippingMethod) => void;
  offices: ShippingOffice[] | null;
  isLoadingOffices: boolean;
  officesError: string | null;
  selectedOfficeId: string | null;
  onSelectOffice: (office: ShippingOffice) => void;
  errors: Record<string, string>;
}

function methodKey(method: ShippingMethod): string {
  return `${method.carrier}:${method.delivery_type}`;
}

export default function DeliveryStep({
  address,
  onAddressChange,
  billingSameAsShipping,
  onToggleBillingSameAsShipping,
  billingAddress,
  onBillingAddressChange,
  shippingMethods,
  isLoadingShippingMethods,
  shippingMethodsError,
  selectedMethod,
  onSelectMethod,
  offices,
  isLoadingOffices,
  officesError,
  selectedOfficeId,
  onSelectOffice,
  errors,
}: DeliveryStepProps) {
  return (
    <div>
      <h2 className="h6 mb-3">{checkoutCopy.address.title}</h2>

      <div className="row">
        <div className="col-12 col-sm-6 mb-3">
          <FormField
            id="checkout-country"
            label={checkoutCopy.address.country}
            value={address.country}
            onChange={(value) => onAddressChange('country', value)}
            error={errors['address.country']}
            required
          />
        </div>
        <div className="col-12 col-sm-6 mb-3">
          <FormField
            id="checkout-city"
            label={checkoutCopy.address.city}
            value={address.city}
            onChange={(value) => onAddressChange('city', value)}
            error={errors['address.city']}
            required
          />
        </div>
      </div>

      <div className="row">
        <div className="col-12 col-sm-6 mb-3">
          <FormField
            id="checkout-postal-code"
            label={checkoutCopy.address.postalCode}
            value={address.postal_code}
            onChange={(value) => onAddressChange('postal_code', value)}
            error={errors['address.postal_code']}
            required
          />
        </div>
        <div className="col-12 col-sm-6 mb-3">
          <FormField
            id="checkout-apartment"
            label={checkoutCopy.address.apartment}
            value={address.apartment}
            onChange={(value) => onAddressChange('apartment', value)}
            error={errors['address.apartment']}
          />
        </div>
      </div>

      <div className="mb-4">
        <FormField
          id="checkout-address-line"
          label={checkoutCopy.address.addressLine}
          value={address.address_line}
          onChange={(value) => onAddressChange('address_line', value)}
          error={errors['address.address_line']}
          required
        />
      </div>

      <h2 className="h6 mb-3">{checkoutCopy.billing.title}</h2>

      <div className="form-check mb-3">
        <input
          type="checkbox"
          className="form-check-input"
          id="checkout-billing-same-as-shipping"
          checked={billingSameAsShipping}
          onChange={(event) => onToggleBillingSameAsShipping(event.target.checked)}
        />
        <label htmlFor="checkout-billing-same-as-shipping" className="form-check-label">
          {checkoutCopy.billing.sameAsShipping}
        </label>
      </div>

      {!billingSameAsShipping && (
        <>
          <div className="row">
            <div className="col-12 col-sm-6 mb-3">
              <FormField
                id="checkout-billing-country"
                label={checkoutCopy.address.country}
                value={billingAddress.country}
                onChange={(value) => onBillingAddressChange('country', value)}
                error={errors['billing_address.country']}
                required
              />
            </div>
            <div className="col-12 col-sm-6 mb-3">
              <FormField
                id="checkout-billing-city"
                label={checkoutCopy.address.city}
                value={billingAddress.city}
                onChange={(value) => onBillingAddressChange('city', value)}
                error={errors['billing_address.city']}
                required
              />
            </div>
          </div>

          <div className="row">
            <div className="col-12 col-sm-6 mb-3">
              <FormField
                id="checkout-billing-postal-code"
                label={checkoutCopy.address.postalCode}
                value={billingAddress.postal_code}
                onChange={(value) => onBillingAddressChange('postal_code', value)}
                error={errors['billing_address.postal_code']}
                required
              />
            </div>
            <div className="col-12 col-sm-6 mb-3">
              <FormField
                id="checkout-billing-apartment"
                label={checkoutCopy.address.apartment}
                value={billingAddress.apartment}
                onChange={(value) => onBillingAddressChange('apartment', value)}
                error={errors['billing_address.apartment']}
              />
            </div>
          </div>

          <div className="mb-4">
            <FormField
              id="checkout-billing-address-line"
              label={checkoutCopy.address.addressLine}
              value={billingAddress.address_line}
              onChange={(value) => onBillingAddressChange('address_line', value)}
              error={errors['billing_address.address_line']}
              required
            />
          </div>
        </>
      )}

      <h2 className="h6 mb-3">{checkoutCopy.delivery.title}</h2>

      {isLoadingShippingMethods && <LoadingState message={checkoutCopy.delivery.loading} />}
      {!isLoadingShippingMethods && shippingMethodsError && <ErrorState message={shippingMethodsError} />}

      {!isLoadingShippingMethods && shippingMethods && (
        <div className="d-flex flex-column gap-2">
          {shippingMethods.map((method) => {
            const isSelected = selectedMethod !== null && methodKey(selectedMethod) === methodKey(method);

            return (
              <label
                key={methodKey(method)}
                className={`d-flex align-items-center justify-content-between border rounded-3 p-3 ${
                  isSelected ? 'border-primary' : ''
                }`}
                style={{ cursor: 'pointer' }}
              >
                <span className="d-flex align-items-center gap-2">
                  <input
                    type="radio"
                    name="shipping_method"
                    className="form-check-input mt-0"
                    checked={isSelected}
                    onChange={() => onSelectMethod(method)}
                  />
                  <span>
                    <span className="d-block fw-semibold">{method.label}</span>
                    <span className="d-block text-muted small">{method.description}</span>
                    <span className="d-block text-muted small">
                      {checkoutCopy.delivery.estimatedDeliveryPrefix} {method.estimated_delivery}
                    </span>
                  </span>
                </span>
                <span className="fw-semibold">{formatPrice(method.price, method.currency as 'EUR')}</span>
              </label>
            );
          })}
        </div>
      )}

      {errors.shipping_carrier && <div className="text-danger small mt-2">{errors.shipping_carrier}</div>}

      {selectedMethod?.requires_office && (
        <div className="mt-3">
          <label htmlFor="checkout-office" className="form-label small fw-semibold">
            {checkoutCopy.delivery.officeLabel}
          </label>

          {isLoadingOffices && <LoadingState message={checkoutCopy.delivery.officeLoading} />}
          {!isLoadingOffices && officesError && <ErrorState message={officesError} />}

          {!isLoadingOffices && !officesError && offices && offices.length === 0 && (
            <div className="text-muted small">{checkoutCopy.delivery.officeEmpty}</div>
          )}

          {!isLoadingOffices && offices && offices.length > 0 && (
            <select
              id="checkout-office"
              className={`form-select ${errors.shipping_office_id ? 'is-invalid' : ''}`}
              value={selectedOfficeId ?? ''}
              onChange={(event) => {
                const office = offices.find((candidate) => candidate.id === event.target.value);
                if (office) onSelectOffice(office);
              }}
            >
              <option value="" disabled>
                {checkoutCopy.delivery.officePlaceholder}
              </option>
              {offices.map((office) => (
                <option key={office.id} value={office.id}>
                  {office.name} — {office.address}
                </option>
              ))}
            </select>
          )}

          {errors.shipping_office_id && <div className="text-danger small mt-1">{errors.shipping_office_id}</div>}
        </div>
      )}
    </div>
  );
}
