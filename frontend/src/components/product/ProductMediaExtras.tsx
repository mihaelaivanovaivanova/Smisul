import type { Media } from '../../types/product';
import { product as productCopy } from '../../content/copy';

export function ProductVideos({ videos }: { videos: Media[] }) {
  if (videos.length === 0) {
    return null;
  }

  return (
    <div className="mb-4">
      <h2 className="section-title">{productCopy.videosTitle}</h2>
      <div className="row row-cols-1 row-cols-md-2 g-3">
        {videos.map((video) => (
          <div className="col" key={video.id}>
            <video controls className="w-100 media-block" src={video.url}>
              Браузърът ти не поддържа вградено видео.
            </video>
          </div>
        ))}
      </div>
    </div>
  );
}

export function ProductDownloads({ downloads }: { downloads: Media[] }) {
  if (downloads.length === 0) {
    return null;
  }

  return (
    <div className="mb-4">
      <h2 className="section-title">{productCopy.downloadsTitle}</h2>
      <ul className="list-unstyled d-flex flex-column gap-2 mb-0">
        {downloads.map((file) => (
          <li key={file.id}>
            <a
              href={file.url}
              target="_blank"
              rel="noopener noreferrer"
              className="btn btn-outline-secondary btn-sm file-chip"
            >
              <span className="file-chip__icon" aria-hidden="true">
                PDF
              </span>
              {file.alt_text ?? productCopy.downloadFallbackLabel}
            </a>
          </li>
        ))}
      </ul>
    </div>
  );
}
