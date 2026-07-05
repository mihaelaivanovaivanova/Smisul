import { useState } from 'react';

export interface ChecklistItem {
  id: number;
  label: string;
}

interface MultiSelectChecklistProps {
  items: ChecklistItem[];
  selectedIds: number[];
  onChange: (ids: number[]) => void;
  searchPlaceholder?: string;
  emptyMessage?: string;
}

/** A searchable, scrollable checkbox list — used wherever an admin form ties a resource to a set of others (e.g. a promotion's eligible products/categories). */
export default function MultiSelectChecklist({
  items,
  selectedIds,
  onChange,
  searchPlaceholder = 'Search...',
  emptyMessage = 'Nothing to select yet.',
}: MultiSelectChecklistProps) {
  const [search, setSearch] = useState('');

  const filtered = search
    ? items.filter((item) => item.label.toLowerCase().includes(search.toLowerCase()))
    : items;

  function toggle(id: number, checked: boolean) {
    onChange(checked ? [...selectedIds, id] : selectedIds.filter((selectedId) => selectedId !== id));
  }

  return (
    <div>
      {items.length > 8 && (
        <input
          type="search"
          className="form-control form-control-sm mb-2"
          placeholder={searchPlaceholder}
          value={search}
          onChange={(event) => setSearch(event.target.value)}
        />
      )}
      <div className="border rounded p-2" style={{ maxHeight: 180, overflowY: 'auto' }}>
        {filtered.length === 0 && <div className="text-muted small">{emptyMessage}</div>}
        {filtered.map((item) => (
          <div className="form-check" key={item.id}>
            <input
              type="checkbox"
              className="form-check-input"
              id={`checklist-${item.id}`}
              checked={selectedIds.includes(item.id)}
              onChange={(event) => toggle(item.id, event.target.checked)}
            />
            <label className="form-check-label" htmlFor={`checklist-${item.id}`}>
              {item.label}
            </label>
          </div>
        ))}
      </div>
      {selectedIds.length > 0 && <div className="form-text">{selectedIds.length} selected</div>}
    </div>
  );
}
