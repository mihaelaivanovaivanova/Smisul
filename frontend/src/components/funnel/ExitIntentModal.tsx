import { useEffect, useRef, useState } from 'react';
import LeadCaptureForm from './LeadCaptureForm';
import { funnelLead } from '../../content/copy';

const SESSION_KEY = 'funnel.exit-intent-shown';
/** No modal in the first seconds — a bounce that fast wasn't going to convert anyway, and greeting instant leavers with a popup reads desperate. */
const MIN_DWELL_MS = 10_000;

/**
 * Desktop exit-intent capture: when the cursor leaves through the top of
 * the viewport (heading for the tab bar / URL), offer the usage manual in
 * exchange for an email — the same honest trade the inline opt-in makes,
 * one last time. Fires at most once per session and only after a minimum
 * dwell; mobile never sees it (no mouse to leave with). Mounted only on
 * the funnel landing page.
 */
export default function ExitIntentModal() {
  const [isOpen, setIsOpen] = useState(false);
  const mountedAt = useRef(Date.now());

  useEffect(() => {
    function handleMouseOut(event: MouseEvent) {
      // Only a real exit through the viewport's top edge — not moves
      // between elements (relatedTarget set) or toward the page itself.
      if (event.relatedTarget !== null || event.clientY > 0) {
        return;
      }
      if (Date.now() - mountedAt.current < MIN_DWELL_MS) {
        return;
      }

      try {
        if (sessionStorage.getItem(SESSION_KEY)) {
          return;
        }
        sessionStorage.setItem(SESSION_KEY, '1');
      } catch {
        // No storage (rare private-mode edge) — showing it repeatedly
        // would be obnoxious, so don't show it at all.
        return;
      }

      setIsOpen(true);
    }

    document.addEventListener('mouseout', handleMouseOut);
    return () => document.removeEventListener('mouseout', handleMouseOut);
  }, []);

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    function handleKeydown(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        setIsOpen(false);
      }
    }

    document.addEventListener('keydown', handleKeydown);
    return () => document.removeEventListener('keydown', handleKeydown);
  }, [isOpen]);

  if (!isOpen) {
    return null;
  }

  return (
    <>
      <div
        className="modal d-block"
        tabIndex={-1}
        role="dialog"
        aria-modal="true"
        aria-labelledby="funnel-exit-title"
        onClick={() => setIsOpen(false)}
      >
        <div className="modal-dialog modal-dialog-centered" role="document" onClick={(event) => event.stopPropagation()}>
          <div className="modal-content funnel-exit-modal">
            <div className="modal-header border-0 pb-0">
              <h2 className="modal-title h4" id="funnel-exit-title">
                {funnelLead.exitTitle}
              </h2>
              <button type="button" className="btn-close" aria-label={funnelLead.exitCloseAria} onClick={() => setIsOpen(false)} />
            </div>
            <div className="modal-body">
              <p>{funnelLead.exitBody}</p>
              <LeadCaptureForm />
            </div>
          </div>
        </div>
      </div>
      <div className="modal-backdrop show"></div>
    </>
  );
}
