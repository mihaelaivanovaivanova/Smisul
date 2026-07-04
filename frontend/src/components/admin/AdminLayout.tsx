import { NavLink, Outlet } from 'react-router-dom';
import { useAuth } from '../../hooks/useAuth';

const NAV_ITEMS = [
  { to: '/admin', label: 'Dashboard', end: true },
  { to: '/admin/products', label: 'Products' },
  { to: '/admin/categories', label: 'Categories' },
  { to: '/admin/promotions', label: 'Promotions' },
  { to: '/admin/orders', label: 'Orders' },
  { to: '/admin/customers', label: 'Customers' },
  { to: '/admin/media', label: 'Media Library' },
  { to: '/admin/settings', label: 'Settings' },
  { to: '/admin/logs', label: 'Logs' },
];

function SidebarNav() {
  return (
    <nav className="nav flex-column">
      {NAV_ITEMS.map((item) => (
        <NavLink
          key={item.to}
          to={item.to}
          end={item.end}
          className={({ isActive }) => `nav-link admin-sidebar__link${isActive ? ' active' : ''}`}
          data-bs-dismiss="offcanvas"
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
          <SidebarNav />
        </div>
      </div>

      <div className="flex-grow-1 d-flex flex-column">
        <header className="border-bottom d-flex align-items-center justify-content-between px-3 py-2">
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
