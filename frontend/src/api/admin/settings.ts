import { apiClient } from '../client';
import type { SettingsData } from '../../types/admin';

export async function fetchSettings(): Promise<SettingsData> {
  const { data } = await apiClient.get<{ data: SettingsData }>('/admin/settings');
  return data.data;
}

export async function updateSettings(values: Record<string, string | number | boolean>): Promise<SettingsData['editable']> {
  const { data } = await apiClient.put<{ data: { editable: SettingsData['editable'] } }>('/admin/settings', { settings: values });
  return data.data.editable;
}
