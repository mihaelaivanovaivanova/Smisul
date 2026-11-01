import { apiClient } from '../client';
import type { Bundle } from '../../types/bundle';
import type { VariantStatus } from '../../types/product';

export interface BundleItemPayload {
  product_variant_id: number;
  quantity: number;
}

export interface BundlePayload {
  slug: string;
  name: string;
  /** e.g. "НАЙ-ПОПУЛЯРЕН" / "НАЙ-ИЗГОДЕН" - same optional badge FunnelConfig's own packages carry. Null clears it. */
  badge: string | null;
  amount: number;
  status: VariantStatus;
  sort_order: number;
  /** Each variant at most once per kit - a kit needing two of something sets that component's quantity to 2. */
  items: BundleItemPayload[];
}

/** Every kit, active or not (the public GET /v1/bundles only lists active ones) - not paginated, see Admin\ProductBundleController. */
export async function fetchAdminBundles(): Promise<Bundle[]> {
  const { data } = await apiClient.get<{ data: Bundle[] }>('/admin/bundles');
  return data.data;
}

export async function createBundle(payload: BundlePayload): Promise<Bundle> {
  const { data } = await apiClient.post<{ data: Bundle }>('/admin/bundles', payload);
  return data.data;
}

/**
 * Price/name/status/sort changes always succeed; changing the components
 * (or deleting the kit) is refused with a 422 while any cart or
 * unconfirmed order still holds stock against the kit's current
 * components - see the backend's BundleInUseException.
 */
export async function updateBundle(id: number, payload: Partial<BundlePayload>): Promise<Bundle> {
  const { data } = await apiClient.put<{ data: Bundle }>(`/admin/bundles/${id}`, payload);
  return data.data;
}

export async function deleteBundle(id: number): Promise<void> {
  await apiClient.delete(`/admin/bundles/${id}`);
}
