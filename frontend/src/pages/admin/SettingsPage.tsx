import { Fragment, useState } from 'react';
import { fetchSettings, updateSettings } from '../../api/admin/settings';
import { fetchAdminLegalDocuments, publishLegalDocument } from '../../api/admin/legalDocuments';
import type { SettingItem } from '../../types/admin';
import { useAsync } from '../../hooks/useAsync';
import { getErrorMessage } from '../../api/errors';
import LoadingState from '../../components/LoadingState';
import ErrorState from '../../components/ErrorState';
import ICardSettingsPanel from '../../components/admin/ICardSettingsPanel';
import ShippingSettingsPanel from '../../components/admin/ShippingSettingsPanel';

const EDITABLE_GROUPS = new Set(['general', 'email', 'seo', 'media', 'system']);

const TABS: { key: string; label: string }[] = [
  { key: 'general', label: 'General' },
  { key: 'payments', label: 'Payments' },
  { key: 'shipping', label: 'Shipping' },
  { key: 'email', label: 'Email' },
  { key: 'seo', label: 'SEO' },
  { key: 'legal', label: 'Legal' },
  { key: 'media', label: 'Media' },
  { key: 'system', label: 'System' },
];

const LEGAL_TYPES = ['terms_of_service', 'privacy_policy', 'right_of_withdrawal', 'shipping_policy'];

/** Rendered inside the Shipping tab's Box Now card instead (see ShippingSettingsPanel.tsx's BoxNowMarketingFields) — filtered out of the generic General list so they don't show up twice. */
const BOX_NOW_MARKETING_KEYS = new Set(['general.box_now_banner_enabled', 'general.box_now_badge_enabled', 'general.box_now_banner_message']);

function SettingField({ item, value, onChange }: { item: SettingItem; value: string | number | boolean; onChange: (value: string | number | boolean) => void }) {
  if (item.type === 'boolean') {
    return (
      <div className="form-check form-switch mb-3">
        <input
          id={item.key}
          type="checkbox"
          className="form-check-input"
          checked={Boolean(value)}
          onChange={(event) => onChange(event.target.checked)}
        />
        <label className="form-check-label" htmlFor={item.key}>
          {item.label}
        </label>
      </div>
    );
  }

  return (
    <div className="mb-3">
      <label className="form-label" htmlFor={item.key}>
        {item.label}
      </label>
      <input
        id={item.key}
        type={item.type === 'integer' ? 'number' : 'text'}
        className="form-control"
        value={value === null ? '' : String(value)}
        onChange={(event) => onChange(item.type === 'integer' ? Number(event.target.value) : event.target.value)}
      />
    </div>
  );
}

function EditableGroupPanel({ group, items, onSaved }: { group: string; items: SettingItem[]; onSaved: () => void }) {
  const [values, setValues] = useState<Record<string, string | number | boolean>>(
    Object.fromEntries(items.map((item) => [item.key, item.value ?? (item.type === 'boolean' ? false : '')])),
  );
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  async function handleSave() {
    setIsSaving(true);
    setError(null);
    setSaved(false);
    try {
      await updateSettings(values);
      setSaved(true);
      onSaved();
    } catch (err) {
      setError(getErrorMessage(err, 'Could not save settings.'));
    } finally {
      setIsSaving(false);
    }
  }

  if (items.length === 0) {
    return <p className="text-muted">No settings in this group yet.</p>;
  }

  return (
    <div style={{ maxWidth: 480 }}>
      {error && <div className="alert alert-danger">{error}</div>}
      {saved && <div className="alert alert-success">Settings saved.</div>}
      {items.map((item) => (
        <SettingField
          key={item.key}
          item={item}
          value={values[item.key]}
          onChange={(value) => setValues((prev) => ({ ...prev, [item.key]: value }))}
        />
      ))}
      <button type="button" className="btn btn-primary" disabled={isSaving} onClick={() => void handleSave()}>
        {isSaving && <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>}
        Save {group}
      </button>
    </div>
  );
}

function LegalPanel() {
  const [reloadKey, setReloadKey] = useState(0);
  const { data: documents, isLoading, error } = useAsync(fetchAdminLegalDocuments, [reloadKey], 'Could not load legal documents.');

  const [type, setType] = useState(LEGAL_TYPES[0]);
  const [version, setVersion] = useState('');
  const [title, setTitle] = useState('');
  const [content, setContent] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);

  const [viewingId, setViewingId] = useState<number | null>(null);
  const [copiedId, setCopiedId] = useState<number | null>(null);

  async function handleCopy(text: string, id: number) {
    await navigator.clipboard.writeText(text);
    setCopiedId(id);
    setTimeout(() => setCopiedId((current) => (current === id ? null : current)), 2000);
  }

  function handleUseAsNewVersion(document: { type: string; title: string; content: string | null }) {
    setType(document.type);
    setTitle(document.title);
    setContent(document.content ?? '');
    setVersion('');
  }

  async function handlePublish() {
    setIsSubmitting(true);
    setSubmitError(null);
    try {
      await publishLegalDocument({ type, version, title, content: content || undefined });
      setVersion('');
      setTitle('');
      setContent('');
      setReloadKey((key) => key + 1);
    } catch (err) {
      setSubmitError(getErrorMessage(err, 'Could not publish this document.'));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="row g-4">
      <div className="col-lg-7">
        {isLoading && <LoadingState message="Loading documents..." />}
        {!isLoading && error && <ErrorState message={error} />}
        {!isLoading && documents && (
          <table className="table align-middle">
            <thead>
              <tr>
                <th>Type</th>
                <th>Version</th>
                <th>Current</th>
                <th>Published</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {documents.map((document) => (
                <Fragment key={document.id}>
                  <tr>
                    <td>{document.type_label}</td>
                    <td>{document.version}</td>
                    <td>
                      {document.is_current && <span className="badge text-bg-success">Current</span>}
                    </td>
                    <td>{new Date(document.published_at).toLocaleDateString('bg-BG')}</td>
                    <td className="text-end">
                      <button
                        type="button"
                        className="btn btn-outline-secondary btn-sm"
                        onClick={() => setViewingId((current) => (current === document.id ? null : document.id))}
                      >
                        {viewingId === document.id ? 'Hide' : 'View'}
                      </button>
                    </td>
                  </tr>
                  {viewingId === document.id && (
                    <tr>
                      <td colSpan={5} className="bg-body-tertiary">
                        <div className="d-flex justify-content-between align-items-center mb-2">
                          <strong className="small">
                            {document.type_label} v{document.version} — {document.title}
                          </strong>
                          <div className="d-flex gap-2">
                            <button
                              type="button"
                              className="btn btn-outline-primary btn-sm"
                              onClick={() => void handleCopy(document.content ?? '', document.id)}
                            >
                              {copiedId === document.id ? 'Copied!' : 'Copy content'}
                            </button>
                            <button
                              type="button"
                              className="btn btn-primary btn-sm"
                              onClick={() => handleUseAsNewVersion(document)}
                            >
                              Use as new version
                            </button>
                          </div>
                        </div>
                        <textarea className="form-control font-monospace small" rows={8} readOnly value={document.content ?? ''} />
                      </td>
                    </tr>
                  )}
                </Fragment>
              ))}
            </tbody>
          </table>
        )}
      </div>
      <div className="col-lg-5">
        <div className="card">
          <div className="card-header">Publish a new version</div>
          <div className="card-body d-flex flex-column gap-3">
            {submitError && <div className="alert alert-danger mb-0">{submitError}</div>}
            <div>
              <label className="form-label">Document type</label>
              <select className="form-select" value={type} onChange={(event) => setType(event.target.value)}>
                {LEGAL_TYPES.map((value) => (
                  <option key={value} value={value}>
                    {value.replace(/_/g, ' ')}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="form-label">Version</label>
              <input className="form-control" value={version} onChange={(event) => setVersion(event.target.value)} placeholder="e.g. 2.0" />
            </div>
            <div>
              <label className="form-label">Title</label>
              <input className="form-control" value={title} onChange={(event) => setTitle(event.target.value)} />
            </div>
            <div>
              <label className="form-label">Content</label>
              <textarea className="form-control" rows={5} value={content} onChange={(event) => setContent(event.target.value)} />
            </div>
            <button
              type="button"
              className="btn btn-primary"
              disabled={isSubmitting || !version || !title}
              onClick={() => void handlePublish()}
            >
              {isSubmitting && <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>}
              Publish
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

export default function SettingsPage() {
  const [reloadKey, setReloadKey] = useState(0);
  const { data, isLoading, error } = useAsync(fetchSettings, [reloadKey], 'Could not load settings.');
  const [activeTab, setActiveTab] = useState('general');

  return (
    <div>
      <h1 className="h3 mb-4">Settings</h1>

      {isLoading && <LoadingState message="Loading settings..." />}
      {!isLoading && error && <ErrorState message={error} />}

      {!isLoading && !error && data && (
        <>
          <ul className="nav nav-tabs mb-4">
            {TABS.map((tab) => (
              <li className="nav-item" key={tab.key}>
                <button
                  type="button"
                  className={`nav-link ${activeTab === tab.key ? 'active' : ''}`}
                  onClick={() => setActiveTab(tab.key)}
                >
                  {tab.label}
                </button>
              </li>
            ))}
          </ul>

          {activeTab === 'payments' && <ICardSettingsPanel />}
          {activeTab === 'shipping' && <ShippingSettingsPanel />}
          {activeTab === 'legal' && <LegalPanel />}
          {EDITABLE_GROUPS.has(activeTab) && (
            <EditableGroupPanel
              key={activeTab}
              group={activeTab}
              items={(data.editable[activeTab] ?? []).filter((item) => !BOX_NOW_MARKETING_KEYS.has(item.key))}
              onSaved={() => setReloadKey((key) => key + 1)}
            />
          )}
        </>
      )}
    </div>
  );
}
