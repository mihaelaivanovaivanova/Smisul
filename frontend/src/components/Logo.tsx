import logoMark from '../assets/logo/smisul-logo-full.svg';
import logoTagline from '../assets/logo/smisul-logo-tagline.svg';
import logoTaglineLight from '../assets/logo/smisul-logo-tagline-light.svg';
import { siteName } from '../content/copy';

interface LogoProps {
  className?: string;
  /** Full lockup with the "избирай с мисъл" tagline baked in — used where there's room to breathe (footer). */
  tagline?: boolean;
  /**
   * A single-color sage-green recolor of the tagline lockup (both the
   * mark and the wordmark are normally two-tone dark green/olive, which
   * disappears against a dark green background) — used on the dark
   * footer band. Only defined for the tagline variant since that's the
   * only place with a dark background.
   */
  light?: boolean;
}

export default function Logo({ className, tagline = false, light = false }: LogoProps) {
  const src = light && tagline ? logoTaglineLight : tagline ? logoTagline : logoMark;

  return (
    <img src={src} alt={siteName} className={`brand-mark ${tagline ? 'brand-mark--tagline' : ''} ${className ?? ''}`} />
  );
}
