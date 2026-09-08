import { useEffect } from 'react';
import { fetchPublicSettings } from '../api/settings';
import { useAsync } from '../hooks/useAsync';

interface SeoProps {
  /** Falls back to the admin-configured "Default meta title" setting when omitted. */
  title?: string;
  description?: string | null;
  /** Path (e.g. "/products/foo") used to build the canonical/og:url. Defaults to the current path. */
  canonicalPath?: string;
  ogImage?: string | null;
  ogType?: string;
  /**
   * Keeps this page out of search results while still letting it be
   * crawled (unlike blocking it in robots.txt, which would hide this very
   * tag from Google and risk the URL being indexed anyway with no
   * description - see Google's own guidance on noindex vs. disallow).
   * Use on pages that are real, linkable, and must render normally but
   * were never meant to rank: order confirmation/tracking, the SPA's
   * client-routed 404 (which - since there's no server-side routing - has
   * no way to return a real HTTP 404 status on its own).
   */
  noindex?: boolean;
  /**
   * One or more JSON-serializable schema.org objects; each is rendered as
   * its own <script type="application/ld+json"> (e.g. a page's Product
   * schema alongside its BreadcrumbList schema).
   */
  jsonLd?: Record<string, unknown> | Record<string, unknown>[] | null;
}

function upsertMeta(attribute: 'name' | 'property', key: string, content: string): void {
  let element = document.head.querySelector<HTMLMetaElement>(`meta[${attribute}="${key}"]`);

  if (!element) {
    element = document.createElement('meta');
    element.setAttribute(attribute, key);
    document.head.appendChild(element);
  }

  element.setAttribute('content', content);
}

function removeMeta(attribute: 'name' | 'property', key: string): void {
  document.head.querySelector<HTMLMetaElement>(`meta[${attribute}="${key}"]`)?.remove();
}

function upsertLink(rel: string, href: string): void {
  let element = document.head.querySelector<HTMLLinkElement>(`link[rel="${rel}"]`);

  if (!element) {
    element = document.createElement('link');
    element.setAttribute('rel', rel);
    document.head.appendChild(element);
  }

  element.setAttribute('href', href);
}

const JSON_LD_ELEMENT_ID_PREFIX = 'seo-json-ld';

/**
 * Hand-rolled, dependency-free SEO tag manager for this client-side-rendered
 * SPA: on mount/update it directly writes document.title and upserts the
 * description/canonical/Open Graph meta tags and a JSON-LD script tag.
 *
 * This works for real users and for crawlers that execute JavaScript
 * (Googlebot does). It does NOT help crawlers/scrapers that read only the
 * initial HTML (many Open Graph link-preview bots) — see the Sprint 3
 * report's "Known limitations" section for the full caveat and the
 * SSR/prerendering path that would be needed to close that gap.
 */
export default function Seo({ title, description, canonicalPath, ogImage, ogType = 'website', jsonLd, noindex = false }: SeoProps) {
  const jsonLdList = jsonLd ? (Array.isArray(jsonLd) ? jsonLd : [jsonLd]) : [];
  const jsonLdString = jsonLdList.length > 0 ? JSON.stringify(jsonLdList) : null;

  // Admin-configured fallback (Settings -> SEO) for any page that doesn't
  // set its own title/description/OG image - see SettingService::publicSettings().
  const { data: defaults } = useAsync(fetchPublicSettings, [], '');
  const effectiveTitle = title ?? defaults?.default_meta_title ?? 'Smisul';
  const effectiveDescription = description ?? defaults?.default_meta_description ?? null;
  const effectiveOgImage = ogImage ?? defaults?.default_og_image ?? null;

  useEffect(() => {
    document.title = effectiveTitle;

    // This SPA reuses one document across client-side navigations, so a
    // noindex tag left by a previous page must be explicitly cleared here
    // rather than assumed gone - otherwise navigating from, say, order
    // confirmation to the homepage would silently carry it along.
    if (noindex) {
      upsertMeta('name', 'robots', 'noindex, follow');
    } else {
      removeMeta('name', 'robots');
    }

    if (effectiveDescription) {
      upsertMeta('name', 'description', effectiveDescription);
    }

    const canonicalUrl = `${window.location.origin}${canonicalPath ?? window.location.pathname}`;
    upsertLink('canonical', canonicalUrl);

    upsertMeta('property', 'og:title', effectiveTitle);
    if (effectiveDescription) {
      upsertMeta('property', 'og:description', effectiveDescription);
    }
    upsertMeta('property', 'og:type', ogType);
    upsertMeta('property', 'og:url', canonicalUrl);
    if (effectiveOgImage) {
      upsertMeta('property', 'og:image', effectiveOgImage);
    }

    upsertMeta('name', 'twitter:card', effectiveOgImage ? 'summary_large_image' : 'summary');
    upsertMeta('name', 'twitter:title', effectiveTitle);
    if (effectiveDescription) {
      upsertMeta('name', 'twitter:description', effectiveDescription);
    }
    if (effectiveOgImage) {
      upsertMeta('name', 'twitter:image', effectiveOgImage);
    }

    const parsedJsonLd: Record<string, unknown>[] = jsonLdString ? JSON.parse(jsonLdString) : [];
    const existingScripts = document.querySelectorAll(`script[id^="${JSON_LD_ELEMENT_ID_PREFIX}-"]`);

    existingScripts.forEach((script) => script.remove());

    parsedJsonLd.forEach((entry, index) => {
      const script = document.createElement('script');
      script.id = `${JSON_LD_ELEMENT_ID_PREFIX}-${index}`;
      script.type = 'application/ld+json';
      script.textContent = JSON.stringify(entry);
      document.head.appendChild(script);
    });
  }, [effectiveTitle, effectiveDescription, canonicalPath, effectiveOgImage, ogType, jsonLdString, noindex]);

  return null;
}
