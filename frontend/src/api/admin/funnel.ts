import { apiClient } from '../client';
import type {
  FunnelAdminPayload,
  FunnelContent,
  FunnelPackage,
  FunnelSection,
  FunnelVariantAdminPayload,
  FunnelVariantInput,
  FunnelVariantSummary,
} from '../../types/funnel';

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

/** Additional ad-angle landing pages layered on top of the base funnel above (see FunnelVariant on the backend). */
export async function fetchFunnelVariants(): Promise<FunnelVariantSummary[]> {
  const { data } = await apiClient.get<{ data: FunnelVariantSummary[] }>('/admin/funnel/variants');
  return data.data;
}

export async function fetchFunnelVariant(slug: string): Promise<FunnelVariantAdminPayload> {
  const { data } = await apiClient.get<{ data: FunnelVariantAdminPayload }>(`/admin/funnel/variants/${slug}`);
  return data.data;
}

/** slug is immutable once created — see the backend's FunnelVariantStoreRequest doc comment. */
export async function createFunnelVariant(
  slug: string,
  payload: FunnelVariantInput,
): Promise<FunnelVariantAdminPayload> {
  const { data } = await apiClient.post<{ data: FunnelVariantAdminPayload }>('/admin/funnel/variants', {
    slug,
    ...payload,
  });
  return data.data;
}

export async function updateFunnelVariant(slug: string, payload: FunnelVariantInput): Promise<FunnelVariantAdminPayload> {
  const { data } = await apiClient.patch<{ data: FunnelVariantAdminPayload }>(`/admin/funnel/variants/${slug}`, payload);
  return data.data;
}

export async function deleteFunnelVariant(slug: string): Promise<void> {
  await apiClient.delete(`/admin/funnel/variants/${slug}`);
}

export async function updateFunnelVariantContentSection<K extends FunnelSection>(
  variantSlug: string,
  section: K,
  payload: FunnelContent[K],
): Promise<FunnelContent[K]> {
  const { data } = await apiClient.put<{ data: FunnelContent[K] }>(
    `/admin/funnel/variants/${variantSlug}/content/${section}`,
    payload,
  );
  return data.data;
}

/** Removes the variant's override for one section so it falls back to the base funnel's content again. */
export async function resetFunnelVariantContentSection(
  variantSlug: string,
  section: FunnelSection,
): Promise<FunnelVariantAdminPayload> {
  const { data } = await apiClient.delete<{ data: FunnelVariantAdminPayload }>(
    `/admin/funnel/variants/${variantSlug}/content/${section}`,
  );
  return data.data;
}

export interface FunnelFaqAttachmentUpload {
  url: string;
  filename: string;
}

/**
 * Uploads a replacement PDF and returns its public URL — the caller still
 * has to put that URL into the relevant FAQ item's attachment_url and
 * Save the section for it to take effect, same as every other field here.
 */
export async function uploadFunnelFaqAttachment(file: File): Promise<FunnelFaqAttachmentUpload> {
  const form = new FormData();
  form.append('file', file);
  const { data } = await apiClient.post<{ data: FunnelFaqAttachmentUpload }>('/admin/funnel/faq-attachment', form);
  return data.data;
}
