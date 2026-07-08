import { useContext } from 'react';
import { SettingsContext } from '../context/settings-context';
import type { SettingsContextValue } from '../context/settings-context';

export function useSettings(): SettingsContextValue {
  const context = useContext(SettingsContext);

  if (!context) {
    throw new Error('useSettings must be used within a SettingsProvider');
  }

  return context;
}
