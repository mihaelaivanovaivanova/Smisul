import type { IconName } from '../components/icons/Icon';

export interface HeroContent {
  eyebrow: string;
  title: string;
  subtitle: string;
  cta: string;
}

export interface FeaturedContent {
  eyebrow: string;
  title: string;
  description: string;
  cta: string;
  /** The admin's choice, by id — null means "no specific pick, use the newest product". */
  product_id: number | null;
  /**
   * Resolved server-side from product_id (see ContentBlockService). Null
   * whenever product_id is null, or the chosen product is no longer
   * published — HomePage falls back to the newest product either way.
   */
  product_slug: string | null;
}

export interface BenefitItem {
  icon: IconName;
  title: string;
  text: string;
}

export interface BenefitsContent {
  title: string;
  lead: string;
  items: BenefitItem[];
}

export interface UsageStep {
  title: string;
  text: string;
}

export interface UsageContent {
  title: string;
  steps: UsageStep[];
}

export interface TrustItem {
  icon: IconName;
  text: string;
}

export interface TrustContent {
  title: string;
  items: TrustItem[];
}

export interface DeliveryContent {
  title: string;
  icon: IconName;
  text: string;
}

export interface BioContent {
  eyebrow: string;
  title: string;
  text: string;
}

export interface FaqItem {
  question: string;
  answer: string;
}

export interface FaqContent {
  title: string;
  items: FaqItem[];
}

export interface HomepageContent {
  hero: HeroContent;
  featured: FeaturedContent;
  benefits: BenefitsContent;
  usage: UsageContent;
  trust: TrustContent;
  delivery: DeliveryContent;
  bio: BioContent;
  faq: FaqContent;
}

export type HomepageSection = keyof HomepageContent;
