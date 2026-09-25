import { useSettings } from './useSettings';
import type { FunnelContent, FunnelPackage } from '../types/funnel';

export interface FunnelLandingData {
  isLoading: boolean;
  error: string | null;
  productSlug: string | null;
  packages: FunnelPackage[];
  content: FunnelContent | null;
}

/**
 * Resolves what FunnelLandingPage renders, from SettingsContext's
 * boot-time fetch.
 */
export function useFunnelLandingData(): FunnelLandingData {
  const settings = useSettings();

  return {
    isLoading: settings.isLoading,
    error: settings.error,
    productSlug: settings.funnelProductSlug,
    packages: settings.funnelPackages,
    content: settings.funnelContent,
  };
}
