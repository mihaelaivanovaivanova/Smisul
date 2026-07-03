import { apiClient, ensureCsrfCookie } from './client';
import type { Payment } from '../types/payment';

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

/** Retries payment for an order whose previous attempt was Failed/Cancelled. */
export async function initiatePayment(orderId: number, token?: string | null): Promise<Payment> {
  await ensureCsrfCookie();
  const { data } = await apiClient.post<PaymentResponse>(
    `/payments/${orderId}/initiate`,
    {},
    { params: token ? { token } : undefined },
  );
  return data.data;
}
