import { Fragment } from 'react';

interface QaPair {
  question: string;
  answer: string;
}

/**
 * Pairs up "В: question / О: answer" lines (the Bulgarian Q/A convention
 * used across seeded product descriptions). Returns null when the block
 * has no such markers, so ordinary prose falls through to a plain paragraph.
 */
function parseQa(lines: string[]): QaPair[] | null {
  const pairs: QaPair[] = [];

  for (const line of lines) {
    if (line.startsWith('В:')) {
      pairs.push({ question: line.slice(2).trim(), answer: '' });
    } else if (line.startsWith('О:') && pairs.length > 0) {
      pairs[pairs.length - 1].answer = line.slice(2).trim();
    } else if (pairs.length > 0) {
      pairs[pairs.length - 1].answer += ` ${line}`;
    } else {
      return null;
    }
  }

  return pairs.length > 0 ? pairs : null;
}

/**
 * A block made entirely of "- " bullet lines renders as one list item per
 * line. Returns null for anything else.
 */
function parseBullets(lines: string[]): string[] | null {
  if (lines.length === 0 || !lines.every((line) => line.startsWith('- '))) {
    return null;
  }

  return lines.map((line) => line.slice(2).trim());
}

/**
 * Splits "Label: value. Label: value." lines (e.g. "Основна част: 17 см.
 * Капаче: 3 см.") into one spec per pair. Returns null unless every line
 * is made of such pairs and there are at least two of them, so a single
 * "Съставки: ..." sentence keeps rendering as ordinary prose.
 */
function parseSpecs(lines: string[]): { label: string; value: string }[] | null {
  const specs: { label: string; value: string }[] = [];

  for (const line of lines) {
    const segments = line.split(/\.\s+(?=[А-ЯA-Z][^:.]{0,40}:)/).map((segment) => segment.replace(/\.$/, '').trim());

    for (const segment of segments) {
      const match = segment.match(/^([^:]{1,40}):\s*(.+)$/);
      if (!match) {
        return null;
      }
      specs.push({ label: match[1].trim(), value: match[2].trim() });
    }
  }

  return specs.length >= 2 ? specs : null;
}

/**
 * Renders the product's long-form description text with real visual
 * structure instead of one flat blob. The underlying field is still a
 * single plain-text string (admin-edited, no rich-text schema): blocks
 * are separated by a blank line, and within a block a short first line
 * ending in ':' (e.g. "Съставки:") is treated as that block's section
 * label rather than body copy — matching how every seeded description is
 * already written (see FunnelSeeder.php). A block made of "В:"/"О:" lines
 * renders as a proper question/answer list. Anything else just renders as
 * a plain paragraph. `emphasizeSpecs` opts a page into also splitting
 * "Label: value" lines into a list with the values in bold.
 */
export default function ProductDescription({ text, emphasizeSpecs = false }: { text: string; emphasizeSpecs?: boolean }) {
  const blocks = text.split(/\n{2,}/).map((block) => block.trim()).filter(Boolean);

  return (
    <div className="product-description">
      {blocks.map((block, index) => {
        const lines = block.split('\n').map((line) => line.trim()).filter(Boolean);
        const [firstLine, ...rest] = lines;
        const label = rest.length > 0 && firstLine.length <= 40 && firstLine.endsWith(':') ? firstLine : null;
        const body = label ? rest : lines;
        const qa = parseQa(body);
        const bullets = qa ? null : parseBullets(body);
        const specs = !qa && !bullets && emphasizeSpecs ? parseSpecs(body) : null;

        return (
          <Fragment key={index}>
            {label && <p className="product-description__label">{label}</p>}
            {specs ? (
              <dl className="product-description__specs">
                {specs.map((spec, specIndex) => (
                  <div className="product-description__spec" key={specIndex}>
                    <dt>{spec.label}</dt>
                    <dd>
                      <strong>{spec.value}</strong>
                    </dd>
                  </div>
                ))}
              </dl>
            ) : bullets ? (
              <ul className="product-description__list">
                {bullets.map((bullet, bulletIndex) => (
                  <li key={bulletIndex}>{bullet}</li>
                ))}
              </ul>
            ) : qa ? (
              <dl className="product-description__qa">
                {qa.map((pair, pairIndex) => (
                  <div className="product-description__qa-item" key={pairIndex}>
                    <dt>{pair.question}</dt>
                    <dd>{pair.answer}</dd>
                  </div>
                ))}
              </dl>
            ) : (
              <p className="product-description__paragraph">{body.join(' ')}</p>
            )}
          </Fragment>
        );
      })}
    </div>
  );
}
