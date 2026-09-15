import { apiClient } from './client';
import type { FunnelPayload } from '../types/funnel';

/**
 * Public, unauthenticated. Called without a slug once at app boot for the
 * base funnel (see SettingsContext); called with an ad-angle slug (e.g.
 * "whitening") by FunnelLandingPage when rendered at
 * /{product-slug}/{variant-slug} — an unknown or deactivated slug rejects
 * with a 404, same as any other missing resource.
 */
export async function fetchFunnel(variantSlug?: string): Promise<FunnelPayload> {
  const { data } = await apiClient.get<{ data: FunnelPayload }>(variantSlug ? `/funnel/${variantSlug}` : '/funnel');
  return data.data;
}

/** Public, unauthenticated, throttled — the landing page's email opt-in block. */
export async function submitFunnelLead(email: string): Promise<void> {
  await apiClient.post('/funnel/leads', { email });
}
