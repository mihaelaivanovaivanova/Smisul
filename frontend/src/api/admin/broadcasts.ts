import { apiClient } from '../client';

export interface SendBroadcastPayload {
  subject: string;
  body: string;
  cta_url?: string;
  cta_label?: string;
}

export interface SendBroadcastResult {
  sent: number;
  failed: number;
  total: number;
}

export async function fetchBroadcastRecipientCount(): Promise<number> {
  const { data } = await apiClient.get<{ data: { count: number } }>('/admin/broadcasts/recipient-count');
  return data.data.count;
}

export async function sendBroadcast(payload: SendBroadcastPayload): Promise<SendBroadcastResult> {
  const { data } = await apiClient.post<{ data: SendBroadcastResult }>('/admin/broadcasts', payload);
  return data.data;
}
