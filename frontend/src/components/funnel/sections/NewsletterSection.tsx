import LeadCaptureForm from '../LeadCaptureForm';
import { funnelLead } from '../../../content/copy';
import { renderWithBrandWordmark } from '../../../utils/renderWithBrandWordmark';

/**
 * Section 19/20 — Newsletter. Powered by the existing "funnelLead" UI
 * copy and LeadCaptureForm (visitors not buying today still become
 * leads), unchanged. id="newsletter" is new — this section had no id
 * before the refactor.
 */
export default function NewsletterSection() {
  return (
    <section className="section section-tint funnel-lead" id="newsletter">
      <div className="container">
        <div className="funnel-lead__inner text-center">
          <h2 className="section-title">{funnelLead.title}</h2>
          <p className="section-lead lead">{renderWithBrandWordmark(funnelLead.body)}</p>
          <LeadCaptureForm />
        </div>
      </div>
    </section>
  );
}
