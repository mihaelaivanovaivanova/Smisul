import type { ReactNode } from 'react';
import BrandWordmark from '../components/BrandWordmark';

const WORDMARK_PATTERN = /с\|мисъл/gi;

/**
 * Splits copy on "с|мисъл" (case-insensitive — admin-authored CMS text
 * isn't guaranteed consistent casing) and swaps each occurrence for the
 * real logo wordmark (BrandWordmark.tsx) — by request, so the brand name
 * always renders in the exact logo font/spacing rather than plain text,
 * wherever it appears in body copy.
 */
export function renderWithBrandWordmark(text: string): ReactNode[] {
  const segments = text.split(WORDMARK_PATTERN);
  const matches = text.match(WORDMARK_PATTERN) ?? [];
  const nodes: ReactNode[] = [];

  segments.forEach((segment, index) => {
    if (segment) {
      nodes.push(segment);
    }
    if (matches[index]) {
      nodes.push(<BrandWordmark key={index} />);
    }
  });

  return nodes;
}
