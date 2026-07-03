import logoMark from '../assets/logo/smisul-logo.svg';
import { siteName } from '../content/copy';

interface LogoProps {
  className?: string;
}

export default function Logo({ className }: LogoProps) {
  return (
    <span className={`brand-mark ${className ?? ''}`}>
      <img src={logoMark} alt="" width={32} height={32} className="brand-mark__icon" />
      <span className="brand-mark__word">{siteName}</span>
    </span>
  );
}
