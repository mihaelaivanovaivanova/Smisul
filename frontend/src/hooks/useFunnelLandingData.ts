import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import axios from 'axios';
import { fetchFunnel } from '../api/funnel';
import { getErrorMessage } from '../api/errors';
import { useSettings } from './useSettings';
import type { FunnelContent, FunnelPackage } from '../types/funnel';

export interface FunnelLandingData {
  isLoading: boolean;
  error: string | null;
  /** Unknown or deactivated variant slug — render NotFoundPage, not ErrorState. */
  notFound: boolean;
  productSlug: string | null;
  /** The ad-angle slug this page rendered — null for the base funnel at "/" (see trackFunnelViewContent/trackFunnelAddToCart). */
  variantSlug: string | null;
  packages: FunnelPackage[];
  content: FunnelContent | null;
  /** Falls back to the shared seo.funnelTitle/funnelDescription copy when the variant hasn't set its own. */
  metaTitle: string | null;
  metaDescription: string | null;
  /** Explicit canonical path for a variant page — see FunnelLandingPage's Seo usage. Null for the base funnel (defaults to the current path). */
  canonicalPath: string | null;
}

/**
 * Resolves what FunnelLandingPage renders: the base funnel from
 * SettingsContext's boot-time fetch (plain "/", no variant segment in the
 * route), or one ad-angle variant fetched here directly by slug (the
 * "/:productSlug/:variantSlug" route — see App.tsx). Centralizing the
 * branch here keeps the page itself from having to juggle "read from
 * context" vs. "fetch locally" at every field.
 */
export function useFunnelLandingData(): FunnelLandingData {
  const { productSlug: productSlugParam, variantSlug } = useParams<{ productSlug: string; variantSlug: string }>();
  const settings = useSettings();

  const [variantData, setVariantData] = useState<Awaited<ReturnType<typeof fetchFunnel>> | null>(null);
  const [variantLoading, setVariantLoading] = useState(Boolean(variantSlug));
  const [variantError, setVariantError] = useState<string | null>(null);
  const [variantNotFound, setVariantNotFound] = useState(false);

  useEffect(() => {
    if (!variantSlug) {
      return;
    }

    let isMounted = true;
    setVariantLoading(true);
    setVariantError(null);
    setVariantNotFound(false);

    fetchFunnel(variantSlug)
      .then((payload) => {
        if (isMounted) {
          setVariantData(payload);
        }
      })
      .catch((err: unknown) => {
        if (!isMounted) {
          return;
        }
        if (axios.isAxiosError(err) && err.response?.status === 404) {
          setVariantNotFound(true);
        } else {
          setVariantError(getErrorMessage(err, 'Продуктът не можа да се зареди.'));
        }
      })
      .finally(() => {
        if (isMounted) {
          setVariantLoading(false);
        }
      });

    return () => {
      isMounted = false;
    };
  }, [variantSlug]);

  if (!variantSlug) {
    return {
      isLoading: settings.isLoading,
      error: settings.error,
      notFound: false,
      productSlug: settings.funnelProductSlug,
      variantSlug: null,
      packages: settings.funnelPackages,
      content: settings.funnelContent,
      metaTitle: null,
      metaDescription: null,
      canonicalPath: null,
    };
  }

  // The whole funnel feature is one site-wide toggle (see FunnelConfig) —
  // an angle page 404s along with "/" once an admin switches it off,
  // rather than staying reachable on its own.
  const notFound = variantNotFound || (!settings.isLoading && !settings.funnelModeEnabled);

  return {
    isLoading: settings.isLoading || variantLoading,
    error: variantError,
    notFound,
    productSlug: variantData?.product_slug ?? null,
    variantSlug: variantSlug ?? null,
    packages: variantData?.packages ?? [],
    content: variantData?.content ?? null,
    metaTitle: variantData?.meta_title ?? null,
    metaDescription: variantData?.meta_description ?? null,
    canonicalPath: variantData ? `/${variantData.product_slug ?? productSlugParam}/${variantSlug}` : null,
  };
}
