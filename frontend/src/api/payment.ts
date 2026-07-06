import { apiClient, ensureCsrfCookie } from './client';
import type { Payment, PaymentMethodValue } from '../types/payment';

/**
 * iCard's IPG API has no "give me a redirect URL" API call — createSession()
 * on the backend builds an RSA-signed field set that the customer's own
 * browser must POST directly to iCard (see ICardPaymentGateway::createSession
 * and PaymentSessionData). This builds and submits that form, which
 * navigates the browser away from the SPA entirely.
 */
export function redirectToGateway(payment: Payment): void {
  if (!payment.redirect_url || !payment.form_fields) {
    return;
  }

  const form = document.createElement('form');
  form.method = 'POST';
  form.action = payment.redirect_url;
  form.style.display = 'none';

  for (const [name, value] of Object.entries(payment.form_fields)) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    form.appendChild(input);
  }

  document.body.appendChild(form);
  form.submit();
}

interface PaymentResponse {
  data: Payment;
}

/**
 * `token` is the order's guest_access_token — required for a guest to
 * check on their own payment (see PaymentController::authorizeAccess on
 * the backend); omitted for authenticated customers, whose ownership is
 * checked via their session instead. Every function below takes it for
 * the same reason.
 */
export async function fetchPaymentStatus(orderId: number, token?: string | null): Promise<Payment> {
  const { data } = await apiClient.get<PaymentResponse>(`/payments/${orderId}/status`, {
    params: token ? { token } : undefined,
  });
  return data.data;
}

/** Called once when the success/failed page mounts — logs the return and reconciles with the gateway if no webhook has landed yet. */
export async function recordPaymentReturn(orderId: number, token?: string | null): Promise<Payment> {
  await ensureCsrfCookie();
  const { data } = await apiClient.post<PaymentResponse>(
    `/payments/${orderId}/return`,
    {},
    { params: token ? { token } : undefined },
  );
  return data.data;
}

/** Called by the cancelled page on mount, or by a customer-initiated "cancel" action before completing payment. */
export async function cancelPayment(orderId: number, token?: string | null): Promise<Payment> {
  await ensureCsrfCookie();
  const { data } = await apiClient.post<PaymentResponse>(
    `/payments/${orderId}/cancel`,
    {},
    { params: token ? { token } : undefined },
  );
  return data.data;
}

/** Retries payment for an order whose previous attempt was Failed/Cancelled, optionally with a different payment method. */
export async function initiatePayment(orderId: number, token?: string | null, paymentMethod?: PaymentMethodValue): Promise<Payment> {
  await ensureCsrfCookie();
  const { data } = await apiClient.post<PaymentResponse>(
    `/payments/${orderId}/initiate`,
    paymentMethod ? { payment_method: paymentMethod } : {},
    { params: token ? { token } : undefined },
  );
  return data.data;
}
