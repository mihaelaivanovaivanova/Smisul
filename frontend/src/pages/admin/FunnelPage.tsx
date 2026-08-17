import { useState } from 'react';
import type { ChangeEvent, FormEvent, ReactNode } from 'react';
import {
  fetchAdminFunnel,
  toggleFunnel,
  updateFunnelContentSection,
  updateFunnelPackages,
  uploadFunnelFaqAttachment,
} from '../../api/admin/funnel';
import { createProduct, fetchAdminProduct, fetchAdminProducts, updateProduct } from '../../api/admin/products';
import type { ProductPayload } from '../../api/admin/products';
import { useAsync } from '../../hooks/useAsync';
import { getErrorMessage, getValidationErrors } from '../../api/errors';
import LoadingState from '../../components/LoadingState';
import ErrorState from '../../components/ErrorState';
import FormModal from '../../components/admin/FormModal';
import VariantManager from '../../components/admin/VariantManager';
import ProductMediaManager from '../../components/admin/ProductMediaManager';
import FieldError from '../../components/FieldError';
import type { Media, ProductStatus, ProductVariant } from '../../types/product';
import type { AdminProduct } from '../../types/admin';
import type {
  FunnelAdminPayload,
  FunnelComparisonContent,
  FunnelFaqContent,
  FunnelFaqItem,
  FunnelFeaturesContent,
  FunnelFinalCtaContent,
  FunnelHeroContent,
  FunnelHistoryContent,
  FunnelIntroContent,
  FunnelNaturalEcoContent,
  FunnelAwarenessContent,
  FunnelPackage,
  FunnelPositioningContent,
  FunnelScienceContent,
  FunnelSection,
  FunnelWhyContent,
} from '../../types/funnel';

const TABS: { key: FunnelSection; label: string }[] = [
  { key: 'hero', label: 'Hero' },
  { key: 'intro', label: 'Intro' },
  { key: 'why', label: 'Why Miswak' },
  { key: 'features', label: 'Features' },
  { key: 'comparison', label: 'Comparison' },
  { key: 'history', label: 'History' },
  { key: 'natural_eco', label: 'Natural / Eco' },
  { key: 'science', label: 'Science' },
  { key: 'awareness', label: 'Skepticism / Honesty' },
  { key: 'positioning', label: 'Positioning statement' },
  { key: 'final_cta', label: 'Final CTA' },
  { key: 'faq', label: 'FAQ' },
];

type TabKey = FunnelSection | 'packages';

function TextField({
  label,
  value,
  onChange,
  multiline = false,
  required = true,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  multiline?: boolean;
  required?: boolean;
}) {
  const id = label.toLowerCase().replace(/\s+/g, '-');

  return (
    <div className="mb-3">
      <label className="form-label" htmlFor={id}>
        {label}
      </label>
      {multiline ? (
        <textarea id={id} className="form-control" rows={3} value={value} onChange={(event) => onChange(event.target.value)} required={required} />
      ) : (
        <input id={id} className="form-control" value={value} onChange={(event) => onChange(event.target.value)} required={required} />
      )}
    </div>
  );
}

function RepeatableStringList({
  label,
  values,
  onChange,
  minItems = 1,
}: {
  label: string;
  values: string[];
  onChange: (values: string[]) => void;
  minItems?: number;
}) {
  return (
    <div className="mb-3">
      <label className="form-label">{label}</label>
      {values.map((value, index) => (
        <div className="input-group mb-2" key={index}>
          <input
            className="form-control"
            value={value}
            onChange={(event) => onChange(values.map((v, i) => (i === index ? event.target.value : v)))}
            required
          />
          <button
            type="button"
            className="btn btn-outline-danger"
            disabled={values.length <= minItems}
            onClick={() => onChange(values.filter((_, i) => i !== index))}
          >
            &times;
          </button>
        </div>
      ))}
      <button type="button" className="btn btn-outline-secondary btn-sm" onClick={() => onChange([...values, ''])}>
        Add
      </button>
    </div>
  );
}

function RepeatableObjectList<T extends Record<string, string>>({
  label,
  items,
  onChange,
  fields,
  emptyItem,
  minItems = 1,
}: {
  label: string;
  items: T[];
  onChange: (items: T[]) => void;
  fields: { key: keyof T; placeholder: string }[];
  emptyItem: T;
  minItems?: number;
}) {
  return (
    <div className="mb-3">
      <label className="form-label">{label}</label>
      {items.map((item, index) => (
        <div className="row g-2 mb-2 align-items-center" key={index}>
          {fields.map((field) => (
            <div className="col" key={String(field.key)}>
              <input
                className="form-control"
                placeholder={field.placeholder}
                value={item[field.key]}
                onChange={(event) =>
                  onChange(items.map((it, i) => (i === index ? { ...it, [field.key]: event.target.value } : it)))
                }
                required
              />
            </div>
          ))}
          <div className="col-auto">
            <button
              type="button"
              className="btn btn-outline-danger btn-sm"
              disabled={items.length <= minItems}
              onClick={() => onChange(items.filter((_, i) => i !== index))}
            >
              &times;
            </button>
          </div>
        </div>
      ))}
      <button type="button" className="btn btn-outline-secondary btn-sm mb-3" onClick={() => onChange([...items, emptyItem])}>
        Add
      </button>
    </div>
  );
}

interface SectionFormProps<T> {
  section: FunnelSection;
  initial: T;
  onSaved: () => void;
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
      const updated = await updateFunnelContentSection(section, value as never);
      setValue(updated as T);
      setSaved(true);
      onSaved();
    } catch (err) {
      setError(getErrorMessage(err, 'Could not save this section.'));
    } finally {
      setIsSaving(false);
    }
  }

  return (
    <form onSubmit={(event) => void handleSubmit(event)} style={{ maxWidth: 720 }}>
      {error && <div className="alert alert-danger">{error}</div>}
      {saved && <div className="alert alert-success">Saved. Changes are live on the funnel page.</div>}
      {children(value, setValue)}
      <button type="submit" className="btn btn-primary" disabled={isSaving}>
        {isSaving && <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>}
        Save
      </button>
    </form>
  );
}

const ICON_HINT = 'Icon name — see IconName in src/components/icons/Icon.tsx (e.g. leaf, recycle, truck)';

function HeroForm({ initial, onSaved }: { initial: FunnelHeroContent; onSaved: () => void }) {
  return (
    <SectionForm section="hero" initial={initial} onSaved={onSaved}>
      {(value, setValue) => (
        <>
          <TextField
            label="Eyebrow (optional kicker above the title)"
            value={value.eyebrow ?? ''}
            onChange={(v) => setValue({ ...value, eyebrow: v })}
            required={false}
          />
          <TextField label="Title" value={value.title} onChange={(v) => setValue({ ...value, title: v })} multiline />
          <TextField label="Body" value={value.body} onChange={(v) => setValue({ ...value, body: v })} multiline />
          <TextField label="Primary button text" value={value.cta_primary} onChange={(v) => setValue({ ...value, cta_primary: v })} />
          <TextField label="Secondary button text" value={value.cta_secondary} onChange={(v) => setValue({ ...value, cta_secondary: v })} />
          <div className="form-text mb-2">{ICON_HINT}</div>
          <RepeatableObjectList
            label="Trust items"
            items={value.trust_items}
            onChange={(trust_items) => setValue({ ...value, trust_items: trust_items as FunnelHeroContent['trust_items'] })}
            fields={[
              { key: 'icon', placeholder: 'Icon' },
              { key: 'label', placeholder: 'Label' },
            ]}
            emptyItem={{ icon: 'leaf', label: '' }}
          />
        </>
      )}
    </SectionForm>
  );
}

function IntroForm({ initial, onSaved }: { initial: FunnelIntroContent; onSaved: () => void }) {
  return (
    <SectionForm section="intro" initial={initial} onSaved={onSaved}>
      {(value, setValue) => (
        <>
          <TextField label="Title" value={value.title} onChange={(v) => setValue({ ...value, title: v })} />
          <RepeatableStringList label="Paragraphs" values={value.paragraphs} onChange={(paragraphs) => setValue({ ...value, paragraphs })} />
          <TextField
            label="Benefits list heading"
            value={value.benefits_title}
            onChange={(v) => setValue({ ...value, benefits_title: v })}
          />
          <RepeatableObjectList
            label="Benefits"
            items={value.benefits}
            onChange={(benefits) => setValue({ ...value, benefits: benefits as FunnelIntroContent['benefits'] })}
            fields={[
              { key: 'label', placeholder: 'Label' },
              { key: 'description', placeholder: 'Description' },
            ]}
            emptyItem={{ label: '', description: '' }}
          />
        </>
      )}
    </SectionForm>
  );
}

function WhyForm({ initial, onSaved }: { initial: FunnelWhyContent; onSaved: () => void }) {
  return (
    <SectionForm section="why" initial={initial} onSaved={onSaved}>
      {(value, setValue) => (
        <>
          <TextField label="Title" value={value.title} onChange={(v) => setValue({ ...value, title: v })} />
          <div className="form-text mb-2">{ICON_HINT}</div>
          <RepeatableObjectList
            label="Cards"
            items={value.cards}
            onChange={(cards) => setValue({ ...value, cards: cards as FunnelWhyContent['cards'] })}
            fields={[
              { key: 'icon', placeholder: 'Icon' },
              { key: 'title', placeholder: 'Title' },
              { key: 'text', placeholder: 'Text' },
            ]}
            emptyItem={{ icon: 'leaf', title: '', text: '' }}
          />
        </>
      )}
    </SectionForm>
  );
}

function ComparisonForm({ initial, onSaved }: { initial: FunnelComparisonContent; onSaved: () => void }) {
  return (
    <SectionForm section="comparison" initial={initial} onSaved={onSaved}>
      {(value, setValue) => (
        <>
          <TextField label="Title" value={value.title} onChange={(v) => setValue({ ...value, title: v })} />
          <TextField
            label="Miswak column label"
            value={value.miswak_label}
            onChange={(v) => setValue({ ...value, miswak_label: v })}
          />
          <TextField
            label="Toothbrush column label"
            value={value.brush_label}
            onChange={(v) => setValue({ ...value, brush_label: v })}
          />
          <div className="form-text mb-2">
            Each row has its own value per column — "✓", "✕", or "△" render as marks; anything else (e.g. "Допълва",
            "обикновено ✕") renders as plain text.
          </div>
          <RepeatableObjectList
            label="Rows"
            items={value.rows}
            onChange={(rows) => setValue({ ...value, rows: rows as FunnelComparisonContent['rows'] })}
            fields={[
              { key: 'label', placeholder: 'Row label' },
              { key: 'miswak_value', placeholder: 'Miswak value (✓ / ✕ / △ / text)' },
              { key: 'brush_value', placeholder: 'Brush value (✓ / ✕ / △ / text)' },
            ]}
            emptyItem={{ label: '', miswak_value: '✓', brush_value: '✕' }}
          />
          <TextField label="Closing line" value={value.closing} onChange={(v) => setValue({ ...value, closing: v })} />
        </>
      )}
    </SectionForm>
  );
}

function HistoryForm({ initial, onSaved }: { initial: FunnelHistoryContent; onSaved: () => void }) {
  return (
    <SectionForm section="history" initial={initial} onSaved={onSaved}>
      {(value, setValue) => (
        <>
          <TextField label="Title" value={value.title} onChange={(v) => setValue({ ...value, title: v })} />
          <TextField label="Subtitle" value={value.subtitle} onChange={(v) => setValue({ ...value, subtitle: v })} />
          <div className="form-text mb-2">Keep this short — the whole section is meant to fit roughly one mobile screen.</div>
          <TextField label="Body" value={value.body} onChange={(v) => setValue({ ...value, body: v })} multiline />
        </>
      )}
    </SectionForm>
  );
}

function FeaturesForm({ initial, onSaved }: { initial: FunnelFeaturesContent; onSaved: () => void }) {
  return (
    <SectionForm section="features" initial={initial} onSaved={onSaved}>
      {(value, setValue) => (
        <>
          <TextField label="Title" value={value.title} onChange={(v) => setValue({ ...value, title: v })} />
          <div className="form-text mb-2">
            Icon is an image filename under /funnel/v2/ (no extension), e.g. "icon-feature-natural-100" — not an
            IconName SVG icon. Avoid unsupported absolute claims (e.g. "zero waste", unqualified "biodegradable").
          </div>
          <RepeatableObjectList
            label="Items"
            items={value.items}
            onChange={(items) => setValue({ ...value, items })}
            fields={[
              { key: 'icon', placeholder: 'Icon filename' },
              { key: 'label', placeholder: 'Label' },
            ]}
            emptyItem={{ icon: '', label: '' }}
          />
        </>
      )}
    </SectionForm>
  );
}

function NaturalEcoForm({ initial, onSaved }: { initial: FunnelNaturalEcoContent; onSaved: () => void }) {
  return (
    <SectionForm section="natural_eco" initial={initial} onSaved={onSaved}>
      {(value, setValue) => (
        <>
          <TextField label="Eyebrow" value={value.eyebrow} onChange={(v) => setValue({ ...value, eyebrow: v })} />
          <TextField label="Title" value={value.title} onChange={(v) => setValue({ ...value, title: v })} />
          <RepeatableStringList label="Paragraphs" values={value.paragraphs} onChange={(paragraphs) => setValue({ ...value, paragraphs })} />
          <div className="form-text mb-2">
            Avoid unsupported absolute claims (zero waste, unqualified "100% biodegradable" if packaging isn't
            verified, universal microplastic-free claims, or any environmental claim without evidence).
          </div>
          <TextField
            label="Brand statement"
            value={value.brand_statement}
            onChange={(v) => setValue({ ...value, brand_statement: v })}
            multiline
          />
        </>
      )}
    </SectionForm>
  );
}

function ScienceForm({ initial, onSaved }: { initial: FunnelScienceContent; onSaved: () => void }) {
  return (
    <SectionForm section="science" initial={initial} onSaved={onSaved}>
      {(value, setValue) => (
        <>
          <TextField label="Eyebrow" value={value.eyebrow} onChange={(v) => setValue({ ...value, eyebrow: v })} />
          <TextField label="Title" value={value.title} onChange={(v) => setValue({ ...value, title: v })} />
          <TextField label="Intro" value={value.intro} onChange={(v) => setValue({ ...value, intro: v })} multiline />
          <div className="form-text mb-2">
            Cards: title carries its own leading emoji (not a separate icon field). Source URL/label are optional —
            leave both blank for a card with no external reference. Every scientific claim must stay defensible; do
            not rewrite/strengthen wording without re-checking the source.
          </div>
          <RepeatableObjectList
            label="Cards"
            items={value.cards}
            onChange={(cards) => setValue({ ...value, cards: cards as FunnelScienceContent['cards'] })}
            fields={[
              { key: 'title', placeholder: 'Title (with emoji)' },
              { key: 'body', placeholder: 'Body' },
              { key: 'source_url', placeholder: 'Source URL (optional)' },
              { key: 'source_label', placeholder: 'Source link label (optional)' },
            ]}
            emptyItem={{ title: '', body: '', source_url: '', source_label: '' }}
          />
          <TextField
            label="Callout — stat line"
            value={value.callout.stat}
            onChange={(v) => setValue({ ...value, callout: { ...value.callout, stat: v } })}
          />
          <TextField
            label="Callout — supporting copy"
            value={value.callout.body}
            onChange={(v) => setValue({ ...value, callout: { ...value.callout, body: v } })}
            multiline
          />
          <TextField
            label="Safety — title"
            value={value.safety.title}
            onChange={(v) => setValue({ ...value, safety: { ...value.safety, title: v } })}
          />
          <TextField
            label="Safety — body"
            value={value.safety.body}
            onChange={(v) => setValue({ ...value, safety: { ...value.safety, body: v } })}
            multiline
          />
        </>
      )}
    </SectionForm>
  );
}

function AwarenessForm({ initial, onSaved }: { initial: FunnelAwarenessContent; onSaved: () => void }) {
  return (
    <SectionForm section="awareness" initial={initial} onSaved={onSaved}>
      {(value, setValue) => (
        <>
          <TextField label="Title" value={value.title} onChange={(v) => setValue({ ...value, title: v })} />
          <TextField label="Subtitle" value={value.subtitle} onChange={(v) => setValue({ ...value, subtitle: v })} />
          <TextField label="Body" value={value.body} onChange={(v) => setValue({ ...value, body: v })} multiline />
        </>
      )}
    </SectionForm>
  );
}

function PositioningForm({ initial, onSaved }: { initial: FunnelPositioningContent; onSaved: () => void }) {
  return (
    <SectionForm section="positioning" initial={initial} onSaved={onSaved}>
      {(value, setValue) => (
        <>
          <TextField label="Title" value={value.title} onChange={(v) => setValue({ ...value, title: v })} />
          <TextField label="Body" value={value.body} onChange={(v) => setValue({ ...value, body: v })} multiline />
        </>
      )}
    </SectionForm>
  );
}

function FinalCtaForm({ initial, onSaved }: { initial: FunnelFinalCtaContent; onSaved: () => void }) {
  return (
    <SectionForm section="final_cta" initial={initial} onSaved={onSaved}>
      {(value, setValue) => (
        <>
          <TextField label="Title" value={value.title} onChange={(v) => setValue({ ...value, title: v })} />
          <RepeatableStringList label="Paragraphs" values={value.paragraphs} onChange={(paragraphs) => setValue({ ...value, paragraphs })} />
          <TextField label="Button text" value={value.cta} onChange={(v) => setValue({ ...value, cta: v })} />
          <div className="form-text mb-2">{ICON_HINT}</div>
          <RepeatableObjectList
            label="Trust items"
            items={value.trust_items}
            onChange={(trust_items) => setValue({ ...value, trust_items: trust_items as FunnelFinalCtaContent['trust_items'] })}
            fields={[
              { key: 'icon', placeholder: 'Icon' },
              { key: 'label', placeholder: 'Label' },
            ]}
            emptyItem={{ icon: 'leaf', label: '' }}
          />
        </>
      )}
    </SectionForm>
  );
}

const EMPTY_FAQ_ITEM: FunnelFaqItem = { question: '', answer: '', attachment_url: '', attachment_label: '' };

function FaqItemEditor({
  item,
  onChange,
  onRemove,
  canRemove,
}: {
  item: FunnelFaqItem;
  onChange: (item: FunnelFaqItem) => void;
  onRemove: () => void;
  canRemove: boolean;
}) {
  const [isUploading, setIsUploading] = useState(false);
  const [uploadError, setUploadError] = useState<string | null>(null);

  async function handleFileSelected(event: ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) {
      return;
    }

    setIsUploading(true);
    setUploadError(null);
    try {
      const uploaded = await uploadFunnelFaqAttachment(file);
      onChange({ ...item, attachment_url: uploaded.url, attachment_label: item.attachment_label || uploaded.filename });
    } catch (err) {
      setUploadError(getErrorMessage(err, 'Could not upload the PDF.'));
    } finally {
      setIsUploading(false);
    }
  }

  return (
    <div className="border rounded p-3 mb-3">
      <div className="d-flex justify-content-between align-items-start mb-2">
        <strong className="text-muted small">Question</strong>
        <button type="button" className="btn btn-outline-danger btn-sm" disabled={!canRemove} onClick={onRemove}>
          &times; Remove
        </button>
      </div>
      <input
        className="form-control mb-2"
        placeholder="Question"
        value={item.question}
        onChange={(event) => onChange({ ...item, question: event.target.value })}
        required
      />
      <textarea
        className="form-control mb-2"
        rows={3}
        placeholder="Answer"
        value={item.answer}
        onChange={(event) => onChange({ ...item, answer: event.target.value })}
        required
      />
      <label className="form-label small text-muted mb-1">Attachment (optional — shown as a download link under the answer)</label>
      <div className="d-flex gap-2 align-items-center mb-2">
        <input
          className="form-control"
          placeholder="Attachment URL"
          value={item.attachment_url}
          onChange={(event) => onChange({ ...item, attachment_url: event.target.value })}
        />
        <label className="btn btn-outline-secondary btn-sm text-nowrap mb-0">
          {isUploading && <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>}
          {item.attachment_url ? 'Replace PDF' : 'Upload PDF'}
          <input type="file" accept="application/pdf" className="d-none" onChange={(event) => void handleFileSelected(event)} disabled={isUploading} />
        </label>
        {item.attachment_url && (
          <button type="button" className="btn btn-outline-secondary btn-sm text-nowrap" onClick={() => onChange({ ...item, attachment_url: '', attachment_label: '' })}>
            Clear
          </button>
        )}
      </div>
      {uploadError && <div className="alert alert-danger py-1 px-2 small">{uploadError}</div>}
      {item.attachment_url && (
        <input
          className="form-control"
          placeholder="Attachment link text"
          value={item.attachment_label}
          onChange={(event) => onChange({ ...item, attachment_label: event.target.value })}
        />
      )}
    </div>
  );
}

function FaqForm({ initial, onSaved }: { initial: FunnelFaqContent; onSaved: () => void }) {
  return (
    <SectionForm section="faq" initial={initial} onSaved={onSaved}>
      {(value, setValue) => (
        <>
          <TextField label="Title" value={value.title} onChange={(v) => setValue({ ...value, title: v })} />
          <label className="form-label">Questions</label>
          {value.items.map((item, index) => (
            <FaqItemEditor
              key={index}
              item={item}
              canRemove={value.items.length > 1}
              onChange={(next) => setValue({ ...value, items: value.items.map((it, i) => (i === index ? next : it)) })}
              onRemove={() => setValue({ ...value, items: value.items.filter((_, i) => i !== index) })}
            />
          ))}
          <button
            type="button"
            className="btn btn-outline-secondary btn-sm mb-3"
            onClick={() => setValue({ ...value, items: [...value.items, EMPTY_FAQ_ITEM] })}
          >
            Add
          </button>
        </>
      )}
    </SectionForm>
  );
}

const EMPTY_PACKAGE: FunnelPackage = {
  variant_id: 0,
  badge: '',
  detail: '',
  value_label: '',
  button_text: '',
};

const EMPTY_PRODUCT_FORM: ProductPayload = {
  name: '',
  short_description: '',
  description: '',
  status: 'draft',
  category_ids: [],
  quantity: 0,
  price: 0,
};

function PackagesForm({ initial, onSaved }: { initial: FunnelAdminPayload; onSaved: () => void }) {
  const [productId, setProductId] = useState<number | null>(initial.product_id);
  const [packages, setPackages] = useState<FunnelPackage[]>(
    initial.packages.length === 4 ? initial.packages : [EMPTY_PACKAGE, EMPTY_PACKAGE, EMPTY_PACKAGE, EMPTY_PACKAGE],
  );
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  const [productsReloadKey, setProductsReloadKey] = useState(0);
  const { data: products } = useAsync(
    () => fetchAdminProducts({ sort: 'name', per_page: 100 }),
    [productsReloadKey],
    'Could not load products.',
  );
  const publishedProducts = (products?.data ?? []).filter((candidate) => candidate.status === 'published');

  const [productReloadKey, setProductReloadKey] = useState(0);
  const { data: product } = useAsync(
    () => (productId ? fetchAdminProduct(productId) : Promise.resolve(null)),
    [productId, productReloadKey],
    'Could not load the product’s variants.',
  );

  // Reuses the exact same create/edit flow as the Products tab
  // (frontend/src/pages/admin/ProductsPage.tsx) — same fields, same
  // "stay open in edit mode after creation" behavior, same VariantManager/
  // ProductMediaManager — so a funnel product never has to be managed from
  // two different screens.
  const [showProductForm, setShowProductForm] = useState(false);
  const [productFormMode, setProductFormMode] = useState<'create' | 'edit'>('create');
  const [modalProductId, setModalProductId] = useState<number | null>(null);
  const [modalMedia, setModalMedia] = useState<Media[]>([]);
  const [modalVariants, setModalVariants] = useState<ProductVariant[]>([]);
  const [justCreatedProduct, setJustCreatedProduct] = useState(false);
  const [productForm, setProductForm] = useState<ProductPayload>(EMPTY_PRODUCT_FORM);
  const [productFormErrors, setProductFormErrors] = useState<Record<string, string>>({});
  const [productFormError, setProductFormError] = useState<string | null>(null);
  const [isSubmittingProduct, setIsSubmittingProduct] = useState(false);

  function updatePackage(index: number, changes: Partial<FunnelPackage>) {
    setPackages(packages.map((pkg, i) => (i === index ? { ...pkg, ...changes } : pkg)));
  }

  function openCreateProduct() {
    setProductFormMode('create');
    setModalProductId(null);
    setModalMedia([]);
    setModalVariants([]);
    setJustCreatedProduct(false);
    setProductForm(EMPTY_PRODUCT_FORM);
    setProductFormErrors({});
    setProductFormError(null);
    setShowProductForm(true);
  }

  function openEditProduct(current: AdminProduct) {
    setProductFormMode('edit');
    setModalProductId(current.id);
    setModalMedia(current.media ?? []);
    setModalVariants(current.variants ?? []);
    setJustCreatedProduct(false);
    setProductForm({
      name: current.name,
      short_description: current.short_description ?? '',
      description: current.description ?? '',
      status: current.status,
      category_ids: current.categories.map((category) => category.id),
      quantity: current.quantity ?? 0,
      price: current.price ?? 0,
    });
    setProductFormErrors({});
    setProductFormError(null);
    setShowProductForm(true);
  }

  function closeProductForm() {
    setShowProductForm(false);
    setProductReloadKey((key) => key + 1);
    setProductsReloadKey((key) => key + 1);
  }

  async function handleProductFormSubmit(): Promise<void> {
    setIsSubmittingProduct(true);
    setProductFormErrors({});
    setProductFormError(null);

    try {
      if (productFormMode === 'edit' && modalProductId) {
        await updateProduct(modalProductId, productForm);
        closeProductForm();
      } else {
        // Media/variants can only be attached to a product that already
        // exists, so creating stays in the same modal (now in "edit" mode)
        // instead of closing.
        const created = await createProduct(productForm);
        setModalProductId(created.id);
        setModalMedia(created.media ?? []);
        setModalVariants(created.variants ?? []);
        setJustCreatedProduct(true);
        setProductFormMode('edit');
        setProductId(created.id);
        setProductsReloadKey((key) => key + 1);
      }
    } catch (err) {
      setProductFormErrors(getValidationErrors(err));
      setProductFormError(getErrorMessage(err, 'Could not save the product.'));
    } finally {
      setIsSubmittingProduct(false);
    }
  }

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();

    if (!productId) {
      setError('Select a product first.');
      return;
    }

    setIsSaving(true);
    setError(null);
    setSaved(false);

    try {
      await updateFunnelPackages(productId, packages);
      setSaved(true);
      onSaved();
    } catch (err) {
      setError(getErrorMessage(err, 'Could not save the packages.'));
    } finally {
      setIsSaving(false);
    }
  }

  return (
    <>
      <form onSubmit={(event) => void handleSubmit(event)} style={{ maxWidth: 960 }}>
        {error && <div className="alert alert-danger">{error}</div>}
        {saved && <div className="alert alert-success">Saved. Changes are live on the funnel page.</div>}

      <div className="mb-3">
        <label className="form-label" htmlFor="funnel-product">
          Product
        </label>
        <div className="d-flex gap-2 flex-wrap">
          <select
            id="funnel-product"
            className="form-select"
            style={{ maxWidth: 360 }}
            value={productId ?? ''}
            onChange={(event) => setProductId(event.target.value ? Number(event.target.value) : null)}
          >
            <option value="">Select a product</option>
            {publishedProducts.map((candidate) => (
              <option key={candidate.id} value={candidate.id}>
                {candidate.name}
              </option>
            ))}
          </select>
          <button
            type="button"
            className="btn btn-outline-secondary"
            disabled={!product}
            onClick={() => product && openEditProduct(product)}
          >
            Edit product
          </button>
          <button type="button" className="btn btn-outline-secondary" onClick={openCreateProduct}>
            New product
          </button>
        </div>
        <div className="form-text">Only published products can power the funnel page.</div>
      </div>

      {packages.map((pkg, index) => (
        <div className="card mb-3" key={index}>
          <div className="card-body row g-2">
            <div className="col-12 col-md-3">
              <label className="form-label">Variant</label>
              <select
                className="form-select"
                value={pkg.variant_id || ''}
                onChange={(event) => updatePackage(index, { variant_id: Number(event.target.value) })}
                required
              >
                <option value="">Select a variant</option>
                {(product?.variants ?? []).map((variant) => (
                  <option key={variant.id} value={variant.id}>
                    {variant.name}
                  </option>
                ))}
              </select>
            </div>
            <div className="col-6 col-md-2">
              <label className="form-label">Badge</label>
              <input className="form-control" value={pkg.badge} onChange={(event) => updatePackage(index, { badge: event.target.value })} required />
            </div>
            <div className="col-6 col-md-2">
              <label className="form-label">Detail</label>
              <input className="form-control" value={pkg.detail} onChange={(event) => updatePackage(index, { detail: event.target.value })} required />
            </div>
            <div className="col-6 col-md-2">
              <label className="form-label">Value label</label>
              <input
                className="form-control"
                value={pkg.value_label}
                onChange={(event) => updatePackage(index, { value_label: event.target.value })}
                required
              />
            </div>
            <div className="col-6 col-md-3">
              <label className="form-label">Button text</label>
              <input
                className="form-control"
                value={pkg.button_text}
                onChange={(event) => updatePackage(index, { button_text: event.target.value })}
                required
              />
            </div>
          </div>
        </div>
      ))}

        <button type="submit" className="btn btn-primary" disabled={isSaving}>
          {isSaving && <span className="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>}
          Save
        </button>
      </form>

      <FormModal
        show={showProductForm}
        title={productFormMode === 'edit' ? 'Edit product' : 'New product'}
        isSubmitting={isSubmittingProduct}
        error={productFormError}
        onSubmit={() => void handleProductFormSubmit()}
        onClose={closeProductForm}
      >
        {justCreatedProduct && (
          <div className="alert alert-success mb-0">Product created. Add pack sizes and photos below, then close when done.</div>
        )}

        <div>
          <label className="form-label" htmlFor="funnel-product-name">
            Name
          </label>
          <input
            id="funnel-product-name"
            className={`form-control ${productFormErrors.name ? 'is-invalid' : ''}`}
            value={productForm.name}
            onChange={(event) => setProductForm({ ...productForm, name: event.target.value })}
            required
          />
          <FieldError message={productFormErrors.name} />
        </div>

        <div>
          <label className="form-label" htmlFor="funnel-product-short-description">
            Short description
          </label>
          <input
            id="funnel-product-short-description"
            className="form-control"
            value={productForm.short_description ?? ''}
            onChange={(event) => setProductForm({ ...productForm, short_description: event.target.value })}
          />
        </div>

        <div>
          <label className="form-label" htmlFor="funnel-product-description">
            Description
          </label>
          <textarea
            id="funnel-product-description"
            className="form-control"
            rows={4}
            value={productForm.description ?? ''}
            onChange={(event) => setProductForm({ ...productForm, description: event.target.value })}
          />
        </div>

        <div>
          <label className="form-label" htmlFor="funnel-product-status">
            Status
          </label>
          <select
            id="funnel-product-status"
            className="form-select"
            value={productForm.status}
            onChange={(event) => setProductForm({ ...productForm, status: event.target.value as ProductStatus })}
          >
            <option value="draft">Draft</option>
            <option value="published">Published</option>
            <option value="archived">Archived</option>
          </select>
          <div className="form-text">Only published products can power the funnel page.</div>
        </div>

        <div className="row g-3">
          <div className="col-sm-6">
            <label className="form-label" htmlFor="funnel-product-quantity">
              Quantity in stock
            </label>
            <input
              id="funnel-product-quantity"
              type="number"
              min="0"
              step="1"
              className={`form-control ${productFormErrors.quantity ? 'is-invalid' : ''}`}
              value={productForm.quantity ?? 0}
              onChange={(event) => setProductForm({ ...productForm, quantity: Number(event.target.value) })}
            />
            <FieldError message={productFormErrors.quantity} />
          </div>
          <div className="col-sm-6">
            <label className="form-label" htmlFor="funnel-product-price">
              Price (EUR)
            </label>
            <input
              id="funnel-product-price"
              type="number"
              min="0"
              step="0.01"
              className={`form-control ${productFormErrors.price ? 'is-invalid' : ''}`}
              value={productForm.price ?? 0}
              onChange={(event) => setProductForm({ ...productForm, price: Number(event.target.value) })}
            />
            <FieldError message={productFormErrors.price} />
          </div>
        </div>

        {modalProductId && <VariantManager productId={modalProductId} variants={modalVariants} onChange={setModalVariants} />}

        {modalProductId && <ProductMediaManager productId={modalProductId} media={modalMedia} onChange={setModalMedia} />}
      </FormModal>
    </>
  );
}

export default function FunnelPage() {
  const [reloadKey, setReloadKey] = useState(0);
  const { data, isLoading, error } = useAsync(fetchAdminFunnel, [reloadKey], 'Could not load funnel config.');
  const [activeTab, setActiveTab] = useState<TabKey>('packages');
  const [isToggling, setIsToggling] = useState(false);
  const [toggleError, setToggleError] = useState<string | null>(null);

  async function handleToggle(): Promise<void> {
    if (!data) {
      return;
    }

    setIsToggling(true);
    setToggleError(null);

    try {
      await toggleFunnel(!data.is_enabled);
      setReloadKey((key) => key + 1);
    } catch (err) {
      setToggleError(getErrorMessage(err, 'Could not change funnel mode.'));
    } finally {
      setIsToggling(false);
    }
  }

  return (
    <div>
      <h1 className="h3 mb-1">Funnel mode</h1>
      <p className="text-muted mb-4">
        When enabled, the homepage ("/") is replaced by a single-product landing page, the navbar's search bar and
        Favorites are hidden, and /search redirects back to "/". The rest of the site (categories, other products,
        cart, checkout) keeps working normally.
      </p>

      {isLoading && <LoadingState message="Loading funnel config..." />}
      {!isLoading && error && <ErrorState message={error} />}

      {!isLoading && !error && data && (
        <>
          {toggleError && <div className="alert alert-danger">{toggleError}</div>}

          <div className="form-check form-switch mb-3">
            <input
              id="funnel-enabled"
              type="checkbox"
              className="form-check-input"
              checked={data.is_enabled}
              disabled={isToggling}
              onChange={() => void handleToggle()}
            />
            <label className="form-check-label" htmlFor="funnel-enabled">
              Funnel mode is {data.is_enabled ? 'ON' : 'OFF'}
            </label>
          </div>

          {data.is_enabled && (
            <div className="alert alert-info">
              Funnel mode is active — the normal homepage (Content tab) isn't currently visible to visitors.
            </div>
          )}

          {data.product_id === null && (
            <div className="alert alert-warning">Pick a product below before turning funnel mode on.</div>
          )}

          <ul className="nav nav-tabs mb-4">
            <li className="nav-item">
              <button
                type="button"
                className={`nav-link ${activeTab === 'packages' ? 'active' : ''}`}
                onClick={() => setActiveTab('packages')}
              >
                Product &amp; packages
              </button>
            </li>
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

          {activeTab === 'packages' && <PackagesForm key="packages" initial={data} onSaved={() => setReloadKey((key) => key + 1)} />}
          {activeTab === 'hero' && <HeroForm key="hero" initial={data.content.hero} onSaved={() => setReloadKey((key) => key + 1)} />}
          {activeTab === 'intro' && <IntroForm key="intro" initial={data.content.intro} onSaved={() => setReloadKey((key) => key + 1)} />}
          {activeTab === 'why' && <WhyForm key="why" initial={data.content.why} onSaved={() => setReloadKey((key) => key + 1)} />}
          {activeTab === 'history' && (
            <HistoryForm key="history" initial={data.content.history} onSaved={() => setReloadKey((key) => key + 1)} />
          )}
          {activeTab === 'natural_eco' && (
            <NaturalEcoForm key="natural_eco" initial={data.content.natural_eco} onSaved={() => setReloadKey((key) => key + 1)} />
          )}
          {activeTab === 'features' && (
            <FeaturesForm key="features" initial={data.content.features} onSaved={() => setReloadKey((key) => key + 1)} />
          )}
          {activeTab === 'comparison' && (
            <ComparisonForm key="comparison" initial={data.content.comparison} onSaved={() => setReloadKey((key) => key + 1)} />
          )}
          {activeTab === 'science' && (
            <ScienceForm key="science" initial={data.content.science} onSaved={() => setReloadKey((key) => key + 1)} />
          )}
          {activeTab === 'awareness' && (
            <AwarenessForm key="awareness" initial={data.content.awareness} onSaved={() => setReloadKey((key) => key + 1)} />
          )}
          {activeTab === 'positioning' && (
            <PositioningForm key="positioning" initial={data.content.positioning} onSaved={() => setReloadKey((key) => key + 1)} />
          )}
          {activeTab === 'final_cta' && (
            <FinalCtaForm key="final_cta" initial={data.content.final_cta} onSaved={() => setReloadKey((key) => key + 1)} />
          )}
          {activeTab === 'faq' && <FaqForm key="faq" initial={data.content.faq} onSaved={() => setReloadKey((key) => key + 1)} />}
        </>
      )}
    </div>
  );
}
