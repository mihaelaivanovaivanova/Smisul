import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useCart } from '../hooks/useCart';
import { useAuth } from '../hooks/useAuth';
import { useAsync } from '../hooks/useAsync';
import * as checkoutApi from '../api/checkout';
import { fetchShippingMethods, fetchLegalDocuments } from '../api/checkout';
import { getErrorMessage, getValidationErrors } from '../api/errors';
import LoadingState from '../components/LoadingState';
import ErrorState from '../components/ErrorState';
import EmptyState from '../components/EmptyState';
import Alert from '../components/Alert';
import Seo from '../components/Seo';
import Breadcrumbs from '../components/Breadcrumbs';
import StepIndicator from '../components/checkout/StepIndicator';
import CustomerInfoStep from '../components/checkout/CustomerInfoStep';
import DeliveryStep from '../components/checkout/DeliveryStep';
import OrderReviewStep from '../components/checkout/OrderReviewStep';
import CheckoutSummary from '../components/checkout/CheckoutSummary';
import { breadcrumbLabels, checkout as checkoutCopy } from '../content/copy';
import type { CustomerInfo, ShippingAddress, ShippingCarrier } from '../types/checkout';

const STEP_LABELS = [checkoutCopy.steps.customer, checkoutCopy.steps.delivery, checkoutCopy.steps.review];

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export default function CheckoutPage() {
  const navigate = useNavigate();
  const { cart, isLoading: isCartLoading, error: cartError, refresh: refreshCart } = useCart();
  const { user } = useAuth();

  const [step, setStep] = useState(0);
  const [customer, setCustomer] = useState<CustomerInfo>({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    company: '',
    vat_number: '',
  });
  const [deliveryNotes, setDeliveryNotes] = useState('');
  const [address, setAddress] = useState<ShippingAddress>({
    country: 'България',
    city: '',
    postal_code: '',
    address_line: '',
    apartment: '',
  });
  const [billingSameAsShipping, setBillingSameAsShipping] = useState(true);
  const [billingAddress, setBillingAddress] = useState<ShippingAddress>({
    country: 'България',
    city: '',
    postal_code: '',
    address_line: '',
    apartment: '',
  });
  const [shippingCarrier, setShippingCarrier] = useState<ShippingCarrier | null>(null);
  const [acceptedLegalDocumentIds, setAcceptedLegalDocumentIds] = useState<number[]>([]);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const { data: shippingMethods, isLoading: isLoadingShippingMethods, error: shippingMethodsError } = useAsync(
    fetchShippingMethods,
    [],
    checkoutCopy.delivery.loadError,
  );
  const { data: legalDocuments, isLoading: isLoadingLegalDocuments, error: legalDocumentsError } = useAsync(
    fetchLegalDocuments,
    [],
    checkoutCopy.legal.loadError,
  );

  // Prefills whatever the account already knows once it loads, without
  // clobbering anything the customer has already typed themselves.
  useEffect(() => {
    if (!user) {
      return;
    }

    setCustomer((prev) => ({
      ...prev,
      first_name: prev.first_name || user.first_name,
      last_name: prev.last_name || user.last_name,
      email: prev.email || user.email,
      phone: prev.phone || user.phone || '',
    }));
  }, [user]);

  function updateCustomer<K extends keyof CustomerInfo>(field: K, value: CustomerInfo[K]): void {
    setCustomer((prev) => ({ ...prev, [field]: value }));
  }

  function updateAddress<K extends keyof ShippingAddress>(field: K, value: ShippingAddress[K]): void {
    setAddress((prev) => ({ ...prev, [field]: value }));
  }

  function updateBillingAddress<K extends keyof ShippingAddress>(field: K, value: ShippingAddress[K]): void {
    setBillingAddress((prev) => ({ ...prev, [field]: value }));
  }

  function toggleLegalDocument(documentId: number): void {
    setAcceptedLegalDocumentIds((prev) =>
      prev.includes(documentId) ? prev.filter((id) => id !== documentId) : [...prev, documentId],
    );
  }

  function validateCustomerStep(): boolean {
    const stepErrors: Record<string, string> = {};

    if (!customer.first_name.trim()) stepErrors['customer.first_name'] = checkoutCopy.errors.firstNameRequired;
    if (!customer.last_name.trim()) stepErrors['customer.last_name'] = checkoutCopy.errors.lastNameRequired;
    if (!EMAIL_PATTERN.test(customer.email)) stepErrors['customer.email'] = checkoutCopy.errors.emailRequired;
    if (!customer.phone.trim()) stepErrors['customer.phone'] = checkoutCopy.errors.phoneRequired;

    setErrors(stepErrors);
    return Object.keys(stepErrors).length === 0;
  }

  function validateDeliveryStep(): boolean {
    const stepErrors: Record<string, string> = {};

    if (!address.country.trim()) stepErrors['address.country'] = checkoutCopy.errors.countryRequired;
    if (!address.city.trim()) stepErrors['address.city'] = checkoutCopy.errors.cityRequired;
    if (!address.postal_code.trim()) stepErrors['address.postal_code'] = checkoutCopy.errors.postalCodeRequired;
    if (!address.address_line.trim()) stepErrors['address.address_line'] = checkoutCopy.errors.addressLineRequired;
    if (!shippingCarrier) stepErrors.shipping_carrier = checkoutCopy.errors.shippingMethodRequired;

    if (!billingSameAsShipping) {
      if (!billingAddress.country.trim()) stepErrors['billing_address.country'] = checkoutCopy.errors.countryRequired;
      if (!billingAddress.city.trim()) stepErrors['billing_address.city'] = checkoutCopy.errors.cityRequired;
      if (!billingAddress.postal_code.trim()) stepErrors['billing_address.postal_code'] = checkoutCopy.errors.postalCodeRequired;
      if (!billingAddress.address_line.trim()) stepErrors['billing_address.address_line'] = checkoutCopy.errors.addressLineRequired;
    }

    setErrors(stepErrors);
    return Object.keys(stepErrors).length === 0;
  }

  function handleNext(): void {
    const isValid = step === 0 ? validateCustomerStep() : validateDeliveryStep();

    if (isValid) {
      setErrors({});
      setStep((prev) => prev + 1);
    }
  }

  function handleBack(): void {
    setErrors({});
    setStep((prev) => prev - 1);
  }

  async function handlePlaceOrder(): Promise<void> {
    if (!shippingCarrier || !legalDocuments) {
      return;
    }

    const allDocumentsAccepted = legalDocuments.every((document) => acceptedLegalDocumentIds.includes(document.id));

    if (!allDocumentsAccepted) {
      setErrors({ legal_document_ids: checkoutCopy.errors.legalRequired });
      return;
    }

    setIsSubmitting(true);
    setSubmitError(null);
    setErrors({});

    try {
      const { order, guestAccessToken, payment } = await checkoutApi.placeOrder({
        customer: {
          first_name: customer.first_name,
          last_name: customer.last_name,
          email: customer.email,
          phone: customer.phone,
          company: customer.company || undefined,
          vat_number: customer.vat_number || undefined,
        },
        address: {
          country: address.country,
          city: address.city,
          postal_code: address.postal_code,
          address_line: address.address_line,
          apartment: address.apartment || undefined,
        },
        billing_same_as_shipping: billingSameAsShipping,
        billing_address: billingSameAsShipping
          ? undefined
          : {
              country: billingAddress.country,
              city: billingAddress.city,
              postal_code: billingAddress.postal_code,
              address_line: billingAddress.address_line,
              apartment: billingAddress.apartment || undefined,
            },
        delivery_notes: deliveryNotes || undefined,
        shipping_carrier: shippingCarrier,
        legal_document_ids: acceptedLegalDocumentIds,
      });

      await refreshCart();

      if (payment.redirect_url) {
        // Leaving the SPA entirely — the customer enters their card
        // details on iCard's own hosted page, never ours (see the
        // sprint's "no card data" requirement). isSubmitting stays true
        // so the button shows a spinner during the brief moment before
        // the browser actually navigates away.
        window.location.href = payment.redirect_url;
        return;
      }

      navigate(`/order-confirmation/${order.id}`, { state: { order, guestAccessToken } });
    } catch (error) {
      setErrors(getValidationErrors(error));
      setSubmitError(getErrorMessage(error, checkoutCopy.errors.placeOrderFailed));
      setIsSubmitting(false);
    }
  }

  const selectedShippingMethod = shippingMethods?.find((method) => method.carrier === shippingCarrier) ?? null;

  return (
    <div className="container py-4">
      <Seo title={checkoutCopy.seoTitle} description={checkoutCopy.seoDescription} />
      <Breadcrumbs items={[{ label: breadcrumbLabels.home, to: '/' }, { label: checkoutCopy.title }]} />
      <h1 className="mb-4 mt-3">{checkoutCopy.title}</h1>

      {isCartLoading && <LoadingState message={checkoutCopy.confirmation.loading} />}
      {!isCartLoading && cartError && <ErrorState message={cartError} />}

      {!isCartLoading && !cartError && cart && cart.items.length === 0 && (
        <EmptyState title={checkoutCopy.emptyCartTitle} message={checkoutCopy.emptyCartMessage} />
      )}

      {!isCartLoading && !cartError && cart && cart.items.length > 0 && (
        <div className="row g-4">
          <div className="col-12 col-lg-8">
            <StepIndicator steps={STEP_LABELS} currentStep={step} />

            <div className="card shadow-sm">
              <div className="card-body">
                {submitError && <Alert variant="danger">{submitError}</Alert>}

                {step === 0 && (
                  <CustomerInfoStep
                    customer={customer}
                    deliveryNotes={deliveryNotes}
                    onCustomerChange={updateCustomer}
                    onDeliveryNotesChange={setDeliveryNotes}
                    errors={errors}
                  />
                )}

                {step === 1 && (
                  <DeliveryStep
                    address={address}
                    onAddressChange={updateAddress}
                    billingSameAsShipping={billingSameAsShipping}
                    onToggleBillingSameAsShipping={setBillingSameAsShipping}
                    billingAddress={billingAddress}
                    onBillingAddressChange={updateBillingAddress}
                    shippingMethods={shippingMethods}
                    isLoadingShippingMethods={isLoadingShippingMethods}
                    shippingMethodsError={shippingMethodsError}
                    selectedCarrier={shippingCarrier}
                    onSelectCarrier={setShippingCarrier}
                    errors={errors}
                  />
                )}

                {step === 2 && (
                  <OrderReviewStep
                    cart={cart}
                    customer={customer}
                    address={address}
                    billingSameAsShipping={billingSameAsShipping}
                    billingAddress={billingAddress}
                    shippingMethod={selectedShippingMethod}
                    legalDocuments={legalDocuments}
                    isLoadingLegalDocuments={isLoadingLegalDocuments}
                    legalDocumentsError={legalDocumentsError}
                    acceptedLegalDocumentIds={acceptedLegalDocumentIds}
                    onToggleLegalDocument={toggleLegalDocument}
                    errors={errors}
                  />
                )}

                <div className="d-flex justify-content-between mt-4">
                  {step > 0 ? (
                    <button type="button" className="btn btn-outline-secondary" onClick={handleBack} disabled={isSubmitting}>
                      {checkoutCopy.back}
                    </button>
                  ) : (
                    <span />
                  )}

                  {step < 2 && (
                    <button type="button" className="btn btn-primary" onClick={handleNext}>
                      {checkoutCopy.next}
                    </button>
                  )}

                  {step === 2 && (
                    <button
                      type="button"
                      className="btn btn-primary"
                      onClick={() => void handlePlaceOrder()}
                      disabled={isSubmitting}
                    >
                      {isSubmitting && <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" />}
                      {isSubmitting ? checkoutCopy.placingOrder : checkoutCopy.placeOrder}
                    </button>
                  )}
                </div>
              </div>
            </div>
          </div>

          <div className="col-12 col-lg-4">
            <CheckoutSummary cart={cart} shippingMethod={selectedShippingMethod} />
          </div>
        </div>
      )}
    </div>
  );
}
