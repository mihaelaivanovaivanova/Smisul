import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import 'bootstrap/dist/css/bootstrap.min.css'
// The plain ESM entry point, not dist/js/bootstrap.bundle.min.js - that
// UMD build inlines its own copy of Popper, which duplicates everything
// (Bootstrap + Popper, ~80KB) once Vite also pulls in the ESM build via
// AdminLayout.tsx's `import { Offcanvas } from 'bootstrap'` (needed there
// to call an instance's .hide() directly - see that file's own comment).
// This one shares @popperjs/core as an ordinary dependency instead, so
// there's only ever one copy in the bundle either way.
import 'bootstrap'
import './index.css'
import App from './App.tsx'
import { AuthProvider } from './context/AuthContext'
import { CartProvider } from './context/CartContext'
import { FavoritesProvider } from './context/FavoritesContext'
import { CookieConsentProvider } from './context/CookieConsentContext'
import { SettingsProvider } from './context/SettingsContext'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <BrowserRouter>
      <SettingsProvider>
        <AuthProvider>
          <CookieConsentProvider>
            <CartProvider>
              <FavoritesProvider>
                <App />
              </FavoritesProvider>
            </CartProvider>
          </CookieConsentProvider>
        </AuthProvider>
      </SettingsProvider>
    </BrowserRouter>
  </StrictMode>,
)
