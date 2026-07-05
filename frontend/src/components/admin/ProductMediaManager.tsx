import { useRef, useState } from 'react';
import { deleteProductMedia, fetchAdminProduct, makeProductMediaPrimary, uploadProductMedia } from '../../api/admin/products';
import { getErrorMessage } from '../../api/errors';
import ConfirmModal from './ConfirmModal';
import type { Media } from '../../types/product';

function isImage(mimeType: string | null): boolean {
  return mimeType?.startsWith('image/') ?? false;
}

function isVideo(mimeType: string | null): boolean {
  return mimeType?.startsWith('video/') ?? false;
}

interface ProductMediaManagerProps {
  productId: number;
  media: Media[];
  onChange: (media: Media[]) => void;
}

/**
 * Self-contained: uploads/deletes/primary-changes all hit the API directly
 * and report the resulting media list back via onChange, rather than
 * routing through the surrounding product form's own save — media changes
 * are independent of (and immediate, unlike) the name/description fields.
 */
export default function ProductMediaManager({ productId, media, onChange }: ProductMediaManagerProps) {
  const [isUploading, setIsUploading] = useState(false);
  const [uploadError, setUploadError] = useState<string | null>(null);
  const [pendingActionId, setPendingActionId] = useState<number | null>(null);
  const [deleting, setDeleting] = useState<Media | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const fileInputRef = useRef<HTMLInputElement | null>(null);

  async function refresh() {
    const product = await fetchAdminProduct(productId);
    onChange(product.media);
  }

  async function handleFilesSelected(files: FileList) {
    setIsUploading(true);
    setUploadError(null);
    try {
      for (const file of Array.from(files)) {
        await uploadProductMedia(productId, file);
      }
      await refresh();
    } catch (err) {
      setUploadError(getErrorMessage(err, 'Could not upload one or more files.'));
    } finally {
      setIsUploading(false);
    }
  }

  async function handleMakePrimary(item: Media) {
    setPendingActionId(item.id);
    try {
      await makeProductMediaPrimary(productId, item.id);
      await refresh();
    } finally {
      setPendingActionId(null);
    }
  }

  async function handleDelete() {
    if (!deleting) return;
    setIsDeleting(true);
    try {
      await deleteProductMedia(productId, deleting.id);
      setDeleting(null);
      await refresh();
    } finally {
      setIsDeleting(false);
    }
  }

  return (
    <div>
      <label className="form-label d-block">Photos &amp; videos</label>

      {uploadError && <div className="alert alert-danger py-2">{uploadError}</div>}

      {media.length > 0 && (
        <div className="row g-2 mb-3">
          {media.map((item) => (
            <div className="col-4 col-sm-3" key={item.id}>
              <div className="card h-100">
                <div className="ratio ratio-1x1 bg-body-tertiary">
                  {isImage(item.mime_type) ? (
                    <img src={item.url} alt={item.alt_text ?? ''} className="object-fit-cover" />
                  ) : isVideo(item.mime_type) ? (
                    <video src={item.url} className="object-fit-cover" muted />
                  ) : (
                    <div className="d-flex align-items-center justify-content-center text-muted small">PDF</div>
                  )}
                </div>
                <div className="card-body p-2 d-flex flex-column gap-1">
                  {item.is_primary ? (
                    <span className="badge text-bg-success">Main photo</span>
                  ) : (
                    <button
                      type="button"
                      className="btn btn-outline-secondary btn-sm"
                      disabled={pendingActionId === item.id}
                      onClick={() => void handleMakePrimary(item)}
                    >
                      Set as main
                    </button>
                  )}
                  <button type="button" className="btn btn-outline-danger btn-sm" onClick={() => setDeleting(item)}>
                    Delete
                  </button>
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      <input
        type="file"
        accept="image/*,video/*,application/pdf"
        multiple
        className="d-none"
        ref={fileInputRef}
        onChange={(event) => {
          if (event.target.files && event.target.files.length > 0) {
            void handleFilesSelected(event.target.files);
          }
          event.target.value = '';
        }}
      />
      <button
        type="button"
        className="btn btn-outline-secondary btn-sm"
        disabled={isUploading}
        onClick={() => fileInputRef.current?.click()}
      >
        {isUploading && <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>}
        Upload photos or videos
      </button>
      <div className="form-text">The first photo you mark as main is shown on the storefront; the rest appear as a gallery.</div>

      <ConfirmModal
        show={deleting !== null}
        title="Delete media"
        message={`Delete "${deleting?.alt_text || 'this file'}"? This cannot be undone.`}
        isLoading={isDeleting}
        onConfirm={() => void handleDelete()}
        onCancel={() => setDeleting(null)}
      />
    </div>
  );
}
