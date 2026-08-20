export type PaymentStatus =
  | 'pending'
  | 'initiated'
  | 'authorized'
  | 'paid'
  | 'failed'
  | 'cancelled'
  | 'expired'
  | 'refunded';

export type PaymentMethodValue = 'cash_on_delivery' | 'card';

export interface PaymentMethodOption {
  value: PaymentMethodValue;
  label: string;
  /** false when this method is listed but not currently selectable (e.g. cash on delivery for a non-BOX-NOW carrier) — show it greyed out, not hidden. */
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
  payment_method: PaymentMethodValue;
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
