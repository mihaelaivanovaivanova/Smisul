import { useEffect, useMemo, useState } from 'react';
import FormField from '../FormField';
import LoadingState from '../LoadingState';
import ErrorState from '../ErrorState';
import SearchableSelect from '../SearchableSelect';
import { formatPrice } from '../../services/productCatalog';
import { checkout as checkoutCopy } from '../../content/copy';
import type {
  CustomerInfo,
  Settlement,
  ShippingAddress,
  ShippingCarrier,
  ShippingDeliveryType,
  ShippingMethod,
  ShippingOffice,
} from '../../types/checkout';

/** Each carrier's own real logo (see frontend/public/shipping/), sourced from their official sites. */
const CARRIER_LOGOS: Record<ShippingCarrier, { src: string; alt: string }> = {
  econt: { src: '/shipping/econt.png', alt: 'Econt' },
  speedy: { src: '/shipping/speedy.png', alt: 'Speedy' },
  box_now: { src: '/shipping/box-now.svg', alt: 'BOX NOW' },
};

interface DeliveryStepProps {
  address: ShippingAddress;
  onAddressChange: <K extends keyof ShippingAddress>(field: K, value: ShippingAddress[K]) => void;
  settlements: Settlement[] | null;
  isLoadingSettlements: boolean;
  settlementsError: string | null;
  customer: CustomerInfo;
  onCustomerChange: <K extends keyof CustomerInfo>(field: K, value: CustomerInfo[K]) => void;
  wantsInvoice: boolean;
  onToggleWantsInvoice: (value: boolean) => void;
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
  onSelectOffice: (office: ShippingOffice | null) => void;
  errors: Record<string, string>;
}

function methodKey(method: ShippingMethod): string {
  return `${method.carrier}:${method.delivery_type}`;
}

interface OfficePickerProps {
  idPrefix: string;
  deliveryType: ShippingDeliveryType;
  offices: ShippingOffice[] | null;
  isLoadingOffices: boolean;
  officesError: string | null;
  selectedOfficeId: string | null;
  onSelectOffice: (office: ShippingOffice | null) => void;
  officeIdError?: string;
}

/**
 * Two cascading dropdowns (city, then office) over the already-fetched,
 * unfiltered-by-city office list for the carrier — city options are derived
 * from the data itself, so there's no separate "no offices in this city"
 * state to handle; every listed city is guaranteed to have at least one
 * office.
 */
function OfficePicker({
  idPrefix,
  deliveryType,
  offices,
  isLoadingOffices,
  officesError,
  selectedOfficeId,
  onSelectOffice,
  officeIdError,
}: OfficePickerProps) {
  const selectedOffice = offices?.find((office) => office.id === selectedOfficeId) ?? null;
  const [city, setCity] = useState(selectedOffice?.city ?? '');

  // Keeps the city dropdown in sync if the selection changes from outside
  // this component (e.g. a fresh office list comes in after switching
  // carriers, which resets selectedOfficeId to null upstream).
  useEffect(() => {
    setCity(selectedOffice?.city ?? '');
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedOfficeId, offices]);

  // Case-insensitive de-dup (keeping whichever casing is seen first) — real
  // carrier data isn't perfectly consistent (e.g. BOX NOW's own destination
  // list has both "Нови хан" and "Нови Хан"), and without this the same
  // city would appear twice under two different-looking entries.
  const cities = useMemo(() => {
    if (!offices) return [];
    const seen = new Map<string, string>();
    for (const office of offices) {
      if (!office.city || seen.has(office.city.toLowerCase())) continue;
      seen.set(office.city.toLowerCase(), office.city);
    }
    return Array.from(seen.values()).sort((a, b) => a.localeCompare(b, 'bg'));
  }, [offices]);

  const officesInCity = useMemo(
    () => (offices ?? []).filter((office) => office.city.toLowerCase() === city.toLowerCase()),
    [offices, city],
  );

  const cityOptions = useMemo(() => cities.map((cityOption) => ({ value: cityOption, label: cityOption })), [cities]);
  const officeOptions = useMemo(
    () => officesInCity.map((office) => ({ value: office.id, label: `${office.name} — ${office.address}` })),
    [officesInCity],
  );

  if (isLoadingOffices) {
    return (
      <div className="d-flex align-items-center gap-2 text-muted small py-2">
        <span className="spinner-border spinner-border-sm" role="status" aria-hidden="true" />
        {checkoutCopy.delivery.officeLoading}
      </div>
    );
  }

  if (officesError) {
    return <ErrorState message={officesError} />;
  }

  if (!offices || offices.length === 0) {
    return <div className="text-muted small">{checkoutCopy.delivery.officeEmpty}</div>;
  }

  const officeLabel = checkoutCopy.delivery.officeLabelByType[deliveryType] ?? checkoutCopy.delivery.officeLabelByType.office;
  const officePlaceholder = checkoutCopy.delivery.officePlaceholderByType[deliveryType] ?? checkoutCopy.delivery.officePlaceholderByType.office;

  return (
    <div className="row g-2">
      <div className="col-12 col-sm-6">
        <label htmlFor={`${idPrefix}-city`} className="form-label small fw-semibold">
          {checkoutCopy.delivery.officeCityLabel}
        </label>
        <SearchableSelect
          id={`${idPrefix}-city`}
          value={city}
          options={cityOptions}
          placeholder={checkoutCopy.delivery.officeCityPlaceholder}
          onChange={(nextCity) => {
            setCity(nextCity);
            onSelectOffice(null);
          }}
        />
      </div>

      <div className="col-12 col-sm-6">
        <label htmlFor={`${idPrefix}-office`} className="form-label small fw-semibold">
          {officeLabel}
        </label>
        <SearchableSelect
          id={`${idPrefix}-office`}
          value={selectedOfficeId ?? ''}
          options={officeOptions}
          placeholder={officePlaceholder}
          disabled={!city}
          invalid={Boolean(officeIdError)}
          onChange={(officeId) => {
            const office = officesInCity.find((candidate) => candidate.id === officeId);
            if (office) onSelectOffice(office);
          }}
        />
        {officeIdError && <div className="text-danger small mt-1">{officeIdError}</div>}
      </div>
    </div>
  );
}

function settlementLabel(settlement: Settlement): string {
  return `${settlement.type} ${settlement.name} (общ. ${settlement.municipality}, обл. ${settlement.region})`;
}

interface SettlementAddressPickerProps {
  idPrefix: string;
  address: ShippingAddress;
  onAddressChange: <K extends keyof ShippingAddress>(field: K, value: ShippingAddress[K]) => void;
  settlements: Settlement[] | null;
  isLoadingSettlements: boolean;
  settlementsError: string | null;
  settlementError?: string;
  addressLineError?: string;
}

/**
 * Just the two fields home delivery actually needs: which settlement (town,
 * city, or village), and the street/building details within it. Country is
 * hardcoded (Bulgaria-only storefront) and postal_code is derived from the
 * chosen settlement — see BulgarianSettlementService on the backend — so
 * neither is collected here.
 */
function SettlementAddressPicker({
  idPrefix,
  address,
  onAddressChange,
  settlements,
  isLoadingSettlements,
  settlementsError,
  settlementError,
  addressLineError,
}: SettlementAddressPickerProps) {
  const settlementOptions = useMemo(
    // searchText is just the settlement's own name — searching "Sofia" should
    // find гр. София, not every village whose municipality/region happens to
    // contain "София" (which the full disambiguated label would also match).
    () => (settlements ?? []).map((settlement) => ({ value: settlement.id, label: settlementLabel(settlement), searchText: settlement.name })),
    [settlements],
  );

  // address.city holds the full formatted label (there's no separate id
  // field on ShippingAddress) — matched back against the list to drive the
  // dropdown's selected value, including when navigating back to this step.
  const selectedSettlementId = useMemo(
    () => settlements?.find((settlement) => settlementLabel(settlement) === address.city)?.id ?? '',
    [settlements, address.city],
  );

  if (isLoadingSettlements) {
    return (
      <div className="d-flex align-items-center gap-2 text-muted small py-2">
        <span className="spinner-border spinner-border-sm" role="status" aria-hidden="true" />
        {checkoutCopy.delivery.settlementLoading}
      </div>
    );
  }

  if (settlementsError) {
    return <ErrorState message={settlementsError} />;
  }

  return (
    <div className="row g-2">
      <div className="col-12 col-sm-6">
        <label htmlFor={`${idPrefix}-settlement`} className="form-label small fw-semibold">
          {checkoutCopy.delivery.settlementLabel}
        </label>
        <SearchableSelect
          id={`${idPrefix}-settlement`}
          value={selectedSettlementId}
          options={settlementOptions}
          placeholder={checkoutCopy.delivery.settlementPlaceholder}
          invalid={Boolean(settlementError)}
          onChange={(settlementId) => {
            const settlement = settlements?.find((candidate) => candidate.id === settlementId);
            if (settlement) {
              onAddressChange('city', settlementLabel(settlement));
              onAddressChange('postal_code', settlement.postal_code);
            }
          }}
        />
        {settlementError && <div className="text-danger small mt-1">{settlementError}</div>}
      </div>

      <div className="col-12 col-sm-6">
        <FormField
          id={`${idPrefix}-address-line`}
          label={checkoutCopy.delivery.addressLineLabel}
          value={address.address_line}
          onChange={(value) => onAddressChange('address_line', value)}
          error={addressLineError}
          required
        />
      </div>
    </div>
  );
}

export default function DeliveryStep({
  address,
  onAddressChange,
  settlements,
  isLoadingSettlements,
  settlementsError,
  customer,
  onCustomerChange,
  wantsInvoice,
  onToggleWantsInvoice,
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
  const isAddressDelivery = selectedMethod?.delivery_type === 'address';
  const isOfficeDelivery = selectedMethod?.requires_office ?? false;

  return (
    <div>
      <h2 className="h6 mb-3">{checkoutCopy.delivery.title}</h2>

      {isLoadingShippingMethods && <LoadingState message={checkoutCopy.delivery.loading} />}
      {!isLoadingShippingMethods && shippingMethodsError && <ErrorState message={shippingMethodsError} />}

      {!isLoadingShippingMethods && shippingMethods && (
        <div className="d-flex flex-column gap-2">
          {shippingMethods.map((method) => {
            const isSelected = selectedMethod !== null && methodKey(selectedMethod) === methodKey(method);
            const logo = CARRIER_LOGOS[method.carrier];

            return (
              <div key={methodKey(method)} className={`shipping-option-wrapper ${isSelected ? 'is-selected' : ''}`}>
                <label className="shipping-option">
                  <input
                    type="radio"
                    name="shipping_method"
                    className="form-check-input mt-0"
                    checked={isSelected}
                    onChange={() => onSelectMethod(method)}
                  />
                  <span className="shipping-carrier-logo">
                    <img src={logo.src} alt={logo.alt} />
                  </span>
                  <span className="flex-grow-1">
                    <span className="shipping-option__label d-block">{method.label}</span>
                    <span className="shipping-option__hint d-block">{method.description}</span>
                    <span className="shipping-option__hint d-block">
                      {checkoutCopy.delivery.estimatedDeliveryPrefix} {method.estimated_delivery}
                    </span>
                  </span>
                  <span className="fw-semibold">{formatPrice(method.price, method.currency as 'EUR')}</span>
                </label>

                {isSelected && method.requires_office && (
                  <div className="shipping-office-panel">
                    <OfficePicker
                      idPrefix={`shipping-office-${methodKey(method)}`}
                      deliveryType={method.delivery_type}
                      offices={offices}
                      isLoadingOffices={isLoadingOffices}
                      officesError={officesError}
                      selectedOfficeId={selectedOfficeId}
                      onSelectOffice={onSelectOffice}
                      officeIdError={errors.shipping_office_id}
                    />
                  </div>
                )}

                {isSelected && method.delivery_type === 'address' && (
                  <div className="shipping-office-panel">
                    <SettlementAddressPicker
                      idPrefix={`shipping-address-${methodKey(method)}`}
                      address={address}
                      onAddressChange={onAddressChange}
                      settlements={settlements}
                      isLoadingSettlements={isLoadingSettlements}
                      settlementsError={settlementsError}
                      settlementError={errors['address.city']}
                      addressLineError={errors['address.address_line']}
                    />
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}

      {errors.shipping_carrier && <div className="text-danger small mt-2">{errors.shipping_carrier}</div>}

      {selectedMethod && (
        <>
          <div className="form-check mb-3 mt-4">
            <input
              type="checkbox"
              className="form-check-input"
              id="checkout-wants-invoice"
              checked={wantsInvoice}
              onChange={(event) => onToggleWantsInvoice(event.target.checked)}
            />
            <label htmlFor="checkout-wants-invoice" className="form-check-label">
              {checkoutCopy.customer.wantsInvoice}
            </label>
          </div>

          {/* Company/VAT number and billing address only exist to support
              an invoice — nothing to show here at all unless the customer
              opted into one above. */}
          {wantsInvoice && (
            <>
              <div className="row">
                <div className="col-12 col-sm-6 mb-3">
                  <FormField
                    id="checkout-company"
                    label={checkoutCopy.customer.company}
                    value={customer.company}
                    onChange={(value) => onCustomerChange('company', value)}
                    error={errors['customer.company']}
                    required
                  />
                </div>
                <div className="col-12 col-sm-6 mb-3">
                  <FormField
                    id="checkout-vat-number"
                    label={checkoutCopy.customer.vatNumber}
                    value={customer.vat_number}
                    onChange={(value) => onCustomerChange('vat_number', value)}
                    error={errors['customer.vat_number']}
                    required
                  />
                </div>
              </div>

              <h2 className="h6 mb-3">{checkoutCopy.billing.title}</h2>

              {isAddressDelivery && (
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
              )}

              {/* Office/locker pickup has no shipping address to copy, so
                  billing details are always collected on their own there —
                  only the address-delivery case gets the shortcut checkbox
                  above. */}
              {(isOfficeDelivery || !billingSameAsShipping) && (
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
                    <div className="col-12 col-sm-6 mb-4">
                      <FormField
                        id="checkout-billing-postal-code"
                        label={checkoutCopy.address.postalCode}
                        value={billingAddress.postal_code}
                        onChange={(value) => onBillingAddressChange('postal_code', value)}
                        error={errors['billing_address.postal_code']}
                        required
                      />
                    </div>
                    <div className="col-12 col-sm-6 mb-4">
                      <FormField
                        id="checkout-billing-address-line"
                        label={checkoutCopy.address.addressLine}
                        value={billingAddress.address_line}
                        onChange={(value) => onBillingAddressChange('address_line', value)}
                        error={errors['billing_address.address_line']}
                        required
                      />
                    </div>
                  </div>
                </>
              )}
            </>
          )}
        </>
      )}
    </div>
  );
}
