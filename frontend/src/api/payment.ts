import { apiBaseUrl, apiClient, ensureCsrfCookie } from './client';
import type { Payment, PaymentMethodValue } from '../types/payment';

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

/** Called once the in-page iCard modal reports success/failure — logs the outcome and reconciles with the gateway if no webhook has landed yet. */
export async function recordPaymentReturn(orderId: number, token?: string | null): Promise<Payment> {
  await ensureCsrfCookie();
  const { data } = await apiClient.post<PaymentResponse>(
    `/payments/${orderId}/return`,
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

/**
 * The absolute URLs for the two wallet-SDK-driven endpoints (Apple Pay
 * merchant validation / tokenized purchase) — the wallet SDK (ICardIpgGAPay)
 * calls these directly with its own fetch/XHR, not through apiClient, so it
 * needs full URLs rather than apiClient's relative paths. See
 * components/checkout/IcardWalletButtons.tsx.
 */
export function walletEndpointUrls(orderId: number, token?: string | null): { tokenProviderSessionUrl: string; processPaymentUrl: string } {
  const query = token ? `?token=${encodeURIComponent(token)}` : '';

  return {
    tokenProviderSessionUrl: `${apiBaseUrl}/payments/${orderId}/wallet/token-provider-session${query}`,
    processPaymentUrl: `${apiBaseUrl}/payments/${orderId}/wallet/tokenized-card-purchase${query}`,
  };
}

const MODAL_SCRIPT_ID = 'ipg-io-js';
const MODAL_CONTAINER_ID = 'ipg';
const MODAL_LOAD_TIMEOUT_MS = 30000;

/**
 * Injects iCard's own hosted JS (given a token from IPGPaymentToken), which
 * renders a payment overlay directly into the page's #ipg container — the
 * embedded-modal equivalent of what used to be a full-page redirect (see
 * components/checkout/IcardModal.tsx). Resolves once iCard's script reports
 * the modal actually rendered (`ipg.formload.success`); rejects on a load
 * error or timeout, mirroring the DOM CustomEvents iCard's script dispatches
 * on `document`.
 */
export function loadIcardModalScript(modalJsUrl: string, token: string, theme: string): Promise<void> {
  return new Promise((resolve, reject) => {
    document.getElementById(MODAL_SCRIPT_ID)?.remove();
    const container = document.getElementById(MODAL_CONTAINER_ID);
    if (container) {
      container.innerHTML = '';
    }

    let settled = false;

    const finish = (error?: Error) => {
      if (settled) return;
      settled = true;
      window.clearTimeout(timeout);
      document.removeEventListener('ipg.formload.success', handleLoadSuccess);
      document.removeEventListener('ipg.loadmodal.error', handleLoadError);
      document.removeEventListener('ipg.error', handleLoadError);

      if (error) {
        reject(error);
      } else {
        resolve();
      }
    };

    const handleLoadSuccess = () => finish();
    const handleLoadError = () => finish(new Error('iCard modal failed to load.'));

    const timeout = window.setTimeout(() => {
      finish(new Error('iCard modal did not load in time.'));
    }, MODAL_LOAD_TIMEOUT_MS);

    document.addEventListener('ipg.formload.success', handleLoadSuccess);
    document.addEventListener('ipg.loadmodal.error', handleLoadError);
    document.addEventListener('ipg.error', handleLoadError);

    const script = document.createElement('script');
    script.id = MODAL_SCRIPT_ID;
    script.async = true;
    script.src = `${modalJsUrl}?token=${encodeURIComponent(token)}&theme=${encodeURIComponent(theme)}`;
    script.onerror = () => finish(new Error('iCard modal script could not be loaded.'));
    document.body.appendChild(script);
  });
}
