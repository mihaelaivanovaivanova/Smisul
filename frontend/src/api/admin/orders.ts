import { apiClient } from '../client';
import type { AdminOrder } from '../../types/admin';
import type { Payment } from '../../types/payment';
import type { PaginatedResponse } from '../../types/product';
import type { ShippingCarrier, ShippingDeliveryType } from '../../types/checkout';

export interface AdminOrderFilters {
  search?: string;
  user_id?: number;
  status?: string;
  hide_cancelled?: boolean;
  hide_failed?: boolean;
  date_from?: string;
  date_to?: string;
  sort?: 'newest' | 'oldest' | 'total_asc' | 'total_desc';
  page?: number;
}

export async function fetchAdminOrders(filters: AdminOrderFilters): Promise<PaginatedResponse<AdminOrder>> {
  const { data } = await apiClient.get<PaginatedResponse<AdminOrder>>('/admin/orders', { params: filters });
  return data;
}

export async function fetchAdminOrder(id: number): Promise<AdminOrder> {
  const { data } = await apiClient.get<{ data: AdminOrder }>(`/admin/orders/${id}`);
  return data.data;
}

export interface ManualOrderPayload {
  customer_first_name: string;
  customer_last_name: string;
  customer_phone: string;
  shipping_carrier: ShippingCarrier;
  shipping_delivery_type: Extract<ShippingDeliveryType, 'office' | 'locker'>;
  shipping_office_id: string;
  shipping_office_name: string;
  shipping_office_city: string;
  shipping_office_address: string;
  shipping_price: number;
  payment_method: 'cash_on_delivery' | 'paid';
  cod_fee: number;
  product_variant_id: number;
  quantity: number;
}

/** A quick phone/in-person sale entered straight in — no cart, no email, one line item (see Admin\OrderController::store()). */
export async function createManualOrder(payload: ManualOrderPayload): Promise<AdminOrder> {
  const { data } = await apiClient.post<{ data: AdminOrder }>('/admin/orders', payload);
  return data.data;
}

export async function updateOrderStatus(id: number, status: string, note?: string): Promise<AdminOrder> {
  const { data } = await apiClient.patch<{ data: AdminOrder }>(`/admin/orders/${id}/status`, { status, note });
  return data.data;
}

/** Manual fallback for the automatic on-payment shipment creation (see the backend listener) - retries a failed attempt or dispatches a historical order. */
export async function createOrderShipment(id: number): Promise<AdminOrder> {
  const { data } = await apiClient.post<{ data: AdminOrder }>(`/admin/orders/${id}/shipment`);
  return data.data;
}

/**
 * Sends a real cancellation request to the carrier - mirrors the automatic
 * on-order-cancelled trigger for manual use. `reason` is optional free text
 * for the carrier's own records - the backend falls back to a safe default
 * if it's left blank (Speedy rejects an empty/too-short one, see
 * SpeedyShippingProvider::cancelShipment()).
 */
export async function cancelOrderShipment(id: number, reason?: string): Promise<AdminOrder> {
  const { data } = await apiClient.post<{ data: AdminOrder }>(`/admin/orders/${id}/shipment/cancel`, { reason });
  return data.data;
}

/** Permanently deletes the order (cancelling its shipment with the carrier first, if one exists). Rejected with a 422 if a complaint is on file for this order. */
export async function deleteOrder(id: number): Promise<void> {
  await apiClient.delete(`/admin/orders/${id}`);
}

export async function reversePayment(paymentId: number): Promise<Payment> {
  const { data } = await apiClient.post<{ data: Payment }>(`/admin/payments/${paymentId}/reverse`);
  return data.data;
}

export async function refundPayment(paymentId: number, amount: number): Promise<Payment> {
  const { data } = await apiClient.post<{ data: Payment }>(`/admin/payments/${paymentId}/refund`, { amount });
  return data.data;
}
