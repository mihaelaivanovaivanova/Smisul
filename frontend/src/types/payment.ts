export type PaymentStatus =
  | 'pending'
  | 'initiated'
  | 'authorized'
  | 'paid'
  | 'failed'
  | 'cancelled'
  | 'expired'
  | 'refunded';

export interface Payment {
  id: number;
  order_id: number;
  provider: string;
  status: PaymentStatus;
  amount: number;
  currency: string;
  redirect_url: string | null;
  form_fields: Record<string, string> | null;
  initiated_at: string | null;
  completed_at: string | null;
}
