import { useCallback, useState } from 'react';
import type { ReactNode } from 'react';
import { storeCookiePreferences } from '../api/consent';
import { getOrCreateGuestConsentId } from '../services/guestConsentId';
import { useAuth } from '../hooks/useAuth';
import type { CookieCategoryChoices } from '../types/consent';
import { CookieConsentContext } from './consent-context';
import type { CookieConsentContextValue } from './consent-context';

const STORAGE_KEY = 'smisul_cookie_consent';

function readStoredChoices(): CookieCategoryChoices | null {
  const raw = localStorage.getItem(STORAGE_KEY);

  if (!raw) {
    return null;
  }

  try {
    return JSON.parse(raw) as CookieCategoryChoices;
  } catch {
    return null;
  }
}

export function CookieConsentProvider({ children }: { children: ReactNode }) {
  const { isAuthenticated } = useAuth();
  const [choices, setChoices] = useState<CookieCategoryChoices | null>(() => readStoredChoices());
  const [isPreferencesModalOpen, setIsPreferencesModalOpen] = useState(false);

  const persist = useCallback(
    async (next: CookieCategoryChoices) => {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
      setChoices(next);
      setIsPreferencesModalOpen(false);

      const guestIdentifier = isAuthenticated ? null : getOrCreateGuestConsentId();

      // Local state/UI must never depend on this succeeding — the banner's
      // job of not re-nagging the visitor is done via localStorage above.
      // The backend call is only the audit trail.
      try {
        await storeCookiePreferences(next, guestIdentifier);
      } catch {
        // Intentionally swallowed — see comment above.
      }
    },
    [isAuthenticated],
  );

  const acceptAll = useCallback(() => persist({ analytics: true, marketing: true, preferences: true }), [persist]);
  const rejectAll = useCallback(() => persist({ analytics: false, marketing: false, preferences: false }), [persist]);
  const savePreferences = useCallback((next: CookieCategoryChoices) => persist(next), [persist]);

  const openPreferencesModal = useCallback(() => setIsPreferencesModalOpen(true), []);
  const closePreferencesModal = useCallback(() => setIsPreferencesModalOpen(false), []);

  const value: CookieConsentContextValue = {
    choices,
    isPreferencesModalOpen,
    acceptAll,
    rejectAll,
    savePreferences,
    openPreferencesModal,
    closePreferencesModal,
  };

  return <CookieConsentContext.Provider value={value}>{children}</CookieConsentContext.Provider>;
}
