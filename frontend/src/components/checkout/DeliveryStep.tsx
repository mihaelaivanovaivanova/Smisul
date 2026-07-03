import FormField from '../FormField';
import LoadingState from '../LoadingState';
import ErrorState from '../ErrorState';
import { formatPrice } from '../../services/productCatalog';
import { checkout as checkoutCopy } from '../../content/copy';
import type { ShippingAddress, ShippingCarrier, ShippingMethod } from '../../types/checkout';

interface DeliveryStepProps {
  address: ShippingAddress;
  onAddressChange: <K extends keyof ShippingAddress>(field: K, value: ShippingAddress[K]) => void;
  shippingMethods: ShippingMethod[] | null;
  isLoadingShippingMethods: boolean;
  shippingMethodsError: string | null;
  selectedCarrier: ShippingCarrier | null;
  onSelectCarrier: (carrier: ShippingCarrier) => void;
  errors: Record<string, string>;
}

export default function DeliveryStep({
  address,
  onAddressChange,
  shippingMethods,
  isLoadingShippingMethods,
  shippingMethodsError,
  selectedCarrier,
  onSelectCarrier,
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

      <h2 className="h6 mb-3">{checkoutCopy.delivery.title}</h2>

      {isLoadingShippingMethods && <LoadingState message={checkoutCopy.delivery.loading} />}
      {!isLoadingShippingMethods && shippingMethodsError && <ErrorState message={shippingMethodsError} />}

      {!isLoadingShippingMethods && shippingMethods && (
        <div className="d-flex flex-column gap-2">
          {shippingMethods.map((method) => (
            <label
              key={method.carrier}
              className={`d-flex align-items-center justify-content-between border rounded-3 p-3 ${
                selectedCarrier === method.carrier ? 'border-primary' : ''
              }`}
              style={{ cursor: 'pointer' }}
            >
              <span className="d-flex align-items-center gap-2">
                <input
                  type="radio"
                  name="shipping_carrier"
                  className="form-check-input mt-0"
                  checked={selectedCarrier === method.carrier}
                  onChange={() => onSelectCarrier(method.carrier)}
                />
                <span>
                  <span className="d-block fw-semibold">{method.label}</span>
                  <span className="d-block text-muted small">{method.description}</span>
                </span>
              </span>
              <span className="fw-semibold">{formatPrice(method.price, method.currency as 'EUR')}</span>
            </label>
          ))}
        </div>
      )}

      {errors.shipping_carrier && <div className="text-danger small mt-2">{errors.shipping_carrier}</div>}
    </div>
  );
}
