import { apiClient } from './client';
import type { FunnelPayload } from '../types/funnel';

/** Public, unauthenticated — fetched once at app boot (see SettingsContext). */
export async function fetchFunnel(): Promise<FunnelPayload> {
  const { data } = await apiClient.get<{ data: FunnelPayload }>('/funnel');
  return data.data;
}

/** Public, unauthenticated, throttled — the landing page's email opt-in block. */
export async function submitFunnelLead(email: string): Promise<void> {
  await apiClient.post('/funnel/leads', { email });
}
