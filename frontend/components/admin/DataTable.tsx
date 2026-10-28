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
      <div className="bg-white border border-slate-200/80 rounded-2xl overflow-hidden shadow-2xs">
        <div className="p-10 flex flex-col items-center justify-center gap-2">
          <div className="w-5 h-5 border-2 border-navy-900 border-t-transparent rounded-full animate-spin" />
          <span className="text-xs text-slate-500 font-medium">Memuat data...</span>
        </div>
      </div>
    );
  }

  return (
    <div className="bg-white border border-slate-200/80 rounded-2xl overflow-hidden shadow-2xs">
      <div className="overflow-x-auto">
        <table className="w-full text-left text-xs text-slate-700 border-collapse">
          <thead className="bg-slate-50/70 border-b border-slate-100 text-[11px] font-semibold text-slate-500 select-none">
            <tr>
              {columns.map((col, idx) => (
                <th key={idx} className={`px-4 py-3 ${col.className || ''}`}>
                  {col.header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {data.length === 0 ? (
              <tr>
                <td colSpan={columns.length} className="px-4 py-10 text-center text-xs text-slate-500 font-medium">
                  {emptyText}
                </td>
              </tr>
            ) : (
              data.map((item) => (
                <tr key={keyExtractor(item)} className="hover:bg-slate-50/70 transition-colors">
                  {columns.map((col, cIdx) => (
                    <td key={cIdx} className={`px-4 py-3 whitespace-nowrap ${col.className || ''}`}>
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
        <div className="p-3.5 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
          <div>
            Total <span className="font-semibold font-mono text-slate-900 tabular-nums">{pagination.total}</span> data
          </div>
          <div className="flex items-center gap-1.5">
            <button
              onClick={() => pagination.onPageChange(pagination.currentPage - 1)}
              disabled={pagination.currentPage <= 1}
              aria-label="Halaman sebelumnya"
              className="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors shadow-2xs min-h-[32px] min-w-[32px] flex items-center justify-center"
            >
              <ChevronLeft className="w-4 h-4" strokeWidth={1.75} />
            </button>
            <span className="px-2 font-mono tabular-nums text-xs font-medium text-slate-700">
              {pagination.currentPage} / {pagination.lastPage}
            </span>
            <button
              onClick={() => pagination.onPageChange(pagination.currentPage + 1)}
              disabled={pagination.currentPage >= pagination.lastPage}
              aria-label="Halaman berikutnya"
              className="p-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors shadow-2xs min-h-[32px] min-w-[32px] flex items-center justify-center"
            >
              <ChevronRight className="w-4 h-4" strokeWidth={1.75} />
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
