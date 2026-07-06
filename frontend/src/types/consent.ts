export interface CookiePreferences {
  necessary: true;
  analytics: boolean;
  marketing: boolean;
  preferences: boolean;
}

export type CookieCategoryChoices = Pick<CookiePreferences, 'analytics' | 'marketing' | 'preferences'>;
