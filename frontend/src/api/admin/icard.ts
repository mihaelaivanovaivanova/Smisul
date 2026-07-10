import { apiClient } from '../client';

export interface ICardProfile {
  environment: 'sandbox' | 'production';
  is_active: boolean;
  enabled: boolean;
  mid: string | null;
  mid_name: string;
  originator: string | null;
  key_index: string | null;
  key_index_resp: string | null;
  ipg_version: string;
  currency_numeric: string;
  base_url: string;
  modal_js_url: string;
  wallet_js_url: string;
  webhook_url: string | null;
  callback_ips: string[] | null;
  private_key_configured: boolean;
  public_key_configured: boolean;
  apple_pay_enabled: boolean;
  google_pay_enabled: boolean;
  apple_merchant_id: string | null;
  apple_merchant_domain: string | null;
  google_merchant_id: string | null;
  google_environment: 'TEST' | 'PRODUCTION';
}

export type ICardProfileUpdate = Omit<ICardProfile, 'environment' | 'is_active' | 'private_key_configured' | 'public_key_configured' | 'callback_ips'> & {
  callback_ips: string[];
  private_key?: string;
  public_key?: string;
};

export async function fetchICardProfiles(): Promise<ICardProfile[]> {
  const { data } = await apiClient.get<{ data: ICardProfile[] }>('/admin/payment-settings/icard');
  return data.data;
}

export async function updateICardProfile(environment: string, values: ICardProfileUpdate): Promise<ICardProfile[]> {
  const { data } = await apiClient.put<{ data: ICardProfile[] }>(`/admin/payment-settings/icard/${environment}`, values);
  return data.data;
}

export async function activateICardProfile(environment: string): Promise<ICardProfile[]> {
  const { data } = await apiClient.post<{ data: ICardProfile[] }>(`/admin/payment-settings/icard/${environment}/activate`);
  return data.data;
}

