import { useRef, useState } from 'react';
import type { Media } from '../../types/product';

interface MediaFocusPointModalProps {
  /** Null closes the modal. */
  media: Media | null;
  onSave: (focusX: number, focusY: number) => Promise<void>;
  onClose: () => void;
}

function clamp01(value: number): number {
  return Math.min(1, Math.max(0, value));
}

/**
 * Lets an admin pick which point of a photo should stay centered when the
 * storefront crops it to fit a differently-shaped box (see
 * ProductGallery.tsx's objectPosition) — the same idea CoreBenefitsSection's
 * hardcoded WHY_IMAGES focus points already use, now settable per photo.
 *
 * The image renders at its natural aspect ratio (only max-width/max-height
 * constrained), so a click/drag position on it maps directly to a 0-1
 * fraction of the photo's own width/height without needing to account for
 * object-fit letterboxing.
 */
export default function MediaFocusPointModal({ media, onSave, onClose }: MediaFocusPointModalProps) {
  const [point, setPoint] = useState({ x: media?.focus_x ?? 0.5, y: media?.focus_y ?? 0.5 });
  const [isDragging, setIsDragging] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const imgRef = useRef<HTMLImageElement>(null);

  // Reset to the media's own saved point whenever a different photo opens —
  // media is only ever swapped while the modal is closed (see
  // ProductMediaManager's key-less open-on-click), but this also covers the
  // rare case of it changing while open.
  const [openedFor, setOpenedFor] = useState(media?.id);
  if (media && media.id !== openedFor) {
    setOpenedFor(media.id);
    setPoint({ x: media.focus_x, y: media.focus_y });
  }

  if (!media) {
    return null;
  }

  function updateFromPointer(clientX: number, clientY: number) {
    const rect = imgRef.current?.getBoundingClientRect();
    if (!rect || rect.width === 0 || rect.height === 0) {
      return;
    }
    setPoint({
      x: clamp01((clientX - rect.left) / rect.width),
      y: clamp01((clientY - rect.top) / rect.height),
    });
  }

  async function handleSave() {
    setIsSaving(true);
    try {
      await onSave(point.x, point.y);
    } finally {
      setIsSaving(false);
    }
  }

  return (
    <>
      <div className="modal d-block" tabIndex={-1} role="dialog">
        <div className="modal-dialog modal-lg" role="document">
          <div className="modal-content">
            <div className="modal-header">
              <h5 className="modal-title">Set focus point</h5>
              <button type="button" className="btn-close" aria-label="Close" onClick={onClose}></button>
            </div>
            <div className="modal-body">
              <p className="text-muted small">
                Click or drag on the photo to mark what should stay in view when it's cropped to fit a different shape
                elsewhere on the site.
              </p>
              <div
                className="position-relative d-inline-block"
                style={{ cursor: isDragging ? 'grabbing' : 'crosshair', touchAction: 'none' }}
                onPointerDown={(event) => {
                  setIsDragging(true);
                  updateFromPointer(event.clientX, event.clientY);
                }}
                onPointerMove={(event) => {
                  if (isDragging) {
                    updateFromPointer(event.clientX, event.clientY);
                  }
                }}
                onPointerUp={() => setIsDragging(false)}
                onPointerLeave={() => setIsDragging(false)}
              >
                <img
                  ref={imgRef}
                  src={media.url}
                  alt={media.alt_text ?? ''}
                  draggable={false}
                  style={{ maxWidth: '100%', maxHeight: '65vh', display: 'block', userSelect: 'none' }}
                />
                <span
                  aria-hidden="true"
                  className="position-absolute translate-middle border border-2 border-white rounded-circle bg-primary"
                  style={{
                    left: `${point.x * 100}%`,
                    top: `${point.y * 100}%`,
                    width: 20,
                    height: 20,
                    boxShadow: '0 0 0 1px rgba(0,0,0,0.4)',
                    pointerEvents: 'none',
                  }}
                />
              </div>
            </div>
            <div className="modal-footer">
              <button type="button" className="btn btn-secondary" onClick={onClose} disabled={isSaving}>
                Cancel
              </button>
              <button type="button" className="btn btn-primary" onClick={() => void handleSave()} disabled={isSaving}>
                {isSaving && <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>}
                Save focus point
              </button>
            </div>
          </div>
        </div>
      </div>
      <div className="modal-backdrop show"></div>
    </>
  );
}
