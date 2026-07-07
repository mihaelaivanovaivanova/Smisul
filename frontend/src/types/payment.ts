export type PaymentStatus =
  | 'pending'
  | 'initiated'
  | 'authorized'
  | 'paid'
  | 'failed'
  | 'cancelled'
  | 'expired'
  | 'refunded';

export type PaymentMethodValue = 'card' | 'apple_pay' | 'google_pay';

export interface PaymentMethodOption {
  value: PaymentMethodValue;
  label: string;
}

export interface PaymentModalSession {
  token: string;
  modal_js_url: string;
  theme: string;
}

export interface PaymentWalletSession {
  wallet_js_url: string;
  environment: 'sandbox' | 'prod';
  mid: string;
  mid_name: string;
  currency_alpha: string;
  apple_merchant_domain: string | null;
  google_merchant_id: string | null;
}

export interface Payment {
  id: number;
  order_id: number;
  provider: string;
  payment_method: PaymentMethodValue;
  status: PaymentStatus;
  amount: number;
  currency: string;
  modal_session: PaymentModalSession | null;
  wallet_session: PaymentWalletSession | null;
  initiated_at: string | null;
  completed_at: string | null;
}
