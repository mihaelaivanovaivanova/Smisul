import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useCart } from '../hooks/useCart';
import { useAuth } from '../hooks/useAuth';
import { useAsync } from '../hooks/useAsync';
import * as checkoutApi from '../api/checkout';
import { fetchShippingMethods, fetchShippingOffices, fetchLegalDocuments, fetchSettlements } from '../api/checkout';
import { initiatePayment, recordPaymentReturn } from '../api/payment';
import { trackBeginCheckout } from '../services/analytics';
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
import type { CustomerInfo, Settlement, ShippingAddress, ShippingCarrier, ShippingMethod, ShippingOffice } from '../types/checkout';
import type { Payment, PaymentMethodValue } from '../types/payment';

// Every carrier this storefront supports — used to prefetch all of their
// office/locker lists up front rather than one at a time as the customer
// switches methods.
const ALL_CARRIERS: ShippingCarrier[] = ['econt', 'speedy', 'box_now'];

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

  // InitiateCheckout: once per checkout visit, as soon as the cart total
  // is known (cart is null while it loads, so the truthiness gate also
  // defers the event until there's a real value to report). A ref rather
  // than state so StrictMode's dev-only double effect invocation can't
  // fire it twice — both setups would still see un-rendered state.
  const checkoutTracked = useRef(false);
  useEffect(() => {
    if (!checkoutTracked.current && cart && cart.items.length > 0) {
      checkoutTracked.current = true;
      trackBeginCheckout(cart.totals.grand_total, cart.totals.currency);
    }
  }, [cart]);
  const [customer, setCustomer] = useState<CustomerInfo>({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    company: '',
    vat_number: '',
  });
  const [wantsInvoice, setWantsInvoice] = useState(false);
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
  const [officesByCarrier, setOfficesByCarrier] = useState<Partial<Record<ShippingCarrier, ShippingOffice[]>>>({});
  const [officesErrorByCarrier, setOfficesErrorByCarrier] = useState<Partial<Record<ShippingCarrier, string>>>({});
  const [isLoadingOffices, setIsLoadingOffices] = useState(true);
  const [settlements, setSettlements] = useState<Settlement[] | null>(null);
  const [isLoadingSettlements, setIsLoadingSettlements] = useState(true);
  const [settlementsError, setSettlementsError] = useState<string | null>(null);
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

  // BOX NOW is the default shipping method — pre-selected as soon as the
  // catalog loads, but only ever once: this must not clobber a method the
  // customer has since deliberately picked (including switching away from
  // BOX NOW itself).
  const defaultMethodApplied = useRef(false);
  useEffect(() => {
    if (defaultMethodApplied.current || !shippingMethods) return;

    const boxNow = shippingMethods.find((method) => method.carrier === 'box_now');
    if (boxNow) {
      defaultMethodApplied.current = true;
      setSelectedMethod(boxNow);
    }
  }, [shippingMethods]);

  // Every carrier's office/locker list is prefetched in parallel as soon as
  // the checkout page mounts — not lazily as the customer picks a method —
  // so switching between shipping options never shows a loading spinner in
  // the middle of the flow; the dropdowns are already populated by the time
  // the customer reaches the delivery step.
  useEffect(() => {
    let isMounted = true;

    Promise.all(
      ALL_CARRIERS.map((carrier) =>
        fetchShippingOffices(carrier)
          .then((result) => ({ carrier, offices: result, error: null as string | null }))
          .catch((error: unknown) => ({
            carrier,
            offices: [] as ShippingOffice[],
            error: getErrorMessage(error, checkoutCopy.delivery.officeLoadError),
          })),
      ),
    )
      .then((results) => {
        if (!isMounted) return;

        const byCarrier: Partial<Record<ShippingCarrier, ShippingOffice[]>> = {};
        const errorsByCarrier: Partial<Record<ShippingCarrier, string>> = {};
        for (const { carrier, offices: result, error } of results) {
          byCarrier[carrier] = result;
          if (error) errorsByCarrier[carrier] = error;
        }
        setOfficesByCarrier(byCarrier);
        setOfficesErrorByCarrier(errorsByCarrier);
      })
      .finally(() => {
        if (isMounted) setIsLoadingOffices(false);
      });

    return () => {
      isMounted = false;
    };
  }, []);

  // The nationwide settlement list (~1MB, effectively static) is prefetched
  // the same way, once on mount, instead of lazily the first time address
  // delivery is selected.
  useEffect(() => {
    let isMounted = true;

    fetchSettlements()
      .then((result) => {
        if (isMounted) setSettlements(result);
      })
      .catch((error: unknown) => {
        if (isMounted) setSettlementsError(getErrorMessage(error, checkoutCopy.delivery.settlementLoadError));
      })
      .finally(() => {
        if (isMounted) setIsLoadingSettlements(false);
      });

    return () => {
      isMounted = false;
    };
  }, []);

  function handleSelectMethod(method: ShippingMethod): void {
    setSelectedMethod(method);
    setSelectedOffice(null);
  }

  // Billing address only exists to support an invoice, so it's collected at
  // all only when the customer opted into one on the customer-info step —
  // and once it is collected, office/locker pickup has no shipping address
  // to copy into it, so the "same as shipping" shortcut must not stay on
  // (with billing details silently blank) for that case. Runs as an effect
  // rather than only inside handleSelectMethod so it stays correct
  // regardless of whether the invoice opt-in or the method is picked first.
  useEffect(() => {
    if (wantsInvoice && selectedMethod?.requires_office) {
      setBillingSameAsShipping(false);
    }
  }, [wantsInvoice, selectedMethod?.requires_office]);

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

    if (!selectedMethod) stepErrors.shipping_carrier = checkoutCopy.errors.shippingMethodRequired;
    if (selectedMethod?.requires_office && !selectedOffice) stepErrors.shipping_office_id = checkoutCopy.delivery.officeRequired;

    // Home delivery is the only method that collects a street address —
    // office/locker pickup has nothing here to validate. Country is
    // hardcoded and postal_code is derived from the chosen settlement, so
    // only the settlement itself (address.city) and the free-text address
    // line are ever actually unset here.
    if (selectedMethod?.delivery_type === 'address') {
      if (!address.city.trim()) stepErrors['address.city'] = checkoutCopy.errors.settlementRequired;
      if (!address.address_line.trim()) stepErrors['address.address_line'] = checkoutCopy.errors.addressLineRequired;
    }

    // Company/VAT number and billing address only exist to support an
    // invoice — an unissued invoice has nothing to validate.
    if (wantsInvoice) {
      if (!customer.company.trim()) stepErrors['customer.company'] = checkoutCopy.errors.companyRequired;
      if (!customer.vat_number.trim()) stepErrors['customer.vat_number'] = checkoutCopy.errors.vatNumberRequired;
    }

    if (wantsInvoice && (selectedMethod?.requires_office || !billingSameAsShipping)) {
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

    // Billing address only exists to support an invoice — collected (and
    // sent) at all only when the customer opted into one; mirrors
    // validateDeliveryStep's condition for when it's shown/required.
    const needsBillingAddress = wantsInvoice && (selectedMethod.requires_office || !billingSameAsShipping);

    try {
      const { order, guestAccessToken, payment } = await checkoutApi.placeOrder({
        customer: {
          first_name: customer.first_name,
          last_name: customer.last_name,
          email: customer.email,
          phone: customer.phone,
          // Left over text from before the customer unchecked the invoice
          // opt-in must not be submitted as if it were still requested.
          company: wantsInvoice ? customer.company || undefined : undefined,
          vat_number: wantsInvoice ? customer.vat_number || undefined : undefined,
        },
        address: {
          country: address.country,
          city: address.city,
          postal_code: address.postal_code,
          address_line: address.address_line,
          apartment: address.apartment || undefined,
        },
        wants_invoice: wantsInvoice,
        billing_same_as_shipping: !needsBillingAddress,
        billing_address: needsBillingAddress
          ? {
              country: billingAddress.country,
              city: billingAddress.city,
              postal_code: billingAddress.postal_code,
              address_line: billingAddress.address_line,
              apartment: billingAddress.apartment || undefined,
            }
          : undefined,
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
  const offices = selectedMethod ? officesByCarrier[selectedMethod.carrier] ?? null : null;
  const officesError = selectedMethod ? officesErrorByCarrier[selectedMethod.carrier] ?? null : null;

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
                    onCustomerChange={updateCustomer}
                    errors={errors}
                  />
                )}

                {step === 1 && (
                  <DeliveryStep
                    address={address}
                    onAddressChange={updateAddress}
                    settlements={settlements}
                    isLoadingSettlements={isLoadingSettlements}
                    settlementsError={settlementsError}
                    customer={customer}
                    onCustomerChange={updateCustomer}
                    wantsInvoice={wantsInvoice}
                    onToggleWantsInvoice={setWantsInvoice}
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
