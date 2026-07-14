import { useState } from 'react';
import type { FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { useSettings } from '../hooks/useSettings';
import Logo from './Logo';
import MiniCart from './cart/MiniCart';
import { favorites as favoritesCopy, funnelNav, nav, orders as ordersCopy, reviews as reviewsCopy } from '../content/copy';

export default function Navbar() {
  const { user, isAuthenticated, isLoading, logout } = useAuth();
  const { funnelModeEnabled } = useSettings();
  const isAdmin = user?.role === 'administrator';
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
    <nav className="navbar navbar-expand-md border-bottom navbar-frosted" aria-label={nav.mainNavAria}>
      <div className="container d-flex flex-wrap justify-content-between align-items-center gap-2">
        <Link to="/" aria-label={nav.home}>
          <Logo />
        </Link>

        {/*
          Mobile: logo + this button group share the first row (order-2,
          justify-content-between pushes it to the right); the search form
          is order-3 with flex-basis:100% (see .navbar-search in
          components.css — Bootstrap's width utilities aren't responsive
          out of the box), so flex-wrap bumps it to its own full-width row
          below. Desktop (md+): natural order (logo, search, buttons) with
          the search bar growing to fill the middle instead.
        */}
        <div className="order-2 order-md-3 d-flex align-items-center gap-2 flex-shrink-0">
          <MiniCart />

          {!isLoading && (
            <>
              {isAuthenticated ? (
                <div className="dropdown">
                  <button
                    type="button"
                    className="btn btn-outline-secondary btn-sm dropdown-toggle"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                  >
                    {nav.profile}
                  </button>
                  <ul className="dropdown-menu dropdown-menu-end">
                    <li>
                      <h6 className="dropdown-header">{nav.greeting(user?.first_name ?? '')}</h6>
                    </li>
                    <li>
                      <hr className="dropdown-divider" />
                    </li>
                    <li>
                      <Link className="dropdown-item" to="/profile">
                        {nav.profile}
                      </Link>
                    </li>
                    {isAdmin ? (
                      <li>
                        <Link className="dropdown-item" to="/admin">
                          {nav.settings}
                        </Link>
                      </li>
                    ) : (
                      <li>
                        <Link className="dropdown-item" to="/profile/orders">
                          {ordersCopy.title}
                        </Link>
                      </li>
                    )}
                    {!funnelModeEnabled && !isAdmin && (
                      <li>
                        <Link className="dropdown-item" to="/profile/favorites">
                          {favoritesCopy.title}
                        </Link>
                      </li>
                    )}
                    {!isAdmin && (
                      <li>
                        <Link className="dropdown-item" to="/profile/reviews">
                          {reviewsCopy.myReviews.title}
                        </Link>
                      </li>
                    )}
                    <li>
                      <hr className="dropdown-divider" />
                    </li>
                    <li>
                      <button type="button" className="dropdown-item text-danger" onClick={() => void handleLogout()}>
                        {nav.logout}
                      </button>
                    </li>
                  </ul>
                </div>
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

        {!funnelModeEnabled && (
          <form className="navbar-search order-3 order-md-2 d-flex mx-md-4" role="search" onSubmit={handleSearchSubmit}>
            <input
              type="search"
              className="form-control"
              placeholder={nav.searchPlaceholder}
              aria-label={nav.searchAria}
              value={searchTerm}
              onChange={(event) => setSearchTerm(event.target.value)}
            />
          </form>
        )}

        {/* Section-anchor nav shown instead of search while funnel mode is
            on — desktop only, same as the reference site's header. */}
        {funnelModeEnabled && (
          <div className="d-none d-md-flex align-items-center gap-3 order-md-2 mx-md-4">
            <Link to="/" className="text-decoration-none text-muted fw-semibold">
              {funnelNav.home}
            </Link>
            <a href="#benefits" className="text-decoration-none text-muted fw-semibold">
              {funnelNav.benefits}
            </a>
            <a href="#how" className="text-decoration-none text-muted fw-semibold">
              {funnelNav.howTo}
            </a>
            <a href="#buy" className="text-decoration-none text-muted fw-semibold">
              {funnelNav.products}
            </a>
            <a href="#faq" className="text-decoration-none text-muted fw-semibold">
              {funnelNav.faq}
            </a>
          </div>
        )}
      </div>
    </nav>
  );
}
