import { useRef, useState } from 'react';
import { deleteMedia, fetchAdminMedia, replaceMedia } from '../../api/admin/media';
import type { MediaFilters, MediaItem } from '../../types/admin';
import { useAsync } from '../../hooks/useAsync';
import LoadingState from '../../components/LoadingState';
import ErrorState from '../../components/ErrorState';
import EmptyState from '../../components/EmptyState';
import Pagination from '../../components/listing/Pagination';
import ConfirmModal from '../../components/admin/ConfirmModal';

function isImage(mimeType: string | null): boolean {
  return mimeType?.startsWith('image/') ?? false;
}

export default function MediaLibraryPage() {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [type, setType] = useState<MediaFilters['type'] | ''>('');
  const [reloadKey, setReloadKey] = useState(0);

  const { data, isLoading, error } = useAsync(
    () => fetchAdminMedia({ page, search: search || undefined, type: type || undefined }),
    [page, search, type, reloadKey],
    'Could not load media.',
  );

  const [deleting, setDeleting] = useState<MediaItem | null>(null);
  const [isDeleting, setIsDeleting] = useState(false);
  const [replacingId, setReplacingId] = useState<number | null>(null);
  const fileInputs = useRef<Record<number, HTMLInputElement | null>>({});

  async function handleDelete() {
    if (!deleting) return;
    setIsDeleting(true);
    try {
      await deleteMedia(deleting.id);
      setDeleting(null);
      setReloadKey((key) => key + 1);
    } finally {
      setIsDeleting(false);
    }
  }

  async function handleReplace(media: MediaItem, file: File) {
    setReplacingId(media.id);
    try {
      await replaceMedia(media.id, file);
      setReloadKey((key) => key + 1);
    } finally {
      setReplacingId(null);
    }
  }

  return (
    <div>
      <h1 className="h3 mb-4">Media Library</h1>

      <div className="row g-2 mb-3">
        <div className="col-sm-5">
          <input
            type="search"
            className="form-control"
            placeholder="Search filename..."
            value={search}
            onChange={(event) => {
              setSearch(event.target.value);
              setPage(1);
            }}
          />
        </div>
        <div className="col-sm-3">
          <select
            className="form-select"
            value={type}
            onChange={(event) => {
              setType(event.target.value as MediaFilters['type']);
              setPage(1);
            }}
          >
            <option value="">All types</option>
            <option value="product">Products</option>
            <option value="category">Categories</option>
          </select>
        </div>
      </div>

      {isLoading && <LoadingState message="Loading media..." />}
      {!isLoading && error && <ErrorState message={error} />}
      {!isLoading && !error && data && data.data.length === 0 && <EmptyState title="No media found" />}

      {!isLoading && !error && data && data.data.length > 0 && (
        <>
          <div className="row g-3">
            {data.data.map((media) => (
              <div key={media.id} className="col-sm-6 col-md-4 col-lg-3">
                <div className="card h-100">
                  <div className="ratio ratio-1x1 bg-body-tertiary">
                    {isImage(media.mime_type) ? (
                      <img src={media.url} alt={media.alt_text ?? media.filename} className="object-fit-cover" />
                    ) : (
                      <div className="d-flex align-items-center justify-content-center text-muted">
                        {media.mime_type ?? 'file'}
                      </div>
                    )}
                  </div>
                  <div className="card-body p-2">
                    <div className="small text-truncate" title={media.filename}>
                      {media.filename}
                    </div>
                    <div className="small text-muted">{media.mediable_type}</div>
                  </div>
                  <div className="card-footer p-2 d-flex gap-2">
                    <input
                      type="file"
                      className="d-none"
                      ref={(el) => {
                        fileInputs.current[media.id] = el;
                      }}
                      onChange={(event) => {
                        const file = event.target.files?.[0];
                        if (file) void handleReplace(media, file);
                        event.target.value = '';
                      }}
                    />
                    <button
                      type="button"
                      className="btn btn-outline-secondary btn-sm flex-fill"
                      disabled={replacingId === media.id}
                      onClick={() => fileInputs.current[media.id]?.click()}
                    >
                      {replacingId === media.id ? 'Uploading...' : 'Replace'}
                    </button>
                    <button type="button" className="btn btn-outline-danger btn-sm" onClick={() => setDeleting(media)}>
                      Delete
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
          <div className="mt-3">
            <Pagination meta={data.meta} onPageChange={setPage} />
          </div>
        </>
      )}

      <ConfirmModal
        show={deleting !== null}
        title="Delete media"
        message={`Delete "${deleting?.filename}"? This cannot be undone.`}
        isLoading={isDeleting}
        onConfirm={() => void handleDelete()}
        onCancel={() => setDeleting(null)}
      />
    </div>
  );
}
