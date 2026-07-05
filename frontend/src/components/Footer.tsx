import { Link } from 'react-router-dom';
import Logo from './Logo';
import { footer, nav, siteName } from '../content/copy';

export default function Footer() {
  const year = new Date().getFullYear();

  return (
    <footer className="section-tint border-top mt-auto py-5">
      <div className="container">
        <div className="d-flex flex-column flex-md-row justify-content-between gap-4">
          <div>
            <Logo tagline />
            <p className="text-muted mt-2 mb-0" style={{ maxWidth: '24rem' }}>
              {footer.description}
            </p>
          </div>
          <nav className="d-flex gap-3" aria-label={footer.columnsAria}>
            <Link className="text-decoration-none text-muted" to="/">
              {nav.home}
            </Link>
            <Link className="text-decoration-none text-muted" to="/search">
              {nav.browseProducts}
            </Link>
          </nav>
        </div>
        <hr className="my-4" style={{ borderColor: 'var(--color-border)' }} />
        <div className="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 text-center text-sm-start">
          <span className="text-muted small">{footer.tagline}</span>
          <span className="text-muted small">
            &copy; {year} {siteName}. Всички права запазени.
          </span>
        </div>
      </div>
    </footer>
  );
}
