export interface FunnelHeroContent {
  badge: string;
  title: string;
  body: string;
  highlight: string;
  cta_primary: string;
  cta_secondary: string;
  bullets: string[];
}

export interface FunnelDarkBandContent {
  eyebrow: string;
  title: string;
  paragraphs: string[];
  highlight: string;
}

export interface FunnelProblemContent {
  eyebrow: string;
  title: string;
  body: string;
  emphasis: string;
  bullets: string[];
  cta: string;
}

export interface FunnelBenefitCard {
  title: string;
  text: string;
  emphasis: string;
}

export interface FunnelBenefitsContent {
  eyebrow: string;
  title: string;
  cards: FunnelBenefitCard[];
}

export interface FunnelIngredientCard {
  title: string;
  text: string;
}

export interface FunnelIngredientsContent {
  eyebrow: string;
  title: string;
  cards: FunnelIngredientCard[];
  closing_line: string;
}

export interface FunnelStep {
  title: string;
  text: string;
}

export interface FunnelRitualContent {
  eyebrow: string;
  title: string;
  lines: string[];
  cta: string;
  steps: FunnelStep[];
}

export interface FunnelHowToContent {
  eyebrow: string;
  title: string;
  steps: FunnelStep[];
  note: string;
}

export interface FunnelPackagesIntroContent {
  eyebrow: string;
  title: string;
  intro: string;
}

export interface FunnelLabelsContent {
  eyebrow: string;
  title: string;
  lines: string[];
  body: string;
  cta: string;
}

export interface FunnelQuote {
  name: string;
  quote: string;
}

export interface FunnelTestimonialsContent {
  eyebrow: string;
  title: string;
  quotes: FunnelQuote[];
}

export interface FunnelFaqItem {
  question: string;
  answer: string;
}

export interface FunnelFaqContent {
  title: string;
  items: FunnelFaqItem[];
}

export interface FunnelFinalCtaContent {
  eyebrow: string;
  title: string;
  lines: string[];
  body: string;
  trust_line: string;
  cta: string;
}

export interface FunnelContent {
  hero: FunnelHeroContent;
  dark_band: FunnelDarkBandContent;
  problem: FunnelProblemContent;
  benefits: FunnelBenefitsContent;
  ingredients: FunnelIngredientsContent;
  ritual: FunnelRitualContent;
  how_to: FunnelHowToContent;
  packages_intro: FunnelPackagesIntroContent;
  labels: FunnelLabelsContent;
  testimonials: FunnelTestimonialsContent;
  faq: FunnelFaqContent;
  final_cta: FunnelFinalCtaContent;
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
