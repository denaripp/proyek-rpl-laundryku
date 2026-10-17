import React from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from './Button';
import { cn } from '@/lib/format';

export interface PaginationProps {
  currentPage: number;
  totalPages: number;
  onPageChange: (page: number) => void;
  className?: string;
}

export const Pagination = ({
  currentPage,
  totalPages,
  onPageChange,
  className,
}: PaginationProps) => {
  if (totalPages <= 1) return null;

  const getPageNumbers = () => {
    const pages: (number | string)[] = [];
    const maxVisible = 5;

    if (totalPages <= maxVisible) {
      for (let i = 1; i <= totalPages; i++) pages.push(i);
    } else {
      pages.push(1);
      if (currentPage > 3) pages.push('...');
      
      const start = Math.max(2, currentPage - 1);
      const end = Math.min(totalPages - 1, currentPage + 1);

      for (let i = start; i <= end; i++) {
        if (!pages.includes(i)) pages.push(i);
      }

      if (currentPage < totalPages - 2) pages.push('...');
      pages.push(totalPages);
    }
    return pages;
  };

  return (
    <nav
      className={cn('flex items-center justify-between sm:justify-center gap-2 py-4', className)}
      aria-label="Navigasi halaman"
    >
      <Button
        variant="secondary"
        size="sm"
        disabled={currentPage <= 1}
        onClick={() => onPageChange(currentPage - 1)}
        className="px-3"
      >
        <ChevronLeft className="w-4 h-4 mr-1" />
        <span className="hidden sm:inline">Sebelumnya</span>
      </Button>

      <div className="flex items-center gap-1">
        {getPageNumbers().map((page, index) => {
          if (page === '...') {
            return (
              <span key={`dots-${index}`} className="px-2 text-slate-400 text-sm select-none">
                ...
              </span>
            );
          }
          const isCurrent = page === currentPage;
          return (
            <button
              key={`page-${page}`}
              onClick={() => onPageChange(page as number)}
              aria-current={isCurrent ? 'page' : undefined}
              className={cn(
                'w-9 h-9 rounded-xl text-sm font-semibold transition-all flex items-center justify-center min-h-[44px] min-w-[44px]',
                isCurrent
                  ? 'bg-navy-900 text-white shadow-sm'
                  : 'text-slate-600 hover:bg-slate-100'
              )}
            >
              {page}
            </button>
          );
        })}
      </div>

      <Button
        variant="secondary"
        size="sm"
        disabled={currentPage >= totalPages}
        onClick={() => onPageChange(currentPage + 1)}
        className="px-3"
      >
        <span className="hidden sm:inline">Selanjutnya</span>
        <ChevronRight className="w-4 h-4 ml-1" />
      </Button>
    </nav>
  );
};
