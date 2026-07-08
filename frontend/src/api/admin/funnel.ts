import { apiClient } from '../client';
import type { FunnelAdminPayload, FunnelContent, FunnelPackage, FunnelSection } from '../../types/funnel';

export async function fetchAdminFunnel(): Promise<FunnelAdminPayload> {
  const { data } = await apiClient.get<{ data: FunnelAdminPayload }>('/admin/funnel');
  return data.data;
}

export async function toggleFunnel(isEnabled: boolean): Promise<FunnelAdminPayload> {
  const { data } = await apiClient.put<{ data: FunnelAdminPayload }>('/admin/funnel/toggle', { is_enabled: isEnabled });
  return data.data;
}

export async function updateFunnelPackages(productId: number, packages: FunnelPackage[]): Promise<FunnelAdminPayload> {
  const { data } = await apiClient.put<{ data: FunnelAdminPayload }>('/admin/funnel/packages', {
    product_id: productId,
    packages,
  });
  return data.data;
}

export async function updateFunnelContentSection<K extends FunnelSection>(
  section: K,
  payload: FunnelContent[K],
): Promise<FunnelContent[K]> {
  const { data } = await apiClient.put<{ data: FunnelContent[K] }>(`/admin/funnel/content/${section}`, payload);
  return data.data;
}
