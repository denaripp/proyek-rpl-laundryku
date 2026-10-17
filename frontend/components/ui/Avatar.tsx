import React from 'react';
import { User } from 'lucide-react';
import { cn } from '@/lib/format';

export interface AvatarProps extends React.HTMLAttributes<HTMLDivElement> {
  src?: string | null;
  alt?: string;
  name?: string;
  size?: 'sm' | 'md' | 'lg' | 'xl';
  status?: 'online' | 'offline' | 'busy' | 'away';
}

export const Avatar = ({
  src,
  alt = 'Avatar',
  name,
  size = 'md',
  status,
  className,
  ...props
}: AvatarProps) => {
  const [hasError, setHasError] = React.useState(false);

  // Reset error state if src changes (e.g. after new upload or deletion)
  React.useEffect(() => {
    setHasError(false);
  }, [src]);

  const sizes = {
    sm: 'w-8 h-8 text-xs',
    md: 'w-10 h-10 text-sm font-semibold',
    lg: 'w-12 h-12 text-base font-semibold',
    xl: 'w-20 h-20 text-xl font-bold',
  };

  const statusSizes = {
    sm: 'w-2 h-2',
    md: 'w-2.5 h-2.5',
    lg: 'w-3 h-3',
    xl: 'w-4 h-4',
  };

  const statusColors = {
    online: 'bg-emerald-500',
    offline: 'bg-slate-400',
    busy: 'bg-rose-500',
    away: 'bg-amber-500',
  };

  const getInitials = (text?: string) => {
    if (!text) return '';
    const parts = text.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  };

  return (
    <div className={cn('relative inline-flex shrink-0', className)} {...props}>
      <div
        className={cn(
          'rounded-full overflow-hidden flex items-center justify-center font-sans bg-slate-100 text-[#1A3A5C] border border-slate-200 shadow-2xs select-none',
          sizes[size]
        )}
      >
        {src && !hasError ? (
          <img
            src={src}
            alt={alt}
            onError={() => setHasError(true)}
            className="w-full h-full object-cover"
          />
        ) : name ? (
          <span className="tracking-tight">{getInitials(name)}</span>
        ) : (
          <User className="w-1/2 h-1/2 text-slate-400" strokeWidth={1.5} />
        )}
      </div>

      {status && (
        <span
          className={cn(
            'absolute bottom-0 right-0 rounded-full ring-2 ring-white',
            statusSizes[size],
            statusColors[status]
          )}
        />
      )}
    </div>
  );
};
