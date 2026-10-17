'use client';

import React, { useState, useEffect, useRef, useId } from 'react';
import { Search, X, Loader2, Store as StoreIcon, Check } from 'lucide-react';
import { lookupStores, StoreLookupItem } from '@/lib/store';
import { cn } from '@/lib/format';

export interface StoreComboboxProps {
  id?: string;
  label?: string;
  required?: boolean;
  optional?: boolean;
  value?: string; // selected store ID
  selectedStore?: StoreLookupItem | null;
  onChange: (storeId: string, store: StoreLookupItem | null) => void;
  error?: string;
  helperText?: string;
  placeholder?: string;
  className?: string;
  disabled?: boolean;
}

export const StoreCombobox: React.FC<StoreComboboxProps> = ({
  id,
  label = 'Outlet Laundry Mitra',
  required = false,
  optional = false,
  value = '',
  selectedStore: propSelectedStore = null,
  onChange,
  error,
  helperText = 'Ketik nama gerai untuk mencari outlet mitra.',
  placeholder = 'Ketik nama toko (misal: Berkah, Clean, Express)...',
  className = '',
  disabled = false,
}) => {
  const generatedId = useId();
  const inputId = id || `store-combobox-${generatedId}`;
  const listboxId = `${inputId}-listbox`;

  const [query, setQuery] = useState<string>('');
  const [isOpen, setIsOpen] = useState<boolean>(false);
  const [results, setResults] = useState<StoreLookupItem[]>([]);
  const [loading, setLoading] = useState<boolean>(false);
  const [fetchError, setFetchError] = useState<string | null>(null);
  const [highlightedIndex, setHighlightedIndex] = useState<number>(-1);

  const containerRef = useRef<HTMLDivElement | null>(null);
  const inputRef = useRef<HTMLInputElement | null>(null);
  const abortControllerRef = useRef<AbortController | null>(null);
  const listboxRef = useRef<HTMLUListElement | null>(null);

  // Sync display text when value or propSelectedStore changes
  useEffect(() => {
    if (propSelectedStore) {
      setQuery(propSelectedStore.name);
    } else if (!value) {
      setQuery('');
    }
  }, [value, propSelectedStore]);

  // Click outside listener to close dropdown
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setIsOpen(false);
        // Reset query text to current selection if no selection confirmed
        if (propSelectedStore) {
          setQuery(propSelectedStore.name);
        } else if (!value) {
          setQuery('');
        }
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [value, propSelectedStore]);

  // Debounced search effect
  useEffect(() => {
    const trimmed = query.trim();

    // If query matches currently selected store name, no need to re-query
    if (propSelectedStore && trimmed === propSelectedStore.name) {
      return;
    }

    if (trimmed.length < 2) {
      setResults([]);
      setLoading(false);
      setFetchError(null);
      return;
    }

    setLoading(true);
    setFetchError(null);

    const timer = setTimeout(async () => {
      // Abort any ongoing request
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }
      abortControllerRef.current = new AbortController();

      try {
        const data = await lookupStores(trimmed, abortControllerRef.current.signal);
        setResults(data || []);
        setHighlightedIndex(-1);
      } catch (err: any) {
        if (err?.name !== 'AbortError') {
          setFetchError('Gagal memuat toko.');
          setResults([]);
        }
      } finally {
        setLoading(false);
      }
    }, 280);

    return () => {
      clearTimeout(timer);
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }
    };
  }, [query, propSelectedStore]);

  const handleSelect = (store: StoreLookupItem) => {
    onChange(store.id, store);
    setQuery(store.name);
    setIsOpen(false);
    setHighlightedIndex(-1);
  };

  const handleClear = (e: React.MouseEvent) => {
    e.stopPropagation();
    onChange('', null);
    setQuery('');
    setResults([]);
    setIsOpen(false);
    inputRef.current?.focus();
  };

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (disabled) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (!isOpen) {
        setIsOpen(true);
      } else if (results.length > 0) {
        setHighlightedIndex((prev) => (prev < results.length - 1 ? prev + 1 : 0));
      }
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      if (!isOpen) {
        setIsOpen(true);
      } else if (results.length > 0) {
        setHighlightedIndex((prev) => (prev > 0 ? prev - 1 : results.length - 1));
      }
    } else if (e.key === 'Enter') {
      if (isOpen && highlightedIndex >= 0 && results[highlightedIndex]) {
        e.preventDefault();
        handleSelect(results[highlightedIndex]);
      }
    } else if (e.key === 'Escape') {
      setIsOpen(false);
      if (propSelectedStore) {
        setQuery(propSelectedStore.name);
      } else if (!value) {
        setQuery('');
      }
    } else if (e.key === 'Tab') {
      setIsOpen(false);
      if (propSelectedStore) {
        setQuery(propSelectedStore.name);
      } else if (!value) {
        setQuery('');
      }
    }
  };

  // Scroll active option into view
  useEffect(() => {
    if (highlightedIndex >= 0 && listboxRef.current) {
      const activeEl = listboxRef.current.children[highlightedIndex] as HTMLElement;
      if (activeEl) {
        activeEl.scrollIntoView({ block: 'nearest' });
      }
    }
  }, [highlightedIndex]);

  const activeOptionId =
    highlightedIndex >= 0 && results[highlightedIndex]
      ? `${inputId}-option-${results[highlightedIndex].id}`
      : undefined;

  return (
    <div ref={containerRef} className={cn('w-full space-y-1 text-left relative', className)}>
      {label && (
        <label
          htmlFor={inputId}
          className="block text-xs sm:text-sm font-medium text-slate-900 tracking-tight"
        >
          {label}{' '}
          {required && <span className="text-slate-500 font-normal text-xs">(wajib)</span>}
          {optional && <span className="text-slate-400 font-normal text-xs">(opsional)</span>}
        </label>
      )}

      {/* Input container */}
      <div className="relative flex items-center">
        <div className="absolute left-3 flex items-center pointer-events-none text-slate-400">
          <Search className="w-4 h-4" strokeWidth={1.5} />
        </div>

        <input
          ref={inputRef}
          id={inputId}
          type="text"
          role="combobox"
          aria-expanded={isOpen}
          aria-haspopup="listbox"
          aria-controls={listboxId}
          aria-activedescendant={activeOptionId}
          aria-autocomplete="list"
          aria-invalid={!!error}
          disabled={disabled}
          placeholder={placeholder}
          value={query}
          onFocus={() => {
            if (!disabled) setIsOpen(true);
          }}
          onChange={(e) => {
            setQuery(e.target.value);
            if (!isOpen) setIsOpen(true);
            // If user types and changes text, deselect until an option is explicitly chosen
            if (value && propSelectedStore && e.target.value !== propSelectedStore.name) {
              onChange('', null);
            }
          }}
          onKeyDown={handleKeyDown}
          className={cn(
            'w-full h-10 min-h-[40px] rounded-lg bg-white border border-slate-300 text-slate-900 text-xs sm:text-sm placeholder:text-slate-400 pl-9 pr-9 transition-colors',
            'focus:outline-hidden focus:border-[#1A3A5C] focus:ring-2 focus:ring-[#1A3A5C]/15',
            'disabled:bg-slate-100 disabled:text-slate-400 disabled:cursor-not-allowed',
            error
              ? 'border-rose-400 focus:border-rose-500 text-rose-900'
              : 'hover:border-slate-400'
          )}
        />

        {/* Action button inside input: Loading spinner or Clear X button */}
        <div className="absolute right-2.5 flex items-center">
          {loading ? (
            <Loader2 className="w-4 h-4 text-slate-400 animate-spin" />
          ) : query.length > 0 ? (
            <button
              type="button"
              onClick={handleClear}
              disabled={disabled}
              className="p-1 text-slate-400 hover:text-slate-700 rounded transition-colors cursor-pointer"
              aria-label="Hapus pilihan"
              title="Hapus pilihan"
            >
              <X className="w-3.5 h-3.5" strokeWidth={1.5} />
            </button>
          ) : null}
        </div>
      </div>

      {/* Helper text or error */}
      {error ? (
        <p id={`${inputId}-error`} className="text-xs text-rose-600 font-medium">
          {error}
        </p>
      ) : helperText ? (
        <p id={`${inputId}-helper`} className="text-xs text-slate-500">
          {helperText}
        </p>
      ) : null}

      {/* Dropdown Popover Listbox */}
      {isOpen && (
        <div className="absolute left-0 right-0 top-full mt-1 z-50 bg-white border border-slate-200 rounded-lg shadow-md max-h-60 overflow-y-auto overflow-x-hidden animate-fadeIn">
          {loading ? (
            <div className="p-3 text-center text-xs text-slate-500 flex items-center justify-center gap-2">
              <Loader2 className="w-3.5 h-3.5 animate-spin text-slate-400" />
              <span>Mencari toko...</span>
            </div>
          ) : fetchError ? (
            <div className="p-3 text-center text-xs text-rose-600 space-y-1">
              <p>{fetchError}</p>
              <button
                type="button"
                onClick={() => {
                  const trimmed = query.trim();
                  if (trimmed.length >= 2) {
                    setLoading(true);
                    lookupStores(trimmed)
                      .then((d) => setResults(d || []))
                      .catch(() => setFetchError('Gagal memuat toko.'))
                      .finally(() => setLoading(false));
                  }
                }}
                className="text-[#1A3A5C] underline font-medium hover:text-[#132d47]"
              >
                Coba lagi
              </button>
            </div>
          ) : query.trim().length < 2 ? (
            <div className="p-3 text-center text-xs text-slate-400">
              Ketik minimal 2 karakter untuk mencari toko...
            </div>
          ) : results.length === 0 ? (
            <div className="p-3 text-center text-xs text-slate-500">
              Tidak ada toko dengan nama tersebut.
            </div>
          ) : (
            <ul
              ref={listboxRef}
              id={listboxId}
              role="listbox"
              className="divide-y divide-slate-100 py-1"
            >
              {results.map((store, idx) => {
                const isSelected = store.id === value;
                const isHighlighted = idx === highlightedIndex;

                return (
                  <li
                    key={store.id}
                    id={`${inputId}-option-${store.id}`}
                    role="option"
                    aria-selected={isSelected}
                    onClick={() => handleSelect(store)}
                    onMouseEnter={() => setHighlightedIndex(idx)}
                    className={cn(
                      'px-3.5 py-2.5 cursor-pointer text-xs transition-colors flex items-start justify-between gap-2',
                      isHighlighted
                        ? 'bg-slate-100 text-slate-900'
                        : 'text-slate-800 hover:bg-slate-50',
                      isSelected && 'bg-slate-50 font-medium'
                    )}
                  >
                    <div className="space-y-0.5 min-w-0 flex-1">
                      <p className="font-semibold text-slate-900 truncate">{store.name}</p>
                      <p className="text-[11px] text-slate-500 truncate leading-relaxed">
                        {store.address_text}
                      </p>
                    </div>

                    {isSelected && (
                      <Check className="w-4 h-4 text-[#1A3A5C] shrink-0 mt-0.5" strokeWidth={2} />
                    )}
                  </li>
                );
              })}
            </ul>
          )}
        </div>
      )}
    </div>
  );
};

export default StoreCombobox;
