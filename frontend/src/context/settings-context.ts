import { createContext } from 'react';
import type { FunnelContent, FunnelPackage } from '../types/funnel';

export interface SettingsContextValue {
  funnelModeEnabled: boolean;
  funnelProductSlug: string | null;
  funnelPackages: FunnelPackage[];
  funnelContent: FunnelContent | null;
  isLoading: boolean;
  error: string | null;
}

export const SettingsContext = createContext<SettingsContextValue | undefined>(undefined);
