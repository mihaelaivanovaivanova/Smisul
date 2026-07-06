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

export interface Payment {
  id: number;
  order_id: number;
  provider: string;
  payment_method: PaymentMethodValue;
  status: PaymentStatus;
  amount: number;
  currency: string;
  redirect_url: string | null;
  form_fields: Record<string, string> | null;
  initiated_at: string | null;
  completed_at: string | null;
}
