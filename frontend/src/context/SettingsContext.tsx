import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { fetchFunnel } from '../api/funnel';
import { getErrorMessage } from '../api/errors';
import { SettingsContext } from './settings-context';
import type { SettingsContextValue } from './settings-context';
import type { FunnelContent, FunnelPackage } from '../types/funnel';

/**
 * Fetches the public funnel payload once at app boot — unlike
 * FavoritesProvider/CartProvider, this has no auth dependency, since
 * Navbar/App routing need to know whether funnel mode is on before a
 * guest's auth state has even resolved.
 */
export function SettingsProvider({ children }: { children: ReactNode }) {
  const [funnelModeEnabled, setFunnelModeEnabled] = useState(false);
  const [funnelProductSlug, setFunnelProductSlug] = useState<string | null>(null);
  const [funnelPackages, setFunnelPackages] = useState<FunnelPackage[]>([]);
  const [funnelContent, setFunnelContent] = useState<FunnelContent | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let isMounted = true;

    fetchFunnel()
      .then((payload) => {
        if (!isMounted) {
          return;
        }
        setFunnelModeEnabled(payload.enabled);
        setFunnelProductSlug(payload.product_slug);
        setFunnelPackages(payload.packages);
        setFunnelContent(payload.content);
      })
      .catch((err: unknown) => {
        if (isMounted) {
          setError(getErrorMessage(err, 'Could not load site settings.'));
        }
      })
      .finally(() => {
        if (isMounted) {
          setIsLoading(false);
        }
      });

    return () => {
      isMounted = false;
    };
  }, []);

  const value: SettingsContextValue = {
    funnelModeEnabled,
    funnelProductSlug,
    funnelPackages,
    funnelContent,
    isLoading,
    error,
  };

  return <SettingsContext.Provider value={value}>{children}</SettingsContext.Provider>;
}
