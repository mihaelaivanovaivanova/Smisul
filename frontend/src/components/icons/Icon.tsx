/**
 * Small hand-authored, dependency-free icon set for the storefront's brand
 * sections (benefits/trust/delivery). Deliberately not pulling in an icon
 * library — this covers the handful of glyphs actually used. Replace with
 * a real brand icon set in src/assets/icons/ if/when one exists; see
 * src/assets/icons/README.md.
 */

export type IconName = 'leaf' | 'shield' | 'heart' | 'sparkle' | 'truck';

interface IconProps {
  name: IconName;
  className?: string;
}

const PATHS: Record<IconName, string> = {
  leaf: 'M20 4C10 4 4 10 4 18c0 1.1.1 2 .3 2.9C10 19 15 14 18 9c-4 4-8 9-10.6 12.6.8.2 1.7.4 2.6.4 8 0 14-6 14-14 0-1.4-.1-2.7-.4-4Z',
  shield: 'M12 2 4 5v6c0 5.2 3.4 9.9 8 11 4.6-1.1 8-5.8 8-11V5l-8-3Zm0 9.9h6c-.5 3.6-3 6.7-6 7.8V12H6V6.3l6-2.2v7.8Z',
  heart:
    'M12 21s-6.7-4.35-9.3-8.4C.9 9.6 1.9 6 5 5c1.8-.6 3.6 0 5 1.6C11.4 5 13.2 4.4 15 5c3.1 1 4.1 4.6 2.3 7.6C18.7 16.65 12 21 12 21Z',
  sparkle:
    'M11 2h2l1.2 5.3L19.5 8.5v2L14.2 12.7 13 18h-2l-1.2-5.3L4.5 11.5v-2l5.3-1.2L11 2Z',
  truck:
    'M3 6h11v9H3V6Zm11 3h4l3 3v3h-3.2a2.5 2.5 0 0 1-4.6 0H8.8a2.5 2.5 0 0 1-4.6 0H3v-2h1c0-1.4 1.1-2.5 2.5-2.5S9 13.6 9 15v0h5V9Zm3.5 6.8a1.3 1.3 0 1 0 0-2.6 1.3 1.3 0 0 0 0 2.6ZM6.5 15.8a1.3 1.3 0 1 0 0-2.6 1.3 1.3 0 0 0 0 2.6Z',
};

export default function Icon({ name, className }: IconProps) {
  return (
    <svg
      viewBox="0 0 24 24"
      width="1.25em"
      height="1.25em"
      fill="currentColor"
      aria-hidden="true"
      focusable="false"
      className={className}
    >
      <path d={PATHS[name]} />
    </svg>
  );
}
