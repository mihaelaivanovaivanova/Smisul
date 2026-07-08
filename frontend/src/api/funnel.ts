import { apiClient } from './client';
import type { FunnelPayload } from '../types/funnel';

/** Public, unauthenticated — fetched once at app boot (see SettingsContext). */
export async function fetchFunnel(): Promise<FunnelPayload> {
  const { data } = await apiClient.get<{ data: FunnelPayload }>('/funnel');
  return data.data;
}
