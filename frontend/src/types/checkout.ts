export interface CustomerInfo {
  first_name: string;
  last_name: string;
  email: string;
  phone: string;
  company: string;
  vat_number: string;
}

export interface ShippingAddress {
  country: string;
  city: string;
  postal_code: string;
  address_line: string;
  apartment: string;
}

export type ShippingCarrier = 'econt' | 'speedy' | 'box_now';

export interface ShippingMethod {
  carrier: ShippingCarrier;
  label: string;
  description: string;
  price: number;
  currency: string;
}

export interface LegalDocument {
  id: number;
  type: string;
  version: string;
  title: string;
  content: string | null;
  published_at: string;
}

export interface PlaceOrderPayload {
  customer: {
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    company?: string;
    vat_number?: string;
  };
  address: {
    country: string;
    city: string;
    postal_code: string;
    address_line: string;
    apartment?: string;
  };
  billing_same_as_shipping: boolean;
  billing_address?: {
    country: string;
    city: string;
    postal_code: string;
    address_line: string;
    apartment?: string;
  };
  delivery_notes?: string;
  shipping_carrier: ShippingCarrier;
  legal_document_ids: number[];
}

export interface OrderItem {
  id: number;
  product_name: string;
  variant_name: string | null;
  sku: string;
  quantity: number;
  unit_price: number;
  compare_at_price: number | null;
  line_total: number;
  discount_amount: number;
  promotion_name: string | null;
}

export interface OrderTimelineEntry {
  id: number;
  status: string;
  previous_status: string | null;
  changed_by: string | null;
  note: string | null;
  changed_at: string;
}

export interface OrderTotals {
  subtotal: number;
  discount_total: number;
  shipping_total: number;
  tax_total: number;
  grand_total: number;
  currency: string;
}

export interface Order {
  id: number;
  order_number: string;
  status: string;
  currency: string;
  customer: CustomerInfo;
  address: ShippingAddress;
  billing_same_as_shipping: boolean;
  billing_address: ShippingAddress;
  delivery_notes: string | null;
  shipping: { carrier: ShippingCarrier; label: string; price: number };
  items: OrderItem[];
  totals: OrderTotals;
  legal_acceptances?: { type: string; version: string; accepted_at: string }[];
  timeline?: OrderTimelineEntry[];
  placed_at: string;
}
