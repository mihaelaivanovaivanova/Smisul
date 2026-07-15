import type { Order, Shipment } from './checkout';
import type { Payment } from './payment';
import type { Role } from './auth';
import type { Product } from './product';

/** OrderResource enriched with admin-only fields (see backend Admin\OrderResource). */
export interface AdminOrder extends Order {
  user_id: number | null;
  payments: Payment[];
  shipment: Shipment | null;
}

/**
 * ProductResource enriched with a simplified quantity/price pair — read off
 * the product's default variant (see backend Admin\ProductResource). Both
 * are null until the product has a variant (i.e. before its first
 * quantity/price has ever been set).
 */
export interface AdminProduct extends Product {
  quantity: number | null;
  price: number | null;
  favorites_count: number;
}

export interface DashboardStats {
  total_orders: number;
  orders_today: number;
  revenue_today: number;
  total_revenue: number;
  total_customers: number;
  total_products: number;
  low_stock_products: number;
  out_of_stock_products: number;
  total_favorites: number;
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

/** An email captured by the funnel landing page's opt-in block. */
export interface FunnelLead {
  id: number;
  email: string;
  created_at: string;
}

export interface LogEntry {
  id: number;
  message: string;
  user: { id: number; name: string; email: string } | null;
  created_at: string;
  meta: Record<string, unknown>;
}

export interface AdminReview {
  id: number;
  rating: number;
  title: string;
  body: string;
  status: 'pending' | 'approved' | 'rejected' | 'hidden';
  verified_purchase: boolean;
  helpful_count: number;
  created_at: string;
  customer: { id: number; name: string; email: string };
  product: { id: number; name: string; slug: string };
  order_id: number;
  admin_reply: string | null;
  admin_reply_at: string | null;
  admin_replied_by: string | null;
}

export interface AdminReviewStatistics {
  total_reviews: number;
  reviews_by_status: Record<string, number>;
  average_rating: number;
  pending_count: number;
}
