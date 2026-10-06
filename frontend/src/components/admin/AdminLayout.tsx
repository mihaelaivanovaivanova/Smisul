import { Offcanvas } from 'bootstrap';
import { Link, NavLink, Outlet } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';
import Logo from '../Logo';

const NAV_ITEMS = [
  { to: '/admin', label: 'Dashboard', end: true },
  { to: '/admin/products', label: 'Products' },
  { to: '/admin/categories', label: 'Categories' },
  { to: '/admin/promotions', label: 'Promotions' },
  { to: '/admin/content', label: 'Content' },
  { to: '/admin/funnel', label: 'Funnel' },
  { to: '/admin/leads', label: 'Leads' },
  { to: '/admin/orders', label: 'Orders' },
  { to: '/admin/reviews', label: 'Reviews' },
  { to: '/admin/customers', label: 'Customers' },
  { to: '/admin/media', label: 'Media Library' },
  { to: '/admin/complaints', label: 'Complaints' },
  { to: '/admin/settings', label: 'Settings' },
  { to: '/admin/logs', label: 'Logs' },
];

/**
 * Rendered in two places: the always-visible desktop sidebar and the
 * mobile offcanvas panel. The mobile copy needs to close the offcanvas on
 * tap — deliberately NOT via `data-bs-dismiss="offcanvas"`, even though
 * that's Bootstrap's normal mechanism for this. Bootstrap's shared dismiss
 * handler (util/component-functions.js) calls `event.preventDefault()`
 * unconditionally for any `<a>`/`<area>` carrying that attribute, and
 * NavLink renders a real `<a href>` - react-router's own click handler
 * checks `!event.defaultPrevented` before it will call navigate(), so
 * whichever of the two document-level listeners runs first decides
 * whether the click actually navigates at all. On mobile this consistently
 * lost the race: every tap on a sidebar link closed the menu but never
 * navigated anywhere. Calling the Offcanvas instance's own `.hide()`
 * directly from a plain onClick sidesteps the data-API entirely, so
 * nothing ever calls preventDefault and NavLink's navigation always goes
 * through.
 */
function SidebarNav({ dismissesOffcanvas = false }: { dismissesOffcanvas?: boolean }) {
  function closeOffcanvas() {
    const element = document.getElementById('adminSidebarOffcanvas');
    if (element) {
      Offcanvas.getInstance(element)?.hide();
    }
  }

  return (
    <nav className="nav flex-column">
      {NAV_ITEMS.map((item) => (
        <NavLink
          key={item.to}
          to={item.to}
          end={item.end}
          className={({ isActive }) => `nav-link admin-sidebar__link${isActive ? ' active' : ''}`}
          onClick={dismissesOffcanvas ? closeOffcanvas : undefined}
        >
          {item.label}
        </NavLink>
      ))}
    </nav>
  );
}

export default function AdminLayout() {
  const { user, logout } = useAuth();

  return (
    <div className="d-flex min-vh-100">
      <aside className="admin-sidebar d-none d-lg-flex flex-column border-end bg-body-tertiary p-3">
        <div className="fw-bold fs-5 mb-4">Smisul Admin</div>
        <SidebarNav />
      </aside>

      <div
        className="offcanvas offcanvas-start"
        tabIndex={-1}
        id="adminSidebarOffcanvas"
        aria-labelledby="adminSidebarOffcanvasLabel"
      >
        <div className="offcanvas-header">
          <span className="fw-bold fs-5" id="adminSidebarOffcanvasLabel">
            Smisul Admin
          </span>
          <button type="button" className="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div className="offcanvas-body">
          <SidebarNav dismissesOffcanvas />
        </div>
      </div>

      <div className="flex-grow-1 d-flex flex-column">
        <header className="border-bottom d-flex align-items-center justify-content-between px-3 py-2">
          <div className="d-flex align-items-center gap-2">
            <button
              type="button"
              className="btn btn-outline-secondary d-lg-none"
              data-bs-toggle="offcanvas"
              data-bs-target="#adminSidebarOffcanvas"
              aria-controls="adminSidebarOffcanvas"
            >
              <span aria-hidden="true">&#9776;</span>
              <span className="visually-hidden">Toggle navigation</span>
            </button>

            <Link to="/">
              <Logo />
            </Link>
          </div>

          <div className="d-flex align-items-center gap-3 ms-auto">
            <button type="button" className="btn btn-outline-secondary btn-sm position-relative" title="Notifications" disabled>
              <span aria-hidden="true">&#128276;</span>
              <span className="visually-hidden">Notifications</span>
            </button>

            <div className="dropdown">
              <button
                type="button"
                className="btn btn-outline-secondary btn-sm dropdown-toggle"
                data-bs-toggle="dropdown"
                aria-expanded="false"
              >
                {user?.full_name}
              </button>
              <ul className="dropdown-menu dropdown-menu-end">
                <li>
                  <Link className="dropdown-item" to="/profile">
                    Profile
                  </Link>
                </li>
                <li>
                  <hr className="dropdown-divider" />
                </li>
                <li>
                  <button type="button" className="dropdown-item" onClick={() => void logout()}>
                    Log out
                  </button>
                </li>
              </ul>
            </div>
          </div>
        </header>

        <main className="flex-grow-1 p-3 p-md-4">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
