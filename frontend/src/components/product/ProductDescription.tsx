import { Fragment } from 'react';

/**
 * Renders the product's long-form description text with real visual
 * structure instead of one flat blob. The underlying field is still a
 * single plain-text string (admin-edited, no rich-text schema): blocks
 * are separated by a blank line, and within a block a short first line
 * ending in ':' (e.g. "Съставки:") is treated as that block's section
 * label rather than body copy — matching how every seeded description is
 * already written (see FunnelSeeder.php). Anything that doesn't happen to
 * follow that convention just renders as a plain paragraph.
 */
export default function ProductDescription({ text }: { text: string }) {
  const blocks = text.split(/\n{2,}/).map((block) => block.trim()).filter(Boolean);

  return (
    <div className="product-description">
      {blocks.map((block, index) => {
        const [firstLine, ...rest] = block.split('\n');
        const label = rest.length > 0 && firstLine.length <= 40 && firstLine.endsWith(':') ? firstLine : null;
        const body = label ? rest.join(' ') : block;

        return (
          <Fragment key={index}>
            {label && <p className="product-description__label">{label}</p>}
            <p className="product-description__paragraph">{body}</p>
          </Fragment>
        );
      })}
    </div>
  );
}
