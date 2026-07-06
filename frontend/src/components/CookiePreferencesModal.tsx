import { useEffect, useState } from 'react';
import { useCookieConsent } from '../hooks/useCookieConsent';
import type { CookieCategoryChoices } from '../types/consent';
import { cookieConsent } from '../content/copy';

type ToggleableCategory = keyof CookieCategoryChoices;

const TOGGLEABLE_CATEGORIES: ToggleableCategory[] = ['analytics', 'marketing', 'preferences'];

const DEFAULT_CHOICES: CookieCategoryChoices = { analytics: false, marketing: false, preferences: false };

export default function CookiePreferencesModal() {
  const { choices, isPreferencesModalOpen, savePreferences, closePreferencesModal } = useCookieConsent();
  const [draft, setDraft] = useState<CookieCategoryChoices>(choices ?? DEFAULT_CHOICES);

  useEffect(() => {
    if (isPreferencesModalOpen) {
      setDraft(choices ?? DEFAULT_CHOICES);
    }
  }, [isPreferencesModalOpen, choices]);

  if (!isPreferencesModalOpen) {
    return null;
  }

  const toggle = (category: ToggleableCategory) => {
    setDraft((prev) => ({ ...prev, [category]: !prev[category] }));
  };

  return (
    <>
      <div className="modal d-block" tabIndex={-1} role="dialog">
        <div className="modal-dialog" role="document">
          <div className="modal-content">
            <div className="modal-header">
              <h5 className="modal-title">{cookieConsent.modal.title}</h5>
              <button type="button" className="btn-close" aria-label="Close" onClick={closePreferencesModal}></button>
            </div>
            <div className="modal-body">
              <p>{cookieConsent.modal.description}</p>

              <div className="form-check form-switch mb-3">
                <input type="checkbox" className="form-check-input" checked disabled id="cookie-cat-necessary" />
                <label className="form-check-label" htmlFor="cookie-cat-necessary">
                  <strong>{cookieConsent.modal.categories.necessary.title}</strong>{' '}
                  <span className="text-muted small">({cookieConsent.modal.alwaysActive})</span>
                  <div className="text-muted small">{cookieConsent.modal.categories.necessary.description}</div>
                </label>
              </div>

              {TOGGLEABLE_CATEGORIES.map((category) => (
                <div className="form-check form-switch mb-3" key={category}>
                  <input
                    type="checkbox"
                    className="form-check-input"
                    id={`cookie-cat-${category}`}
                    checked={draft[category]}
                    onChange={() => toggle(category)}
                  />
                  <label className="form-check-label" htmlFor={`cookie-cat-${category}`}>
                    <strong>{cookieConsent.modal.categories[category].title}</strong>
                    <div className="text-muted small">{cookieConsent.modal.categories[category].description}</div>
                  </label>
                </div>
              ))}
            </div>
            <div className="modal-footer">
              <button type="button" className="btn btn-secondary" onClick={closePreferencesModal}>
                {cookieConsent.modal.cancel}
              </button>
              <button type="button" className="btn btn-primary" onClick={() => void savePreferences(draft)}>
                {cookieConsent.modal.save}
              </button>
            </div>
          </div>
        </div>
      </div>
      <div className="modal-backdrop show"></div>
    </>
  );
}
