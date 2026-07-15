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
}

export interface FunnelHistoryStat {
  icon: IconName;
  label: string;
}

export interface FunnelHistoryContent {
  title: string;
  paragraphs: string[];
  stats: FunnelHistoryStat[];
}

export interface FunnelFeatureItem {
  label: string;
}

export interface FunnelFeaturesContent {
  title: string;
  items: FunnelFeatureItem[];
}

/** A positive statement — true for Miswak (rendered ✓), false for a plastic brush (rendered ✗). */
export interface FunnelComparisonRow {
  label: string;
}

export interface FunnelComparisonContent {
  title: string;
  miswak_label: string;
  brush_label: string;
  rows: FunnelComparisonRow[];
}

export interface FunnelFromTreeStep {
  label: string;
}

export interface FunnelFromTreeContent {
  title: string;
  paragraphs: string[];
  steps: FunnelFromTreeStep[];
}

export interface FunnelAwarenessContent {
  title: string;
  paragraphs: string[];
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
  from_tree: FunnelFromTreeContent;
  awareness: FunnelAwarenessContent;
  final_cta: FunnelFinalCtaContent;
  faq: FunnelFaqContent;
}

export type FunnelSection = keyof FunnelContent;

export interface FunnelPackage {
  variant_id: number;
  badge: string;
  detail: string;
  value_label: string;
  /** Optional second value line, e.g. "≈ 4-5 месеца ежедневна грижа" — absent on configs saved before the field existed. */
  duration_label?: string | null;
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
