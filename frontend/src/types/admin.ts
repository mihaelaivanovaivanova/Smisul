import type { Order, Shipment } from './checkout';
import type { Payment } from './payment';
import type { Role } from './auth';

/** OrderResource enriched with admin-only fields (see backend Admin\OrderResource). */
export interface AdminOrder extends Order {
  user_id: number | null;
  payments: Payment[];
  shipment: Shipment | null;
}

/**
 * The dashboard's "latest orders" table doesn't eager-load items/timeline
 * (see OrderService::latest()), so those keys are genuinely absent from
 * the JSON, not just empty — only these fields are safe to render.
 */
export type DashboardOrderSummary = Pick<Order, 'id' | 'order_number' | 'status' | 'customer' | 'totals' | 'placed_at'>;

export interface DashboardStats {
  total_orders: number;
  orders_today: number;
  revenue_today: number;
  total_revenue: number;
  total_customers: number;
  total_products: number;
  low_stock_products: number;
  out_of_stock_products: number;
  latest_orders: DashboardOrderSummary[];
}

export interface Customer {
  id: number;
  first_name: string;
  last_name: string;
  full_name: string;
  email: string;
  phone: string | null;
  role: Role;
  email_verified_at: string | null;
  created_at: string;
  orders_count?: number;
}

export interface CustomerFilters {
  search?: string;
  sort?: 'newest' | 'oldest' | 'name';
  page?: number;
}

export type SettingType = 'string' | 'integer' | 'boolean';

export interface SettingItem {
  key: string;
  group: string;
  label: string;
  type: SettingType;
  value: string | number | boolean | null;
}

export interface ProviderStatus {
  configured: boolean;
  environment?: string;
}

export interface SettingsData {
  editable: Record<string, SettingItem[]>;
  providers: {
    payments: Record<string, ProviderStatus>;
    shipping: Record<string, ProviderStatus>;
  };
}

export interface AdminLegalDocument {
  id: number;
  type: string;
  type_label: string;
  version: string;
  title: string;
  content: string | null;
  is_current: boolean;
  published_at: string;
}

export interface PublishLegalDocumentPayload {
  type: string;
  version: string;
  title: string;
  content?: string;
}

export interface MediaItem {
  id: number;
  url: string;
  filename: string;
  mime_type: string | null;
  size: number | null;
  alt_text: string | null;
  is_primary: boolean;
  sort_order: number;
  mediable_type: string;
  mediable_id: number;
  created_at: string;
}

export interface MediaFilters {
  search?: string;
  type?: 'product' | 'category';
  mime_type?: string;
  page?: number;
}

export type LogType = 'orders' | 'payments' | 'shipments' | 'authentication' | 'admin_actions';

export interface LogEntry {
  id: number;
  message: string;
  user: { id: number; name: string; email: string } | null;
  created_at: string;
  meta: Record<string, unknown>;
}
