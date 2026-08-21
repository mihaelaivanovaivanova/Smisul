import wordmark from '../assets/logo/smisul-wordmark.svg';

/**
 * The "С|МИСЪЛ" wordmark, cropped (viewBox only — path data untouched) from
 * the real logo artwork at assets/logo/smisul-logo-full.svg (see that
 * folder's README). Used inline within body copy wherever the brand name
 * is written as "с|мисъл" — see utils/renderWithBrandWordmark.tsx — so it
 * always renders in the exact font, spacing, and proportions of the actual
 * logo instead of an approximation in a font we don't have.
 */
export default function BrandWordmark() {
  return <img src={wordmark} alt="с|мисъл" className="brand-wordmark-inline" />;
}
