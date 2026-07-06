const STORAGE_KEY = 'smisul_guest_consent_id';

/**
 * Client-generated UUID identifying an anonymous visitor's cookie-consent
 * history (see ConsentController on the backend) — mirrors the guest cart
 * token pattern (services/guestCartToken.ts), but generated here rather
 * than server-minted, since consent can be recorded before any request
 * that would give the backend a chance to mint one.
 */
export function getOrCreateGuestConsentId(): string {
  const existing = localStorage.getItem(STORAGE_KEY);

  if (existing) {
    return existing;
  }

  const id = crypto.randomUUID();
  localStorage.setItem(STORAGE_KEY, id);

  return id;
}
