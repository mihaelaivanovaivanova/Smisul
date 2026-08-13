import type { FunnelFaqContent } from '../../../types/funnel';

interface FaqSectionProps {
  content: FunnelFaqContent;
  activeFaqIndex: number | null;
  onToggle: (index: number) => void;
}

/**
 * Section 18/20 — FAQ. Powered by the existing "funnel.faq" content
 * block, unchanged copy/behavior. id="faq" is unchanged from before the
 * refactor — the hash-scroll effect and Navbar's "FAQ" link both already
 * target it.
 *
 * A true accordion: each answer expands directly under its own question.
 * Two independent columns on desktop (reading order fills the left
 * column first), so opening an answer only pushes down the questions in
 * its own column.
 */
export default function FaqSection({ content, activeFaqIndex, onToggle }: FaqSectionProps) {
  const faqColumnSize = Math.ceil(content.items.length / 2);

  return (
    <section className="section funnel-hero-tone funnel-divided-section funnel-faq" id="faq">
      <div className="container">
        <h2 className="section-title mb-4 text-center">{content.title}</h2>
        <div className="funnel-faq-columns">
          {[content.items.slice(0, faqColumnSize), content.items.slice(faqColumnSize)].map((column, columnIndex) => (
            <div className="funnel-faq-column" key={column[0]?.question ?? columnIndex}>
              {column.map((item, itemIndex) => {
                const index = columnIndex * faqColumnSize + itemIndex;
                const isActive = activeFaqIndex === index;

                return (
                  <div className="funnel-faq-item" key={item.question}>
                    <button
                      type="button"
                      className={`funnel-faq-toggle btn btn-outline-secondary ${isActive ? 'is-active' : ''}`}
                      aria-expanded={isActive}
                      aria-controls={`funnel-faq-answer-${index}`}
                      onClick={() => onToggle(index)}
                    >
                      {item.question}
                      <span aria-hidden="true">{isActive ? '−' : '+'}</span>
                    </button>
                    {/* Always mounted so the open/close can animate height
                        (grid-rows 0fr -> 1fr); aria-hidden + delayed
                        visibility keep closed answers out of the a11y
                        tree and tab order. */}
                    <div
                      className={`funnel-faq-answer-wrap${isActive ? ' is-open' : ''}`}
                      id={`funnel-faq-answer-${index}`}
                      aria-hidden={!isActive}
                    >
                      <div className="funnel-faq-answer">
                        {/* The padded box lives one level below the
                            clipped grid item — padding/border on the
                            item itself would floor the collapsed height
                            above zero. */}
                        <div className="funnel-faq-answer__inner">
                          {item.answer}
                          {item.attachment_url && (
                            <a
                              href={item.attachment_url}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="funnel-faq-answer__attachment"
                              tabIndex={isActive ? 0 : -1}
                            >
                              {item.attachment_label || 'Изтегли документ'}
                            </a>
                          )}
                        </div>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
