import type { LegalDocument } from './legal';

export type Role = 'customer' | 'administrator';

export interface User {
  id: number;
  first_name: string;
  last_name: string;
  full_name: string;
  email: string;
  phone: string | null;
  role: Role;
  email_verified_at: string | null;
  newsletter_subscription: boolean;
  marketing_consent: boolean;
  gdpr_consent_at: string | null;
  created_at: string;
  /** Terms/Privacy versions this account hasn't accepted yet — empty once agreed to. */
  outstanding_legal_documents: LegalDocument[];
}
