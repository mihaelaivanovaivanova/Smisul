import { useState } from 'react';
import type { FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import Logo from './Logo';
import { nav } from '../content/copy';

export default function Navbar() {
  const { user, isAuthenticated, isLoading, logout } = useAuth();
  const navigate = useNavigate();
  const [searchTerm, setSearchTerm] = useState('');

  async function handleLogout() {
    await logout();
    navigate('/login');
  }

  function handleSearchSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const trimmed = searchTerm.trim();
    navigate(trimmed ? `/search?q=${encodeURIComponent(trimmed)}` : '/search');
  }

  return (
    <nav className="navbar navbar-expand-md bg-white border-bottom" aria-label={nav.mainNavAria}>
      <div className="container gap-2">
        <Link to="/" aria-label={nav.home}>
          <Logo />
        </Link>

        <form className="d-flex flex-grow-1 mx-md-4" role="search" onSubmit={handleSearchSubmit}>
          <input
            type="search"
            className="form-control"
            placeholder={nav.searchPlaceholder}
            aria-label={nav.searchAria}
            value={searchTerm}
            onChange={(event) => setSearchTerm(event.target.value)}
          />
        </form>

        <div className="d-flex align-items-center gap-2 flex-shrink-0">
          {!isLoading && (
            <>
              {isAuthenticated ? (
                <>
                  <span className="text-muted d-none d-sm-inline me-1">
                    {nav.greeting(user?.first_name ?? '')}
                  </span>
                  <Link className="btn btn-outline-secondary btn-sm" to="/profile">
                    {nav.profile}
                  </Link>
                  <button type="button" className="btn btn-outline-danger btn-sm" onClick={() => void handleLogout()}>
                    {nav.logout}
                  </button>
                </>
              ) : (
                <>
                  <Link className="btn btn-outline-primary btn-sm" to="/login">
                    {nav.login}
                  </Link>
                  <Link className="btn btn-primary btn-sm" to="/register">
                    {nav.register}
                  </Link>
                </>
              )}
            </>
          )}
        </div>
      </div>
    </nav>
  );
}
