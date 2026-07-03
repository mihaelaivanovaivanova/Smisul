import { useEffect } from 'react';

interface SeoProps {
  title: string;
  description?: string | null;
  /** Path (e.g. "/products/foo") used to build the canonical/og:url. Defaults to the current path. */
  canonicalPath?: string;
  ogImage?: string | null;
  ogType?: string;
  /** A JSON-serializable schema.org object; rendered as a <script type="application/ld+json">. */
  jsonLd?: Record<string, unknown> | null;
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

function upsertLink(rel: string, href: string): void {
  let element = document.head.querySelector<HTMLLinkElement>(`link[rel="${rel}"]`);

  if (!element) {
    element = document.createElement('link');
    element.setAttribute('rel', rel);
    document.head.appendChild(element);
  }

  element.setAttribute('href', href);
}

const JSON_LD_ELEMENT_ID = 'seo-json-ld';

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
export default function Seo({ title, description, canonicalPath, ogImage, ogType = 'website', jsonLd }: SeoProps) {
  const jsonLdString = jsonLd ? JSON.stringify(jsonLd) : null;

  useEffect(() => {
    document.title = title;

    if (description) {
      upsertMeta('name', 'description', description);
    }

    const canonicalUrl = `${window.location.origin}${canonicalPath ?? window.location.pathname}`;
    upsertLink('canonical', canonicalUrl);

    upsertMeta('property', 'og:title', title);
    if (description) {
      upsertMeta('property', 'og:description', description);
    }
    upsertMeta('property', 'og:type', ogType);
    upsertMeta('property', 'og:url', canonicalUrl);
    if (ogImage) {
      upsertMeta('property', 'og:image', ogImage);
    }

    const existingScript = document.getElementById(JSON_LD_ELEMENT_ID);

    if (jsonLdString) {
      const script = existingScript ?? document.createElement('script');
      script.id = JSON_LD_ELEMENT_ID;
      (script as HTMLScriptElement).type = 'application/ld+json';
      script.textContent = jsonLdString;

      if (!existingScript) {
        document.head.appendChild(script);
      }
    } else {
      existingScript?.remove();
    }
  }, [title, description, canonicalPath, ogImage, ogType, jsonLdString]);

  return null;
}
