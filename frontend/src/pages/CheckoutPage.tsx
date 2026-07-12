import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useCart } from '../hooks/useCart';
import { useAuth } from '../hooks/useAuth';
import { useAsync } from '../hooks/useAsync';
import * as checkoutApi from '../api/checkout';
import { fetchShippingMethods, fetchShippingOffices, fetchLegalDocuments } from '../api/checkout';
import { initiatePayment, recordPaymentReturn } from '../api/payment';
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
import PaymentStep from '../components/checkout/PaymentStep';
import CheckoutSummary from '../components/checkout/CheckoutSummary';
import IcardModal from '../components/checkout/IcardModal';
import { isValidBgMobile } from '../utils/phone';
import { breadcrumbLabels, checkout as checkoutCopy } from '../content/copy';
import type { CustomerInfo, ShippingAddress, ShippingMethod, ShippingOffice } from '../types/checkout';
import type { Payment, PaymentMethodValue } from '../types/payment';

interface ActivePayment {
  orderId: number;
  guestAccessToken: string | null;
  payment: Payment;
}

const STEP_LABELS = [
  checkoutCopy.steps.customer,
  checkoutCopy.steps.delivery,
  checkoutCopy.steps.review,
  checkoutCopy.steps.payment,
];
const LAST_STEP = STEP_LABELS.length - 1;

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
  const [selectedMethod, setSelectedMethod] = useState<ShippingMethod | null>(null);
  const [selectedOffice, setSelectedOffice] = useState<ShippingOffice | null>(null);
  const [offices, setOffices] = useState<ShippingOffice[] | null>(null);
  const [isLoadingOffices, setIsLoadingOffices] = useState(false);
  const [officesError, setOfficesError] = useState<string | null>(null);
  const [acceptedLegalDocumentIds, setAcceptedLegalDocumentIds] = useState<number[]>([]);
  const [selectedPaymentMethod, setSelectedPaymentMethod] = useState<PaymentMethodValue>('card');
  const [storedPaymentMethodId, setStoredPaymentMethodId] = useState<number | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [activePayment, setActivePayment] = useState<ActivePayment | null>(null);
  const [paymentOutcome, setPaymentOutcome] = useState<'error' | 'cancelled' | null>(null);
  const [isRetryingPayment, setIsRetryingPayment] = useState(false);

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

  // Offices/lockers depend on both the chosen method (which carrier) and
  // the shipping city already typed above — refetches whenever either
  // changes, and clears out entirely for methods that don't need one
  // (Address delivery).
  useEffect(() => {
    if (!selectedMethod?.requires_office) {
      setOffices(null);
      setSelectedOffice(null);
      return;
    }

    let isMounted = true;
    setIsLoadingOffices(true);
    setOfficesError(null);

    fetchShippingOffices(selectedMethod.carrier, address.city || undefined)
      .then((result) => {
        if (isMounted) setOffices(result);
      })
      .catch((error: unknown) => {
        if (isMounted) setOfficesError(getErrorMessage(error, checkoutCopy.delivery.officeLoadError));
      })
      .finally(() => {
        if (isMounted) setIsLoadingOffices(false);
      });

    return () => {
      isMounted = false;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedMethod?.carrier, selectedMethod?.requires_office, address.city]);

  function handleSelectMethod(method: ShippingMethod): void {
    setSelectedMethod(method);
    setSelectedOffice(null);
  }

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
    if (!customer.phone.trim()) {
      stepErrors['customer.phone'] = checkoutCopy.errors.phoneRequired;
    } else if (!isValidBgMobile(customer.phone)) {
      stepErrors['customer.phone'] = checkoutCopy.errors.phoneInvalid;
    }

    setErrors(stepErrors);
    return Object.keys(stepErrors).length === 0;
  }

  function validateDeliveryStep(): boolean {
    const stepErrors: Record<string, string> = {};

    if (!address.country.trim()) stepErrors['address.country'] = checkoutCopy.errors.countryRequired;
    if (!address.city.trim()) stepErrors['address.city'] = checkoutCopy.errors.cityRequired;
    if (!address.postal_code.trim()) stepErrors['address.postal_code'] = checkoutCopy.errors.postalCodeRequired;
    if (!address.address_line.trim()) stepErrors['address.address_line'] = checkoutCopy.errors.addressLineRequired;
    if (!selectedMethod) stepErrors.shipping_carrier = checkoutCopy.errors.shippingMethodRequired;
    if (selectedMethod?.requires_office && !selectedOffice) stepErrors.shipping_office_id = checkoutCopy.delivery.officeRequired;

    if (!billingSameAsShipping) {
      if (!billingAddress.country.trim()) stepErrors['billing_address.country'] = checkoutCopy.errors.countryRequired;
      if (!billingAddress.city.trim()) stepErrors['billing_address.city'] = checkoutCopy.errors.cityRequired;
      if (!billingAddress.postal_code.trim()) stepErrors['billing_address.postal_code'] = checkoutCopy.errors.postalCodeRequired;
      if (!billingAddress.address_line.trim()) stepErrors['billing_address.address_line'] = checkoutCopy.errors.addressLineRequired;
    }

    setErrors(stepErrors);
    return Object.keys(stepErrors).length === 0;
  }

  function validateReviewStep(): boolean {
    if (!legalDocuments) {
      return false;
    }

    const allDocumentsAccepted = legalDocuments.every((document) => acceptedLegalDocumentIds.includes(document.id));

    if (!allDocumentsAccepted) {
      setErrors({ legal_document_ids: checkoutCopy.errors.legalRequired });
      return false;
    }

    return true;
  }

  function handleNext(): void {
    const validators = [validateCustomerStep, validateDeliveryStep, validateReviewStep];
    const isValid = validators[step]();

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
    if (!selectedMethod || !legalDocuments) {
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
        shipping_carrier: selectedMethod.carrier,
        shipping_delivery_type: selectedMethod.delivery_type,
        shipping_office_id: selectedOffice?.id,
        shipping_office_name: selectedOffice?.name,
        shipping_office_city: selectedOffice?.city,
        shipping_office_address: selectedOffice?.address,
        legal_document_ids: acceptedLegalDocumentIds,
        payment_method: selectedPaymentMethod,
        stored_payment_method_id: selectedPaymentMethod === 'card' ? storedPaymentMethodId ?? undefined : undefined,
      });

      await refreshCart();

      if (selectedPaymentMethod === 'cash_on_delivery') {
        navigate(`/order-confirmation/${order.id}`, { state: { guestAccessToken } });
        return;
      }

      setActivePayment({ orderId: order.id, guestAccessToken, payment });
      setIsSubmitting(false);
    } catch (error) {
      setErrors(getValidationErrors(error));

      if (selectedPaymentMethod === 'card') {
        // The real reason (e.g. "iCard API request failed: configuration
        // incomplete: mid, originator...") is too technical/internal to show
        // a customer, but swallowing it entirely makes a misconfigured
        // gateway undiagnosable from the browser — log it so it's at least
        // visible in devtools without needing server log access.
        // eslint-disable-next-line no-console
        console.error('iCard payment could not be started:', getErrorMessage(error, 'unknown error'));
        setSubmitError('Плащането не беше стартирано. Моля, опитайте отново.');
      } else {
        setSubmitError(getErrorMessage(error, checkoutCopy.errors.placeOrderFailed));
      }

      setIsSubmitting(false);
    }
  }

  async function handlePaymentSuccess(): Promise<void> {
    if (!activePayment) return;

    try {
      await recordPaymentReturn(activePayment.orderId, activePayment.guestAccessToken);
    } catch {
      // Best-effort — the confirmation page re-fetches payment status on
      // its own, so a failure here just means one fewer early reconcile.
    }

    navigate(`/order-confirmation/${activePayment.orderId}`, {
      state: { guestAccessToken: activePayment.guestAccessToken },
    });
  }

  function handlePaymentError(): void {
    setPaymentOutcome('error');
  }

  function handlePaymentCancel(): void {
    setPaymentOutcome('cancelled');
  }

  async function handleRetryPayment(): Promise<void> {
    if (!activePayment) return;

    setIsRetryingPayment(true);
    setPaymentOutcome(null);

    try {
      const payment = await initiatePayment(
        activePayment.orderId,
        activePayment.guestAccessToken,
        selectedPaymentMethod,
        selectedPaymentMethod === 'card' ? storedPaymentMethodId : null,
      );
      setActivePayment({ ...activePayment, payment });
    } catch (error) {
      setSubmitError(getErrorMessage(error, checkoutCopy.errors.placeOrderFailed));
    } finally {
      setIsRetryingPayment(false);
    }
  }

  const selectedShippingMethod = selectedMethod;

  return (
    <div className="container py-4">
      <Seo title={checkoutCopy.seoTitle} description={checkoutCopy.seoDescription} />
      <Breadcrumbs items={[{ label: breadcrumbLabels.home, to: '/' }, { label: checkoutCopy.title }]} />
      <h1 className="mb-4 mt-3">{checkoutCopy.title}</h1>

      {isCartLoading && <LoadingState message={checkoutCopy.confirmation.loading} />}
      {!isCartLoading && cartError && <ErrorState message={cartError} />}

      {!isCartLoading && !cartError && cart && cart.items.length === 0 && !activePayment && (
        <EmptyState title={checkoutCopy.emptyCartTitle} message={checkoutCopy.emptyCartMessage} />
      )}

      {!isCartLoading && !cartError && cart && (cart.items.length > 0 || activePayment) && (
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
                    selectedMethod={selectedMethod}
                    onSelectMethod={handleSelectMethod}
                    offices={offices}
                    isLoadingOffices={isLoadingOffices}
                    officesError={officesError}
                    selectedOfficeId={selectedOffice?.id ?? null}
                    onSelectOffice={setSelectedOffice}
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
                    office={selectedOffice}
                    legalDocuments={legalDocuments}
                    isLoadingLegalDocuments={isLoadingLegalDocuments}
                    legalDocumentsError={legalDocumentsError}
                    acceptedLegalDocumentIds={acceptedLegalDocumentIds}
                    onToggleLegalDocument={toggleLegalDocument}
                    errors={errors}
                  />
                )}

                {step === 3 && !activePayment && (
                  <PaymentStep
                    cart={cart}
                    shippingMethod={selectedShippingMethod}
                    selectedMethod={selectedPaymentMethod}
                    onSelectMethod={setSelectedPaymentMethod}
                    storedPaymentMethodId={storedPaymentMethodId}
                    onSelectStoredPaymentMethod={setStoredPaymentMethodId}
                  />
                )}

                {activePayment && (
                  <div>
                    <h2 className="h6 mb-3">{checkoutCopy.paymentStep.title}</h2>

                    {paymentOutcome === 'error' && <Alert variant="danger">{checkoutCopy.paymentStep.modal.paymentError}</Alert>}
                    {paymentOutcome === 'cancelled' && (
                      <Alert variant="warning">{checkoutCopy.paymentStep.modal.cancelled}</Alert>
                    )}

                    {paymentOutcome && (
                      <button
                        type="button"
                        className="btn btn-primary mb-3"
                        onClick={() => void handleRetryPayment()}
                        disabled={isRetryingPayment}
                      >
                        {isRetryingPayment && (
                          <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" />
                        )}
                        {checkoutCopy.paymentStep.modal.retry}
                      </button>
                    )}

                    {!paymentOutcome && activePayment.payment.modal_session && (
                      <IcardModal
                        session={activePayment.payment.modal_session}
                        onSuccess={() => void handlePaymentSuccess()}
                        onError={handlePaymentError}
                        onCancel={handlePaymentCancel}
                      />
                    )}

                    {/* Defensive: the order was placed but no payment
                        session came back (e.g. the gateway isn't
                        configured) — without this, the customer would see
                        a blank panel with no explanation or way forward. */}
                    {!paymentOutcome && !activePayment.payment.modal_session && (
                      <>
                        <Alert variant="danger">{checkoutCopy.paymentStep.modal.unavailable}</Alert>
                        <button
                          type="button"
                          className="btn btn-primary"
                          onClick={() => void handleRetryPayment()}
                          disabled={isRetryingPayment}
                        >
                          {isRetryingPayment && (
                            <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" />
                          )}
                          {checkoutCopy.paymentStep.modal.retry}
                        </button>
                      </>
                    )}
                  </div>
                )}

                {!activePayment && (
                  <div className="d-flex justify-content-between mt-4">
                    {step > 0 ? (
                      <button type="button" className="btn btn-outline-secondary" onClick={handleBack} disabled={isSubmitting}>
                        {checkoutCopy.back}
                      </button>
                    ) : (
                      <span />
                    )}

                    {step < LAST_STEP && (
                      <button type="button" className="btn btn-primary" onClick={handleNext}>
                        {checkoutCopy.next}
                      </button>
                    )}

                    {step === LAST_STEP && (
                      <button
                        type="button"
                        className="btn btn-primary"
                        onClick={() => void handlePlaceOrder()}
                        disabled={isSubmitting}
                      >
                        {isSubmitting && <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true" />}
                        {isSubmitting
                          ? (selectedPaymentMethod === 'card' ? 'Подготвяме защитено плащане...' : checkoutCopy.placingOrder)
                          : (selectedPaymentMethod === 'card' ? 'Плати с карта' : 'Завърши поръчката')}
                      </button>
                    )}
                  </div>
                )}
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
