import type { BreadcrumbItem } from '../components/Breadcrumbs';
import { siteName } from '../content/copy';

/**
 * schema.org BreadcrumbList for the same items rendered by <Breadcrumbs>
 * — keep the two in sync by building both from a single items array
 * rather than hand-writing a second copy of the trail.
 */
export function buildBreadcrumbJsonLd(items: BreadcrumbItem[]): Record<string, unknown> {
  return {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: items.map((item, index) => ({
      '@type': 'ListItem',
      position: index + 1,
      name: item.label,
      ...(item.to ? { item: `${window.location.origin}${item.to}` } : {}),
    })),
  };
}

/**
 * Site-wide Organization schema — meant for the homepage only, not every
 * page. `logo`/`sameAs` are optional since they depend on data (a bundled
 * asset URL, the admin's configured social links) the caller already has
 * on hand rather than something this pure function can fetch itself —
 * omit either and Google simply skips that field, no Knowledge Panel harm.
 */
export function organizationJsonLd(options?: { logo?: string; sameAs?: string[] }): Record<string, unknown> {
  return {
    '@context': 'https://schema.org',
    '@type': 'Organization',
    name: siteName,
    url: window.location.origin,
    ...(options?.logo ? { logo: options.logo } : {}),
    ...(options?.sameAs && options.sameAs.length > 0 ? { sameAs: options.sameAs } : {}),
  };
}

/**
 * WebSite + SearchAction schema, enabling Google's sitelinks search box —
 * Google's own guidance is to place this on the homepage, not site-wide.
 */
export function websiteJsonLd(): Record<string, unknown> {
  return {
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    name: siteName,
    url: window.location.origin,
    potentialAction: {
      '@type': 'SearchAction',
      target: `${window.location.origin}/search?q={search_term_string}`,
      'query-input': 'required name=search_term_string',
    },
  };
}
