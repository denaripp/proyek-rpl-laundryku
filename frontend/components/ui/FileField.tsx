import React, { useRef, useState, useEffect } from 'react';
import { X, FileText, Check } from 'lucide-react';
import { cn } from '@/lib/format';

export interface FileFieldProps {
  id?: string;
  label?: string;
  required?: boolean;
  optional?: boolean;
  helperText?: string;
  error?: string;
  accept?: string;
  maxSizeMb?: number;
  value?: File | null;
  onChange: (file: File | null) => void;
  className?: string;
  disabled?: boolean;
}

export const FileField: React.FC<FileFieldProps> = ({
  id,
  label,
  required = false,
  optional = false,
  helperText = 'PDF, JPG, atau PNG, maks. 2 MB',
  error: externalError,
  accept = '.pdf,.jpg,.jpeg,.png',
  maxSizeMb = 2,
  value,
  onChange,
  className = '',
  disabled = false,
}) => {
  const inputRef = useRef<HTMLInputElement | null>(null);
  const [internalError, setInternalError] = useState<string | null>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);

  const fieldId = id || (label ? label.toLowerCase().replace(/\s+/g, '-') : 'file-input');
  const error = externalError || internalError;

  // Create image thumbnail preview if image
  useEffect(() => {
    if (value && value.type.startsWith('image/')) {
      const url = URL.createObjectURL(value);
      setPreviewUrl(url);
      return () => URL.revokeObjectURL(url);
    } else {
      setPreviewUrl(null);
    }
  }, [value]);

  const validateAndSelectFile = (file: File | null) => {
    setInternalError(null);
    if (!file) return;

    // Check size
    if (file.size > maxSizeMb * 1024 * 1024) {
      setInternalError(`Ukuran file melebihi batas maksimal ${maxSizeMb} MB.`);
      return;
    }

    // Check extension
    const ext = '.' + file.name.split('.').pop()?.toLowerCase();
    const acceptedList = accept.split(',').map((s) => s.trim().toLowerCase());
    if (acceptedList.length > 0 && !acceptedList.includes(ext) && !acceptedList.some((a) => file.type.includes(a.replace('.', '')))) {
      setInternalError(`Format file tidak didukung. Gunakan ${accept}.`);
      return;
    }

    onChange(file);
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0] || null;
    validateAndSelectFile(file);
    if (inputRef.current) inputRef.current.value = '';
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    if (disabled) return;
    const file = e.dataTransfer.files?.[0] || null;
    validateAndSelectFile(file);
  };

  const handleRemove = (e: React.MouseEvent) => {
    e.stopPropagation();
    setInternalError(null);
    onChange(null);
    if (inputRef.current) inputRef.current.value = '';
  };

  const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  };

  return (
    <div className={cn('w-full space-y-1.5 text-left', className)}>
      {label && (
        <label htmlFor={fieldId} className="block text-xs sm:text-sm font-medium text-slate-900 tracking-tight">
          {label}{' '}
          {required && <span className="text-slate-500 font-normal text-xs">(wajib)</span>}
          {optional && <span className="text-slate-400 font-normal text-xs">(opsional)</span>}
        </label>
      )}

      <input
        ref={inputRef}
        id={fieldId}
        type="file"
        accept={accept}
        disabled={disabled}
        onChange={handleFileChange}
        className="hidden"
      />

      {value ? (
        <div className="flex items-center justify-between p-3 bg-white border border-slate-300 rounded-lg text-xs">
          <div className="flex items-center gap-3 min-w-0 pr-2">
            {previewUrl ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img
                src={previewUrl}
                alt="Thumbnail dokumen"
                className="w-8 h-8 rounded object-cover border border-slate-200 shrink-0"
              />
            ) : (
              <div className="w-8 h-8 rounded bg-slate-100 border border-slate-200 flex items-center justify-center shrink-0 text-slate-500">
                <FileText className="w-4 h-4" strokeWidth={1.5} />
              </div>
            )}

            <div className="min-w-0 space-y-0.5">
              <p className="font-medium text-slate-900 truncate">{value.name}</p>
              <div className="flex items-center gap-2 text-[11px] text-slate-500 font-mono">
                <span>{formatFileSize(value.size)}</span>
                <span>•</span>
                <span className="text-emerald-700 font-medium">Terunggah</span>
              </div>
            </div>
          </div>

          <button
            type="button"
            onClick={handleRemove}
            disabled={disabled}
            className="text-slate-400 hover:text-rose-600 p-1.5 rounded transition-colors cursor-pointer"
            aria-label="Hapus file"
            title="Hapus file"
          >
            <X className="w-4 h-4" strokeWidth={1.5} />
          </button>
        </div>
      ) : (
        <div
          onClick={() => !disabled && inputRef.current?.click()}
          onDragOver={handleDragOver}
          onDrop={handleDrop}
          className={cn(
            'border border-dashed rounded-lg min-h-[88px] flex flex-col items-center justify-center p-4 text-center cursor-pointer transition-colors select-none',
            error
              ? 'border-rose-400 bg-rose-50/20 hover:bg-rose-50/40'
              : 'border-slate-300 bg-slate-50/50 hover:bg-slate-50 hover:border-slate-400',
            disabled && 'opacity-60 cursor-not-allowed'
          )}
        >
          <div className="space-y-1">
            <p className="text-xs font-medium text-slate-700">
              Pilih file atau seret ke sini
            </p>
            <p className="text-[11px] text-slate-500">{helperText}</p>
          </div>
        </div>
      )}

      {error && <p className="text-xs text-rose-600 font-medium">{error}</p>}
    </div>
  );
};
