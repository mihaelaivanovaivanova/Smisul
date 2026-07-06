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
export type ShippingDeliveryType = 'office' | 'locker' | 'address';

export interface ShippingMethod {
  carrier: ShippingCarrier;
  delivery_type: ShippingDeliveryType;
  label: string;
  description: string;
  price: number;
  currency: string;
  estimated_delivery: string;
  requires_office: boolean;
}

export interface ShippingOffice {
  id: string;
  carrier: ShippingCarrier;
  type: ShippingDeliveryType;
  name: string;
  city: string;
  address: string;
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
  shipping_delivery_type: ShippingDeliveryType;
  shipping_office_id?: string;
  shipping_office_name?: string;
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
  /** Null if the variant (or its product) has since been deleted — can't be reviewed in that case. */
  product_variant_id: number | null;
  product_slug: string | null;
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

export interface ShipmentTrackingEvent {
  status: string;
  label: string;
  description: string | null;
  occurred_at: string;
}

export interface Shipment {
  id: number;
  order_id: number;
  carrier: ShippingCarrier;
  delivery_type: ShippingDeliveryType;
  office_id: string | null;
  office_name: string | null;
  tracking_number: string | null;
  status: string;
  status_label: string;
  price: number;
  currency: string;
  estimated_delivery_at: string | null;
  events: ShipmentTrackingEvent[];
  created_at: string;
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
  shipping: {
    carrier: ShippingCarrier;
    delivery_type: ShippingDeliveryType | null;
    office_id: string | null;
    office_name: string | null;
    label: string;
    price: number;
  };
  items: OrderItem[];
  totals: OrderTotals;
  legal_acceptances?: { type: string; version: string; accepted_at: string }[];
  timeline?: OrderTimelineEntry[];
  placed_at: string;
}
