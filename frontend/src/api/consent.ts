import { apiClient } from './client';
import type { CookieCategoryChoices, CookiePreferences } from '../types/consent';

/**
 * Both endpoints are open to guests and authenticated users alike — an
 * authenticated request is traced by session, a guest one by
 * guestIdentifier (see services/guestConsentId.ts). When authenticated,
 * guestIdentifier is omitted; the backend ignores it in that case anyway.
 */

export async function fetchCookiePreferences(guestIdentifier: string | null): Promise<CookiePreferences> {
  const { data } = await apiClient.get<{ data: CookiePreferences }>('/consent/cookies', {
    params: guestIdentifier ? { guest_identifier: guestIdentifier } : undefined,
  });
  return data.data;
}

export async function storeCookiePreferences(
  categories: CookieCategoryChoices,
  guestIdentifier: string | null,
): Promise<CookiePreferences> {
  const { data } = await apiClient.post<{ data: CookiePreferences }>('/consent/cookies', {
    guest_identifier: guestIdentifier ?? undefined,
    categories,
  });
  return data.data;
}
