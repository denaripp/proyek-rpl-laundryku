import React from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

export interface Column<T> {
  header: string;
  accessor?: keyof T | ((item: T) => React.ReactNode);
  className?: string;
}

interface DataTableProps<T> {
  columns: Column<T>[];
  data: T[];
  keyExtractor: (item: T) => string;
  loading?: boolean;
  emptyText?: string;
  pagination?: {
    currentPage: number;
    lastPage: number;
    total: number;
    onPageChange: (page: number) => void;
  };
}

export function DataTable<T>({
  columns,
  data,
  keyExtractor,
  loading = false,
  emptyText = 'Belum ada data.',
  pagination,
}: DataTableProps<T>) {
  if (loading) {
    return (
      <div className="bg-white border border-slate-200 rounded-lg overflow-hidden">
        <div className="p-8 flex flex-col items-center justify-center gap-2">
          <div className="w-5 h-5 border-2 border-slate-400 border-t-transparent rounded-full animate-spin" />
          <span className="text-xs text-slate-500">Memuat data...</span>
        </div>
      </div>
    );
  }

  return (
    <div className="bg-white border border-slate-200 rounded-lg overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full text-left text-xs text-slate-700">
          <thead className="bg-slate-50/80 border-b border-slate-200 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
            <tr>
              {columns.map((col, idx) => (
                <th key={idx} className={`px-4 py-2.5 ${col.className || ''}`}>
                  {col.header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {data.length === 0 ? (
              <tr>
                <td colSpan={columns.length} className="px-4 py-8 text-center text-xs text-slate-500">
                  {emptyText}
                </td>
              </tr>
            ) : (
              data.map((item) => (
                <tr key={keyExtractor(item)} className="hover:bg-slate-50/60 transition-colors">
                  {columns.map((col, cIdx) => (
                    <td key={cIdx} className={`px-4 py-2.5 whitespace-nowrap ${col.className || ''}`}>
                      {typeof col.accessor === 'function'
                        ? col.accessor(item)
                        : col.accessor
                        ? (item[col.accessor] as any)
                        : null}
                    </td>
                  ))}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {pagination && pagination.lastPage > 1 && (
        <div className="p-3 border-t border-slate-200 flex items-center justify-between text-xs text-slate-500">
          <div>
            Total <span className="font-semibold text-slate-900 tabular-nums">{pagination.total}</span> data
          </div>
          <div className="flex items-center gap-1.5">
            <button
              onClick={() => pagination.onPageChange(pagination.currentPage - 1)}
              disabled={pagination.currentPage <= 1}
              aria-label="Halaman sebelumnya"
              className="p-1 rounded border border-slate-200 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
            >
              <ChevronLeft className="w-4 h-4" strokeWidth={1.5} />
            </button>
            <span className="px-2 font-mono tabular-nums">
              {pagination.currentPage} / {pagination.lastPage}
            </span>
            <button
              onClick={() => pagination.onPageChange(pagination.currentPage + 1)}
              disabled={pagination.currentPage >= pagination.lastPage}
              aria-label="Halaman berikutnya"
              className="p-1 rounded border border-slate-200 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
            >
              <ChevronRight className="w-4 h-4" strokeWidth={1.5} />
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
