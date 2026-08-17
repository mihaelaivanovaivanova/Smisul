import { apiClient } from '../client';

export interface ShippingProviderSetting {
  provider: 'econt' | 'speedy' | 'box_now';
  label: string;
  enabled: boolean;
  base_url: string | null;
  username_configured: boolean;
  password_configured: boolean;
  client_id_configured: boolean;
  client_secret_configured: boolean;
  configured: boolean;
}

export interface ShippingProviderSettingUpdate {
  enabled: boolean;
  base_url: string;
  username?: string;
  password?: string;
  client_id?: string;
  client_secret?: string;
}

export async function fetchShippingProviderSettings(): Promise<ShippingProviderSetting[]> {
  const { data } = await apiClient.get<{ data: ShippingProviderSetting[] }>('/admin/shipping-settings');
  return data.data;
}

export async function updateShippingProviderSetting(
  provider: string,
  values: ShippingProviderSettingUpdate,
): Promise<ShippingProviderSetting[]> {
  const { data } = await apiClient.put<{ data: ShippingProviderSetting[] }>(`/admin/shipping-settings/${provider}`, values);
  return data.data;
}
