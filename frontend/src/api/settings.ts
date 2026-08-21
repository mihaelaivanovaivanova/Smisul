import { apiClient } from './client';

/** The whitelisted merchant-identity subset served publicly for the footer. */
export interface PublicSettings {
  company_name: string | null;
  company_name_en: string | null;
  company_manager: string | null;
  company_id: string | null;
  contact_address: string | null;
  support_phone: string | null;
  store_email: string | null;
  /** "HH:MM" — same-day dispatch cutoff for the funnel promise line; null/empty disables it. */
  same_day_dispatch_cutoff: string | null;
  social_instagram: string | null;
  social_facebook: string | null;
  social_tiktok: string | null;
  /** Admin-configurable via the Shipping tab's Box Now card (see ShippingSettingsPanel.tsx) — TopAnnouncementBar.tsx / BoxNowBadge.tsx. */
  box_now_banner_enabled: boolean;
  box_now_badge_enabled: boolean;
  box_now_banner_message: string | null;
}

/** Public, unauthenticated — fetched once by the footer. */
export async function fetchPublicSettings(): Promise<PublicSettings> {
  const { data } = await apiClient.get<{ data: PublicSettings }>('/settings/public');
  return data.data;
}
