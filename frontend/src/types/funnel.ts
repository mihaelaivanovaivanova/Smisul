import type { IconName } from '../components/icons/Icon';

export interface FunnelTrustItem {
  icon: IconName;
  label: string;
}

export interface FunnelHeroContent {
  /** Optional kicker line above the H1 — absent on content saved before the field existed. */
  eyebrow?: string | null;
  title: string;
  body: string;
  cta_primary: string;
  cta_secondary: string;
  trust_items: FunnelTrustItem[];
}

export interface FunnelIntroContent {
  title: string;
  paragraphs: string[];
}

export interface FunnelWhyCard {
  icon: IconName;
  title: string;
  text: string;
}

export interface FunnelWhyContent {
  title: string;
  cards: FunnelWhyCard[];
  /** Short statement shown after the benefit cards. */
  closing: string;
}

export interface FunnelHistoryContent {
  title: string;
  subtitle: string;
  body: string;
}

export interface FunnelFeatureItem {
  /** Image filename slug under /funnel/v2/ (e.g. "icon-feature-natural-100") — not an IconName SVG icon. */
  icon: string;
  label: string;
}

export interface FunnelFeaturesContent {
  title: string;
  items: FunnelFeatureItem[];
}

/**
 * Each column's value per row — not a fixed yes/no pair. Expected values
 * are "✓" / "✕" / "△" (rendered as marks with a matching aria-label) or
 * free text (e.g. "Допълва", "обикновено ✕"), rendered as-is.
 */
export interface FunnelComparisonRow {
  label: string;
  miswak_value: string;
  brush_value: string;
}

export interface FunnelComparisonContent {
  title: string;
  miswak_label: string;
  brush_label: string;
  rows: FunnelComparisonRow[];
  /** Short statement shown after the table/cards. */
  closing: string;
}

/** A single cited claim card — title carries its own leading emoji (same convention as funnelUseCases/funnelHeroBenefits), not a separate icon field. */
export interface FunnelScienceCard {
  title: string;
  body: string;
  /** External reference (e.g. a PubMed page) — empty string means the card has no link. */
  source_url: string;
  source_label: string;
}

export interface FunnelScienceCallout {
  stat: string;
  body: string;
}

export interface FunnelScienceSafety {
  title: string;
  body: string;
}

export interface FunnelScienceContent {
  eyebrow: string;
  title: string;
  intro: string;
  cards: FunnelScienceCard[];
  callout: FunnelScienceCallout;
  safety: FunnelScienceSafety;
}

export interface FunnelNaturalEcoContent {
  eyebrow: string;
  title: string;
  paragraphs: string[];
  /** Short, bolder closing statement — brand philosophy, not a fact claim. */
  brand_statement: string;
}

export interface FunnelAwarenessContent {
  title: string;
  subtitle: string;
  body: string;
}

export interface FunnelPositioningContent {
  title: string;
  body: string;
}

export interface FunnelFinalCtaContent {
  title: string;
  paragraphs: string[];
  cta: string;
  trust_items: FunnelTrustItem[];
}

export interface FunnelFaqItem {
  question: string;
  answer: string;
  /** Optional download link shown under the answer — empty string means none. */
  attachment_url: string;
  attachment_label: string;
}

export interface FunnelFaqContent {
  title: string;
  items: FunnelFaqItem[];
}

export interface FunnelContent {
  hero: FunnelHeroContent;
  intro: FunnelIntroContent;
  why: FunnelWhyContent;
  features: FunnelFeaturesContent;
  comparison: FunnelComparisonContent;
  history: FunnelHistoryContent;
  natural_eco: FunnelNaturalEcoContent;
  science: FunnelScienceContent;
  awareness: FunnelAwarenessContent;
  positioning: FunnelPositioningContent;
  final_cta: FunnelFinalCtaContent;
  faq: FunnelFaqContent;
}

export type FunnelSection = keyof FunnelContent;

export interface FunnelPackage {
  variant_id: number;
  badge: string;
  detail: string;
  value_label: string;
  button_text: string;
}

/** Public payload — served to every storefront visitor at boot. */
export interface FunnelPayload {
  enabled: boolean;
  product_slug: string | null;
  packages: FunnelPackage[];
  content: FunnelContent;
}

/** Admin's own view — raw product_id instead of a resolved slug, so a stale pick is visible rather than silently dropped. */
export interface FunnelAdminPayload {
  is_enabled: boolean;
  product_id: number | null;
  packages: FunnelPackage[];
  content: FunnelContent;
}
