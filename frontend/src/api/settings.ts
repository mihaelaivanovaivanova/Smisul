import { apiClient } from './client';

/** The whitelisted merchant-identity subset served publicly for the footer. */
export interface PublicSettings {
  company_name: string | null;
  company_id: string | null;
  contact_address: string | null;
  support_phone: string | null;
  store_email: string | null;
}

/** Public, unauthenticated — fetched once by the footer. */
export async function fetchPublicSettings(): Promise<PublicSettings> {
  const { data } = await apiClient.get<{ data: PublicSettings }>('/settings/public');
  return data.data;
}
