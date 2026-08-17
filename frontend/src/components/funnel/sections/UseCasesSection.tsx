import { funnelUseCases } from '../../../content/copy';
import Icon from '../../icons/Icon';

/**
 * Section 3/20 — Use Cases, immediately below the Hero. Four short
 * occasion cards ("after coffee", "office", "car", "travel" — see
 * ai/context/04_Marketing_Strategy.md's approved hooks) answering "does
 * this happen to you?" before the page explains what Miswak is.
 *
 * Mobile-first: a plain 1-column stack under 576px, 2 columns from there
 * up — no carousel, no JS, just Bootstrap's grid (matches the pattern
 * used by every other card grid on this page, e.g. CoreBenefitsSection).
 * The closing CTA scrolls to #how-to-use via a plain anchor; when no
 * product video is uploaded that id doesn't exist yet, and
 * FunnelLandingPage's existing hash-scroll effect already falls back to
 * opening the FAQ's usage answer in that case — no new JS needed here.
 */
export default function UseCasesSection() {
  return (
    <section className="section funnel-hero-tone funnel-divided-section" id="use-cases">
      <div className="container">
        <h2 className="section-title mb-4 text-center">{funnelUseCases.title}</h2>

        <div className="row row-cols-1 row-cols-sm-2 g-3 g-md-4">
          {funnelUseCases.cards.map((card) => (
            <div className="col" key={card.title}>
              <div className="funnel-usecase-card funnel-usecase-card--hover">
                <span className="funnel-usecase-card__icon" aria-hidden="true">
                  <Icon name={card.icon} />
                </span>
                <h3 className="h6 mb-2">{card.title}</h3>
                <p className="section-lead lead mb-0">{card.body}</p>
              </div>
            </div>
          ))}
        </div>

        <p className="funnel-usecases__closing section-lead lead text-center fw-semibold mt-4 mb-3">
          {funnelUseCases.closing}
        </p>

        <div className="text-center">
          <a href="#how-to-use" className="btn btn-outline-secondary">
            {funnelUseCases.cta}
          </a>
        </div>
      </div>
    </section>
  );
}
