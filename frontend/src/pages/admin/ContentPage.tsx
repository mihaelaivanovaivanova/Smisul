import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { fetchAdminHomepageContent, updateHomepageSection } from '../../api/admin/content';
import { fetchAdminProducts } from '../../api/admin/products';
import { useAsync } from '../../hooks/useAsync';
import { getErrorMessage } from '../../api/errors';
import LoadingState from '../../components/LoadingState';
import ErrorState from '../../components/ErrorState';
import type { IconName } from '../../components/icons/Icon';
import type {
  BenefitsContent,
  BioContent,
  DeliveryContent,
  FaqContent,
  FeaturedContent,
  HeroContent,
  HomepageSection,
  TrustContent,
  UsageContent,
} from '../../types/content';

const ICONS: IconName[] = ['leaf', 'shield', 'heart', 'sparkle', 'truck', 'cart'];

const TABS: { key: HomepageSection; label: string }[] = [
  { key: 'hero', label: 'Hero' },
  { key: 'featured', label: 'Featured product' },
  { key: 'benefits', label: 'Benefits' },
  { key: 'usage', label: 'Usage steps' },
  { key: 'trust', label: 'Trust list' },
  { key: 'delivery', label: 'Delivery' },
  { key: 'bio', label: 'Bio blurb' },
  { key: 'faq', label: 'FAQ' },
];

function IconSelect({ value, onChange }: { value: IconName; onChange: (value: IconName) => void }) {
  return (
    <select className="form-select" value={value} onChange={(event) => onChange(event.target.value as IconName)}>
      {ICONS.map((icon) => (
        <option key={icon} value={icon}>
          {icon}
        </option>
      ))}
    </select>
  );
}

interface SectionFormProps<T> {
  section: HomepageSection;
  initial: T;
  onSaved: (value: T) => void;
  children: (value: T, setValue: (value: T) => void) => ReactNode;
}

function SectionForm<T>({ section, initial, onSaved, children }: SectionFormProps<T>) {
  const [value, setValue] = useState<T>(initial);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setIsSaving(true);
    setError(null);
    setSaved(false);
    try {
      const updated = await updateHomepageSection(section, value as never);
      setValue(updated as T);
      onSaved(updated as T);
      setSaved(true);
    } catch (err) {
      setError(getErrorMessage(err, 'Could not save this section.'));
    } finally {
      setIsSaving(false);
    }
  }

  return (
    <form onSubmit={(event) => void handleSubmit(event)} style={{ maxWidth: 720 }}>
      {error && <div className="alert alert-danger">{error}</div>}
      {saved && <div className="alert alert-success">Saved. Changes are live on the homepage.</div>}
      {children(value, setValue)}
      <button type="submit" className="btn btn-primary" disabled={isSaving}>
        {isSaving && <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>}
        Save
      </button>
    </form>
  );
}

function TextField({ label, value, onChange, multiline = false }: { label: string; value: string; onChange: (value: string) => void; multiline?: boolean }) {
  const id = label.toLowerCase().replace(/\s+/g, '-');

  return (
    <div className="mb-3">
      <label className="form-label" htmlFor={id}>
        {label}
      </label>
      {multiline ? (
        <textarea id={id} className="form-control" rows={3} value={value} onChange={(event) => onChange(event.target.value)} required />
      ) : (
        <input id={id} className="form-control" value={value} onChange={(event) => onChange(event.target.value)} required />
      )}
    </div>
  );
}

function HeroForm({ initial, onSaved }: { initial: HeroContent; onSaved: () => void }) {
  return (
    <SectionForm section="hero" initial={initial} onSaved={onSaved}>
      {(value, setValue) => (
        <>
          <TextField label="Eyebrow" value={value.eyebrow} onChange={(v) => setValue({ ...value, eyebrow: v })} />
          <TextField label="Title" value={value.title} onChange={(v) => setValue({ ...value, title: v })} />
          <TextField label="Subtitle" value={value.subtitle} onChange={(v) => setValue({ ...value, subtitle: v })} multiline />
          <TextField label="Button text" value={value.cta} onChange={(v) => setValue({ ...value, cta: v })} />
        </>
      )}
    </SectionForm>
  );
}

function ProductSelect({ value, onChange }: { value: number | null; onChange: (value: number | null) => void }) {
  const { data, isLoading, error } = useAsync(
    () => fetchAdminProducts({ sort: 'name', per_page: 100 }),
    [],
    'Could not load products.',
  );

  const publishedProducts = (data?.data ?? []).filter((product) => product.status === 'published');

  return (
    <div className="mb-3">
      <label className="form-label" htmlFor="featured-product">
        Featured product
      </label>
      {isLoading && <div className="text-muted small">Loading products...</div>}
      {!isLoading && error && <div className="text-danger small">{error}</div>}
      {!isLoading && !error && (
        <select
          id="featured-product"
          className="form-select"
          value={value ?? ''}
          onChange={(event) => onChange(event.target.value ? Number(event.target.value) : null)}
        >
          <option value="">Use the newest published product automatically</option>
          {publishedProducts.map((product) => (
            <option key={product.id} value={product.id}>
              {product.name}
            </option>
          ))}
        </select>
      )}
      <div className="form-text">Only published products can be featured.</div>
    </div>
  );
}

function FeaturedForm({ initial, onSaved }: { initial: FeaturedContent; onSaved: () => void }) {
  return (
    <SectionForm section="featured" initial={initial} onSaved={onSaved}>
      {(value, setValue) => (
        <>
          <TextField label="Eyebrow" value={value.eyebrow} onChange={(v) => setValue({ ...value, eyebrow: v })} />
          <TextField label="Title" value={value.title} onChange={(v) => setValue({ ...value, title: v })} />
          <TextField label="Description" value={value.description} onChange={(v) => setValue({ ...value, description: v })} multiline />
          <TextField label="Button text" value={value.cta} onChange={(v) => setValue({ ...value, cta: v })} />
          <ProductSelect value={value.product_id} onChange={(product_id) => setValue({ ...value, product_id })} />
        </>
      )}
    </SectionForm>
  );
}

function BenefitsForm({ initial, onSaved }: { initial: BenefitsContent; onSaved: () => void }) {
  return (
    <SectionForm section="benefits" initial={initial} onSaved={onSaved}>
      {(value, setValue) => (
        <>
          <TextField label="Title" value={value.title} onChange={(v) => setValue({ ...value, title: v })} />
          <TextField label="Lead" value={value.lead} onChange={(v) => setValue({ ...value, lead: v })} multiline />

          <label className="form-label">Cards</label>
          {value.items.map((item, index) => (
            <div className="row g-2 mb-2 align-items-start" key={index}>
              <div className="col-3">
                <IconSelect
                  value={item.icon}
                  onChange={(icon) =>
                    setValue({ ...value, items: value.items.map((it, i) => (i === index ? { ...it, icon } : it)) })
                  }
                />
              </div>
              <div className="col-4">
                <input
                  className="form-control"
                  placeholder="Title"
                  value={item.title}
                  onChange={(event) =>
                    setValue({ ...value, items: value.items.map((it, i) => (i === index ? { ...it, title: event.target.value } : it)) })
                  }
                  required
                />
              </div>
              <div className="col-4">
                <input
                  className="form-control"
                  placeholder="Text"
                  value={item.text}
                  onChange={(event) =>
                    setValue({ ...value, items: value.items.map((it, i) => (i === index ? { ...it, text: event.target.value } : it)) })
                  }
                  required
                />
              </div>
              <div className="col-1">
                <button
                  type="button"
                  className="btn btn-outline-danger btn-sm"
                  disabled={value.items.length <= 1}
                  onClick={() => setValue({ ...value, items: value.items.filter((_, i) => i !== index) })}
                >
                  &times;
                </button>
              </div>
            </div>
          ))}
          <button
            type="button"
            className="btn btn-outline-secondary btn-sm mb-3"
            onClick={() => setValue({ ...value, items: [...value.items, { icon: 'leaf', title: '', text: '' }] })}
          >
            Add card
          </button>
        </>
      )}
    </SectionForm>
  );
}

function UsageForm({ initial, onSaved }: { initial: UsageContent; onSaved: () => void }) {
  return (
    <SectionForm section="usage" initial={initial} onSaved={onSaved}>
      {(value, setValue) => (
        <>
          <TextField label="Title" value={value.title} onChange={(v) => setValue({ ...value, title: v })} />

          <label className="form-label">Steps</label>
          {value.steps.map((step, index) => (
            <div className="row g-2 mb-2" key={index}>
              <div className="col-5">
                <input
                  className="form-control"
                  placeholder="Title"
                  value={step.title}
                  onChange={(event) =>
                    setValue({ ...value, steps: value.steps.map((s, i) => (i === index ? { ...s, title: event.target.value } : s)) })
                  }
                  required
                />
              </div>
              <div className="col-6">
                <input
                  className="form-control"
                  placeholder="Text"
                  value={step.text}
                  onChange={(event) =>
                    setValue({ ...value, steps: value.steps.map((s, i) => (i === index ? { ...s, text: event.target.value } : s)) })
                  }
                  required
                />
              </div>
              <div className="col-1">
                <button
                  type="button"
                  className="btn btn-outline-danger btn-sm"
                  disabled={value.steps.length <= 1}
                  onClick={() => setValue({ ...value, steps: value.steps.filter((_, i) => i !== index) })}
                >
                  &times;
                </button>
              </div>
            </div>
          ))}
          <button
            type="button"
            className="btn btn-outline-secondary btn-sm mb-3"
            onClick={() => setValue({ ...value, steps: [...value.steps, { title: '', text: '' }] })}
          >
            Add step
          </button>
        </>
      )}
    </SectionForm>
  );
}

function TrustForm({ initial, onSaved }: { initial: TrustContent; onSaved: () => void }) {
  return (
    <SectionForm section="trust" initial={initial} onSaved={onSaved}>
      {(value, setValue) => (
        <>
          <TextField label="Title" value={value.title} onChange={(v) => setValue({ ...value, title: v })} />

          <label className="form-label">Items</label>
          {value.items.map((item, index) => (
            <div className="row g-2 mb-2" key={index}>
              <div className="col-3">
                <IconSelect
                  value={item.icon}
                  onChange={(icon) =>
                    setValue({ ...value, items: value.items.map((it, i) => (i === index ? { ...it, icon } : it)) })
                  }
                />
              </div>
              <div className="col-8">
                <input
                  className="form-control"
                  placeholder="Text"
                  value={item.text}
                  onChange={(event) =>
                    setValue({ ...value, items: value.items.map((it, i) => (i === index ? { ...it, text: event.target.value } : it)) })
                  }
                  required
                />
              </div>
              <div className="col-1">
                <button
                  type="button"
                  className="btn btn-outline-danger btn-sm"
                  disabled={value.items.length <= 1}
                  onClick={() => setValue({ ...value, items: value.items.filter((_, i) => i !== index) })}
                >
                  &times;
                </button>
              </div>
            </div>
          ))}
          <button
            type="button"
            className="btn btn-outline-secondary btn-sm mb-3"
            onClick={() => setValue({ ...value, items: [...value.items, { icon: 'leaf', text: '' }] })}
          >
            Add item
          </button>
        </>
      )}
    </SectionForm>
  );
}

function DeliveryForm({ initial, onSaved }: { initial: DeliveryContent; onSaved: () => void }) {
  return (
    <SectionForm section="delivery" initial={initial} onSaved={onSaved}>
      {(value, setValue) => (
        <>
          <TextField label="Title" value={value.title} onChange={(v) => setValue({ ...value, title: v })} />
          <div className="mb-3">
            <label className="form-label">Icon</label>
            <IconSelect value={value.icon} onChange={(icon) => setValue({ ...value, icon })} />
          </div>
          <TextField label="Text" value={value.text} onChange={(v) => setValue({ ...value, text: v })} multiline />
        </>
      )}
    </SectionForm>
  );
}

function BioForm({ initial, onSaved }: { initial: BioContent; onSaved: () => void }) {
  return (
    <SectionForm section="bio" initial={initial} onSaved={onSaved}>
      {(value, setValue) => (
        <>
          <TextField label="Eyebrow" value={value.eyebrow} onChange={(v) => setValue({ ...value, eyebrow: v })} />
          <TextField label="Title" value={value.title} onChange={(v) => setValue({ ...value, title: v })} />
          <TextField label="Text" value={value.text} onChange={(v) => setValue({ ...value, text: v })} multiline />
        </>
      )}
    </SectionForm>
  );
}

function FaqForm({ initial, onSaved }: { initial: FaqContent; onSaved: () => void }) {
  return (
    <SectionForm section="faq" initial={initial} onSaved={onSaved}>
      {(value, setValue) => (
        <>
          <TextField label="Title" value={value.title} onChange={(v) => setValue({ ...value, title: v })} />

          <label className="form-label">Questions</label>
          {value.items.map((item, index) => (
            <div className="row g-2 mb-2" key={index}>
              <div className="col-5">
                <input
                  className="form-control"
                  placeholder="Question"
                  value={item.question}
                  onChange={(event) =>
                    setValue({ ...value, items: value.items.map((it, i) => (i === index ? { ...it, question: event.target.value } : it)) })
                  }
                  required
                />
              </div>
              <div className="col-6">
                <input
                  className="form-control"
                  placeholder="Answer"
                  value={item.answer}
                  onChange={(event) =>
                    setValue({ ...value, items: value.items.map((it, i) => (i === index ? { ...it, answer: event.target.value } : it)) })
                  }
                  required
                />
              </div>
              <div className="col-1">
                <button
                  type="button"
                  className="btn btn-outline-danger btn-sm"
                  disabled={value.items.length <= 1}
                  onClick={() => setValue({ ...value, items: value.items.filter((_, i) => i !== index) })}
                >
                  &times;
                </button>
              </div>
            </div>
          ))}
          <button
            type="button"
            className="btn btn-outline-secondary btn-sm mb-3"
            onClick={() => setValue({ ...value, items: [...value.items, { question: '', answer: '' }] })}
          >
            Add question
          </button>
        </>
      )}
    </SectionForm>
  );
}

export default function ContentPage() {
  const [reloadKey, setReloadKey] = useState(0);
  const { data, isLoading, error } = useAsync(fetchAdminHomepageContent, [reloadKey], 'Could not load homepage content.');
  const [activeTab, setActiveTab] = useState<HomepageSection>('hero');

  return (
    <div>
      <h1 className="h3 mb-1">Homepage content</h1>
      <p className="text-muted mb-4">Edits here go live on the public homepage immediately.</p>

      {isLoading && <LoadingState message="Loading content..." />}
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

          {activeTab === 'hero' && <HeroForm key="hero" initial={data.hero} onSaved={() => setReloadKey((k) => k + 1)} />}
          {activeTab === 'featured' && (
            <FeaturedForm key="featured" initial={data.featured} onSaved={() => setReloadKey((k) => k + 1)} />
          )}
          {activeTab === 'benefits' && (
            <BenefitsForm key="benefits" initial={data.benefits} onSaved={() => setReloadKey((k) => k + 1)} />
          )}
          {activeTab === 'usage' && <UsageForm key="usage" initial={data.usage} onSaved={() => setReloadKey((k) => k + 1)} />}
          {activeTab === 'trust' && <TrustForm key="trust" initial={data.trust} onSaved={() => setReloadKey((k) => k + 1)} />}
          {activeTab === 'delivery' && (
            <DeliveryForm key="delivery" initial={data.delivery} onSaved={() => setReloadKey((k) => k + 1)} />
          )}
          {activeTab === 'bio' && <BioForm key="bio" initial={data.bio} onSaved={() => setReloadKey((k) => k + 1)} />}
          {activeTab === 'faq' && <FaqForm key="faq" initial={data.faq} onSaved={() => setReloadKey((k) => k + 1)} />}
        </>
      )}
    </div>
  );
}
