import { useContext } from 'react';
import { CookieConsentContext } from '../context/consent-context';
import type { CookieConsentContextValue } from '../context/consent-context';

export function useCookieConsent(): CookieConsentContextValue {
  const context = useContext(CookieConsentContext);

  if (!context) {
    throw new Error('useCookieConsent must be used within a CookieConsentProvider');
  }

  return context;
}
