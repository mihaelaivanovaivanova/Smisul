import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import FormField from '../../components/FormField';
import PhoneField from '../../components/PhoneField';
import SearchableSelect from '../../components/SearchableSelect';
import LoadingState from '../../components/LoadingState';
import ErrorState from '../../components/ErrorState';
import { useAsync } from '../../hooks/useAsync';
import { fetchAdminProducts } from '../../api/admin/products';
import { createManualOrder } from '../../api/admin/orders';
import { fetchShippingMethods, fetchShippingOffices, fetchPaymentMethods } from '../../api/checkout';
import { getErrorMessage, getValidationErrors } from '../../api/errors';
import { formatPrice } from '../../services/productCatalog';
import type { ShippingMethod, ShippingOffice } from '../../types/checkout';

function methodKey(method: ShippingMethod): string {
  return `${method.carrier}:${method.delivery_type}`;
}

export default function CreateOrderPage() {
  const navigate = useNavigate();

  const { data: productsPage, isLoading: isLoadingProducts, error: productsError } = useAsync(
    () => fetchAdminProducts({ per_page: 100 }),
    [],
    'Could not load products.',
  );

  const { data: shippingMethods, isLoading: isLoadingMethods, error: methodsError } = useAsync(
    () => fetchShippingMethods(),
    [],
    'Could not load shipping methods.',
  );

  const { data: paymentMethods } = useAsync(() => fetchPaymentMethods(), [], 'Could not load payment methods.');
  const codDefaultFee = paymentMethods?.find((method) => method.value === 'cash_on_delivery')?.fee ?? 0;

  // Manual orders are office/locker pickup only (no street-address form) —
  // see StoreManualOrderRequest's own docblock for why.
  const pickupMethods = useMemo(() => (shippingMethods ?? []).filter((method) => method.requires_office), [shippingMethods]);

  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [phone, setPhone] = useState('');
  const [variantId, setVariantId] = useState('');
  const [quantity, setQuantity] = useState('1');
  const [selectedMethodKey, setSelectedMethodKey] = useState('');
  const [city, setCity] = useState('');
  const [officeId, setOfficeId] = useState('');
  const [shippingPrice, setShippingPrice] = useState('');
  const [shippingPriceTouched, setShippingPriceTouched] = useState(false);
  const [paymentMethod, setPaymentMethod] = useState<'cash_on_delivery' | 'paid'>('cash_on_delivery');
  const [codFee, setCodFee] = useState('');
  const [codFeeTouched, setCodFeeTouched] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const selectedMethod = pickupMethods.find((method) => methodKey(method) === selectedMethodKey) ?? null;

  const { data: offices, isLoading: isLoadingOffices } = useAsync(
    () => (selectedMethod ? fetchShippingOffices(selectedMethod.carrier) : Promise.resolve<ShippingOffice[]>([])),
    [selectedMethod?.carrier],
    'Could not load offices.',
  );

  // Prefills shipping_price with the selected method's default rate — the
  // admin can still edit or zero it out afterward (shippingPriceTouched
  // stops this from clobbering that edit if they then switch methods back
  // and forth).
  useEffect(() => {
    if (selectedMethod && !shippingPriceTouched) {
      setShippingPrice(String(selectedMethod.price));
    }
  }, [selectedMethod, shippingPriceTouched]);

  useEffect(() => {
    if (paymentMethod === 'cash_on_delivery' && !codFeeTouched) {
      setCodFee(String(codDefaultFee));
    }
    if (paymentMethod === 'paid' && !codFeeTouched) {
      setCodFee('0');
    }
  }, [paymentMethod, codDefaultFee, codFeeTouched]);

  // Cash on delivery only works for Speedy (its courier collects cash in
  // person at hand-off) — same auto-switch-off checkout's own DeliveryStep
  // does the moment BOX NOW becomes the selected carrier, so the two
  // choices can never end up mismatched.
  useEffect(() => {
    if (selectedMethod && selectedMethod.carrier !== 'speedy' && paymentMethod === 'cash_on_delivery') {
      setPaymentMethod('paid');
    }
  }, [selectedMethod, paymentMethod]);

  const relevantOffices = useMemo(
    () => (offices ?? []).filter((office) => office.type === selectedMethod?.delivery_type),
    [offices, selectedMethod],
  );

  const cities = useMemo(() => {
    const seen = new Map<string, string>();
    for (const office of relevantOffices) {
      if (!office.city || seen.has(office.city.toLowerCase())) continue;
      seen.set(office.city.toLowerCase(), office.city);
    }
    return Array.from(seen.values()).sort((a, b) => a.localeCompare(b, 'bg'));
  }, [relevantOffices]);

  const officesInCity = useMemo(
    () => relevantOffices.filter((office) => office.city.toLowerCase() === city.toLowerCase()),
    [relevantOffices, city],
  );

  const selectedOffice = officesInCity.find((office) => office.id === officeId) ?? null;
  const isCashOnDeliveryDisabled = Boolean(selectedMethod && selectedMethod.carrier !== 'speedy');

  const variantOptions = useMemo(
    () =>
      (productsPage?.data ?? []).flatMap((product) =>
        product.variants.map((variant) => {
          const price = variant.prices.find((p) => p.currency === 'EUR')?.amount ?? 0;
          return {
            value: String(variant.id),
            label: `${product.name} — ${variant.name} (${formatPrice(price)})`,
            searchText: `${product.name} ${variant.name}`,
          };
        }),
      ),
    [productsPage],
  );

  function handleSelectMethod(nextKey: string) {
    setSelectedMethodKey(nextKey);
    setCity('');
    setOfficeId('');
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    setSubmitError(null);
    setErrors({});

    if (!selectedMethod || !selectedOffice) {
      setSubmitError('Please choose a shipping method and pickup office.');
      return;
    }

    setIsSubmitting(true);
    try {
      const order = await createManualOrder({
        customer_first_name: firstName,
        customer_last_name: lastName,
        customer_phone: phone,
        shipping_carrier: selectedMethod.carrier,
        shipping_delivery_type: selectedMethod.delivery_type as 'office' | 'locker',
        shipping_office_id: selectedOffice.id,
        shipping_office_name: selectedOffice.name,
        shipping_office_city: selectedOffice.city,
        shipping_office_address: selectedOffice.address,
        shipping_price: Number(shippingPrice) || 0,
        payment_method: paymentMethod,
        cod_fee: Number(codFee) || 0,
        product_variant_id: Number(variantId),
        quantity: Number(quantity) || 1,
      });
      navigate(`/admin/orders/${order.id}`);
    } catch (error) {
      setErrors(getValidationErrors(error));
      setSubmitError(getErrorMessage(error, 'Could not create the order.'));
    } finally {
      setIsSubmitting(false);
    }
  }

  if (isLoadingProducts || isLoadingMethods) {
    return <LoadingState message="Loading..." />;
  }

  if (productsError || methodsError) {
    return <ErrorState message={productsError ?? methodsError ?? 'Something went wrong.'} />;
  }

  return (
    <div>
      <h1 className="h3 mb-4">Create order</h1>

      <form onSubmit={(event) => void handleSubmit(event)} className="card shadow-sm" style={{ maxWidth: 640 }}>
        <div className="card-body">
          <h2 className="h6 mb-3">Customer</h2>
          <div className="row g-3 mb-4">
            <div className="col-12 col-sm-6">
              <FormField id="order-first-name" label="First name" value={firstName} onChange={setFirstName} error={errors.customer_first_name} required />
            </div>
            <div className="col-12 col-sm-6">
              <FormField id="order-last-name" label="Last name" value={lastName} onChange={setLastName} error={errors.customer_last_name} required />
            </div>
            <div className="col-12">
              <PhoneField id="order-phone" label="Phone" value={phone} onChange={setPhone} error={errors.customer_phone} required />
            </div>
          </div>

          <h2 className="h6 mb-3">Package</h2>
          <div className="row g-3 mb-4">
            <div className="col-12 col-sm-8">
              <label htmlFor="order-variant" className="form-label">
                Product / pack size
              </label>
              <SearchableSelect
                id="order-variant"
                value={variantId}
                options={variantOptions}
                placeholder="Search a product..."
                onChange={setVariantId}
                invalid={Boolean(errors.product_variant_id)}
              />
              {errors.product_variant_id && <div className="text-danger small mt-1">{errors.product_variant_id}</div>}
            </div>
            <div className="col-12 col-sm-4">
              <FormField id="order-quantity" label="Quantity" type="number" value={quantity} onChange={setQuantity} error={errors.quantity} required />
            </div>
          </div>

          <h2 className="h6 mb-3">Delivery</h2>
          <div className="d-flex flex-column gap-2 mb-3">
            {pickupMethods.map((method) => (
              <label key={methodKey(method)} className={`shipping-option ${selectedMethodKey === methodKey(method) ? 'is-selected' : ''}`}>
                <input
                  type="radio"
                  name="shipping_method"
                  className="form-check-input mt-0"
                  checked={selectedMethodKey === methodKey(method)}
                  onChange={() => handleSelectMethod(methodKey(method))}
                />
                <span className="flex-grow-1">{method.label}</span>
                <span className="fw-semibold">{formatPrice(method.price, method.currency as 'EUR')}</span>
              </label>
            ))}
          </div>
          {errors.shipping_carrier && <div className="text-danger small mb-3">{errors.shipping_carrier}</div>}

          {selectedMethod && (
            <div className="row g-2 mb-4">
              <div className="col-12 col-sm-6">
                <label htmlFor="order-office-city" className="form-label small fw-semibold">
                  City
                </label>
                {isLoadingOffices ? (
                  <div className="d-flex align-items-center gap-2 text-muted small py-2">
                    <span className="spinner-border spinner-border-sm" role="status" aria-hidden="true" />
                    Loading offices...
                  </div>
                ) : (
                  <SearchableSelect
                    id="order-office-city"
                    value={city}
                    options={cities.map((cityOption) => ({ value: cityOption, label: cityOption }))}
                    placeholder="Choose a city"
                    onChange={(nextCity) => {
                      setCity(nextCity);
                      setOfficeId('');
                    }}
                  />
                )}
              </div>
              <div className="col-12 col-sm-6">
                <label htmlFor="order-office" className="form-label small fw-semibold">
                  Office
                </label>
                <SearchableSelect
                  id="order-office"
                  value={officeId}
                  options={officesInCity.map((office) => ({ value: office.id, label: `${office.name} — ${office.address}` }))}
                  placeholder="Choose an office"
                  disabled={!city}
                  invalid={Boolean(errors.shipping_office_id)}
                  onChange={setOfficeId}
                />
                {errors.shipping_office_id && <div className="text-danger small mt-1">{errors.shipping_office_id}</div>}
              </div>
              <div className="col-12 col-sm-6 mt-2">
                <FormField
                  id="order-shipping-price"
                  label="Delivery cost"
                  type="number"
                  value={shippingPrice}
                  onChange={(value) => {
                    setShippingPrice(value);
                    setShippingPriceTouched(true);
                  }}
                  error={errors.shipping_price}
                  required
                />
              </div>
            </div>
          )}

          <h2 className="h6 mb-3">Payment</h2>
          <div className="d-flex flex-column gap-2 mb-3">
            <label className={`payment-option ${paymentMethod === 'cash_on_delivery' ? 'is-selected' : ''} ${isCashOnDeliveryDisabled ? 'is-disabled' : ''}`}>
              <input
                type="radio"
                name="payment_method"
                className="form-check-input mt-0"
                checked={paymentMethod === 'cash_on_delivery'}
                disabled={isCashOnDeliveryDisabled}
                onChange={() => setPaymentMethod('cash_on_delivery')}
              />
              <span className="flex-grow-1">
                Cash on delivery
                {isCashOnDeliveryDisabled && <span className="text-muted d-block small">Only available for Speedy</span>}
              </span>
            </label>
            <label className={`payment-option ${paymentMethod === 'paid' ? 'is-selected' : ''}`}>
              <input
                type="radio"
                name="payment_method"
                className="form-check-input mt-0"
                checked={paymentMethod === 'paid'}
                onChange={() => setPaymentMethod('paid')}
              />
              <span className="flex-grow-1">Already paid (bank transfer, in person, etc.)</span>
            </label>
          </div>
          <div className="mb-4" style={{ maxWidth: 220 }}>
            <FormField
              id="order-cod-fee"
              label="Cash-on-delivery fee"
              type="number"
              value={codFee}
              onChange={(value) => {
                setCodFee(value);
                setCodFeeTouched(true);
              }}
              error={errors.cod_fee}
              required
            />
          </div>

          {submitError && <div className="alert alert-danger">{submitError}</div>}

          <button type="submit" className="btn btn-primary" disabled={isSubmitting}>
            {isSubmitting ? 'Creating...' : 'Create order'}
          </button>
        </div>
      </form>
    </div>
  );
}
