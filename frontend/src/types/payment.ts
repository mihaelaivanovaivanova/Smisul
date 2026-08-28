export type PaymentStatus =
  | 'pending'
  | 'initiated'
  | 'authorized'
  | 'paid'
  | 'failed'
  | 'cancelled'
  | 'expired'
  | 'refunded';

export type PaymentMethodValue = 'card';

/**
 * Payment.payment_method reflects whatever an order/payment was actually
 * placed with historically, which can include methods no longer
 * selectable at checkout (see PaymentMethodValue) — cash on delivery was
 * removed as a live option, but existing payments still carry it (see the
 * backend's PaymentMethod::CashOnDelivery doc comment for why the value
 * itself is never deleted, only stopped from being newly selectable).
 */
export type HistoricalPaymentMethodValue = PaymentMethodValue | 'cash_on_delivery';

export interface PaymentMethodOption {
  value: PaymentMethodValue;
  label: string;
  /** Always true today — every method the backend returns is enabled (see PaymentService::availablePaymentMethods). Kept as a field rather than dropped in case a future method is ever listed disabled. */
  available: boolean;
}

export interface PaymentModalSession {
  token: string;
  modal_js_url: string;
  theme: string;
}

export interface Payment {
  id: number;
  order_id: number;
  provider: string;
  payment_method: HistoricalPaymentMethodValue;
  status: PaymentStatus;
  amount: number;
  currency: string;
  gateway_environment: string | null;
  gateway_transaction_reference?: string | null;
  operation_status?: string | null;
  operation_code?: string | null;
  operation_message?: string | null;
  approval_code?: string | null;
  masked_pan?: string | null;
  card_type?: string | null;
  cardholder_name?: string | null;
  refunded_amount: number;
  modal_session: PaymentModalSession | null;
  initiated_at: string | null;
  completed_at: string | null;
  paid_at: string | null;
  reversed_at: string | null;
  refunded_at: string | null;
  callback_logs?: PaymentCallbackLog[];
  transactions?: PaymentTransactionLog[];
}

export interface PaymentCallbackLog {
  id: number;
  event_type: string | null;
  provider_reference: string | null;
  signature_valid: boolean;
  error_message: string | null;
  payload: Record<string, unknown>;
  processed_at: string | null;
  created_at: string | null;
}

export interface PaymentTransactionLog {
  id: number;
  type: string;
  status: PaymentStatus;
  payload: Record<string, unknown> | null;
  created_at: string | null;
}

export interface StoredPaymentMethod {
  id: number;
  brand: string | null;
  last_four: string | null;
  expiry_month: string | null;
  expiry_year: string | null;
}
