import logoMark from '../assets/logo/smisul-logo-full.svg';
import logoTagline from '../assets/logo/smisul-logo-tagline.svg';
import { siteName } from '../content/copy';

interface LogoProps {
  className?: string;
  /** Full lockup with the "избирай с мисъл" tagline baked in — used where there's room to breathe (footer). */
  tagline?: boolean;
}

export default function Logo({ className, tagline = false }: LogoProps) {
  return (
    <img
      src={tagline ? logoTagline : logoMark}
      alt={siteName}
      className={`brand-mark ${tagline ? 'brand-mark--tagline' : ''} ${className ?? ''}`}
    />
  );
}
