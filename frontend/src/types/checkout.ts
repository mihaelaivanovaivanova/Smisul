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
  line_total: number;
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
  delivery_notes: string | null;
  shipping: { carrier: ShippingCarrier; label: string; price: number };
  items: OrderItem[];
  totals: OrderTotals;
  legal_acceptances?: { type: string; version: string; accepted_at: string }[];
  placed_at: string;
}
