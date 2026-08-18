import { useEffect, useMemo, useRef, useState } from 'react';

export interface SearchableSelectOption {
  value: string;
  label: string;
  /** What typed text is matched against, if narrower than the full displayed label (e.g. a settlement's own name, excluding its "(общ. X, обл. Y)" disambiguation suffix). Defaults to `label`. */
  searchText?: string;
}

interface SearchableSelectProps {
  id: string;
  value: string;
  options: SearchableSelectOption[];
  placeholder: string;
  onChange: (value: string) => void;
  disabled?: boolean;
  invalid?: boolean;
}

/**
 * Bulgarian "streamlined" Cyrillic->Latin transliteration (the scheme used
 * on road signs and in passports) — lets a search for "Sofia" match
 * "СОФИЯ" without needing a reverse (ambiguous) Latin->Cyrillic mapping.
 */
const CYRILLIC_TO_LATIN: Record<string, string> = {
  а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ж: 'zh', з: 'z', и: 'i', й: 'y',
  к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u',
  ф: 'f', х: 'h', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'sht', ъ: 'a', ь: 'y', ю: 'yu', я: 'ya',
};

function transliterate(text: string): string {
  return text
    .split('')
    .map((char) => CYRILLIC_TO_LATIN[char] ?? char)
    .join('');
}

/**
 * Common English spellings of Bulgarian place names often drop the "y"
 * glide the strict transliteration above adds for я/ю (e.g. "София" ->
 * strict "sofiya", but everyone types/writes "Sofia") — this loose variant
 * covers that so the common spelling still matches.
 */
function looseTransliterate(text: string): string {
  return transliterate(text).replace(/y([auo])/g, '$1');
}

function matchesQuery(label: string, query: string): boolean {
  const normalizedQuery = query.trim().toLowerCase();
  if (!normalizedQuery) return true;

  const normalizedLabel = label.toLowerCase();
  return (
    normalizedLabel.includes(normalizedQuery) ||
    transliterate(normalizedLabel).includes(normalizedQuery) ||
    looseTransliterate(normalizedLabel).includes(normalizedQuery)
  );
}

/**
 * A text-filterable dropdown over a fixed option list — native <select>
 * elements have no general search, just jump-to-first-letter, and can't
 * match a Latin query against Cyrillic option text. Used for the
 * city/office pickers, where lists can run into the hundreds and customers
 * may type either script.
 */
export default function SearchableSelect({ id, value, options, placeholder, onChange, disabled = false, invalid = false }: SearchableSelectProps) {
  const selectedOption = options.find((option) => option.value === value) ?? null;

  const [inputValue, setInputValue] = useState(selectedOption?.label ?? '');
  const [isOpen, setIsOpen] = useState(false);
  const [highlightedIndex, setHighlightedIndex] = useState(0);
  const containerRef = useRef<HTMLDivElement>(null);

  // Keeps the visible text in sync when the selection changes from outside
  // (a parent clearing it, or the option list being replaced).
  useEffect(() => {
    setInputValue(selectedOption?.label ?? '');
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [value, selectedOption?.label]);

  const filteredOptions = useMemo(
    () => (isOpen ? options.filter((option) => matchesQuery(option.searchText ?? option.label, inputValue)) : options),
    [options, inputValue, isOpen],
  );

  useEffect(() => {
    setHighlightedIndex(0);
  }, [inputValue, isOpen]);

  useEffect(() => {
    if (!isOpen) return;

    function handleOutsideClick(event: MouseEvent): void {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
        setInputValue(selectedOption?.label ?? '');
      }
    }

    document.addEventListener('mousedown', handleOutsideClick);
    return () => document.removeEventListener('mousedown', handleOutsideClick);
  }, [isOpen, selectedOption?.label]);

  function selectOption(option: SearchableSelectOption): void {
    onChange(option.value);
    setInputValue(option.label);
    setIsOpen(false);
  }

  function handleKeyDown(event: React.KeyboardEvent<HTMLInputElement>): void {
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      setIsOpen(true);
      setHighlightedIndex((index) => Math.min(index + 1, filteredOptions.length - 1));
    } else if (event.key === 'ArrowUp') {
      event.preventDefault();
      setHighlightedIndex((index) => Math.max(index - 1, 0));
    } else if (event.key === 'Enter') {
      event.preventDefault();
      const option = filteredOptions[highlightedIndex];
      if (isOpen && option) selectOption(option);
    } else if (event.key === 'Escape') {
      setIsOpen(false);
      setInputValue(selectedOption?.label ?? '');
    }
  }

  return (
    <div className="position-relative" ref={containerRef}>
      <input
        id={id}
        type="text"
        role="combobox"
        aria-expanded={isOpen}
        aria-controls={`${id}-listbox`}
        autoComplete="off"
        className={`form-control ${invalid ? 'is-invalid' : ''}`}
        placeholder={placeholder}
        value={inputValue}
        disabled={disabled}
        onFocus={() => {
          setIsOpen(true);
          setInputValue('');
        }}
        onChange={(event) => {
          setInputValue(event.target.value);
          setIsOpen(true);
        }}
        onKeyDown={handleKeyDown}
      />

      {isOpen && (
        <ul id={`${id}-listbox`} role="listbox" className="dropdown-menu show search-select-menu">
          {filteredOptions.length === 0 && <li className="dropdown-item-text text-muted small">Няма съвпадения</li>}
          {filteredOptions.map((option, index) => (
            <li key={option.value}>
              <button
                type="button"
                role="option"
                aria-selected={option.value === value}
                className={`dropdown-item ${index === highlightedIndex ? 'active' : ''}`}
                onMouseDown={(event) => event.preventDefault()}
                onMouseEnter={() => setHighlightedIndex(index)}
                onClick={() => selectOption(option)}
              >
                {option.label}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
