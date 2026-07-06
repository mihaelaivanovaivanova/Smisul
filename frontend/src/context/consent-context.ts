import { createContext } from 'react';
import type { CookieCategoryChoices } from '../types/consent';

export interface CookieConsentContextValue {
  /** Null means the visitor hasn't made a choice yet — the banner should show. */
  choices: CookieCategoryChoices | null;
  isPreferencesModalOpen: boolean;
  acceptAll: () => Promise<void>;
  rejectAll: () => Promise<void>;
  savePreferences: (choices: CookieCategoryChoices) => Promise<void>;
  openPreferencesModal: () => void;
  closePreferencesModal: () => void;
}

export const CookieConsentContext = createContext<CookieConsentContextValue | undefined>(undefined);
