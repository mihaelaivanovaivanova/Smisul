import { useState } from 'react';
import type { FormEvent } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../hooks/useAuth';
import { useSettings } from '../hooks/useSettings';
import Logo from './Logo';
import MiniCart from './cart/MiniCart';
import Icon from './icons/Icon';
import { favorites as favoritesCopy, nav, orders as ordersCopy, reviews as reviewsCopy } from '../content/copy';

/**
 * Header content is deliberately minimal — logo, a discreet account/login
 * affordance, and the cart. No section-anchor nav, no order CTA, no
 * register button: the funnel Hero carries the page's one primary CTA, so
 * the header must not compete with it (see HeroSection.tsx). Register
 * stays reachable from the login page's own "no account yet" link.
 */
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
                  {/* No "dropdown-toggle" class — that only adds Bootstrap's
                      caret glyph, which would undercut the icon-only,
                      discreet look; data-bs-toggle alone still drives the
                      dropdown behavior. */}
                  <button
                    type="button"
                    className="navbar-account-toggle"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                    aria-label={nav.profile}
                  >
                    <Icon name="user" />
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
                <Link className="navbar-account-toggle" to="/login" aria-label={nav.login}>
                  <Icon name="user" />
                </Link>
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
      </div>
    </nav>
  );
}
