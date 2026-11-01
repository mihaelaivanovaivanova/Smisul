import { apiClient } from './client';
import type { Bundle } from '../types/bundle';

/** Every active, purchasable kit, in display order - see backend's BundleController. An out-of-stock kit is still listed, with is_available false. */
export async function fetchBundles(): Promise<Bundle[]> {
  const { data } = await apiClient.get<{ data: Bundle[] }>('/bundles');
  return data.data;
}
