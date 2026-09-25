import type { FunnelScienceSafety } from '../../types/funnel';

interface EvidenceSectionProps {
  safety: FunnelScienceSafety;
}

/**
 * Reference's "lab-tested" block (EUROFINS certificate + stat). We have no
 * real lab certificate for Miswak, so — per the approved plan — this reuses
 * the existing, already-live funnel.science.safety content instead of
 * fabricating one. No certificate CTA; the content itself is the evidence.
 */
export default function EvidenceSection({ safety }: EvidenceSectionProps) {
  if (!safety?.title) {
    return null;
  }

  return (
    <section className="section funnel-hero-tone" id="evidence">
      <div className="container">
        <div className="miswak-evidence">
          <h2 className="section-title mb-3">{safety.title}</h2>
          <p className="section-lead lead mb-0">{safety.body}</p>
        </div>
      </div>
    </section>
  );
}
