'use client';

import { useEffect, useRef, useCallback } from 'react';

export interface UsePollingOptions {
  enabled?: boolean;
  immediate?: boolean;
  refetchOnFocus?: boolean;
}

/**
 * Universal polling hook with automatic tab visibility pause & instant refocus refetch.
 *
 * @param callback The function to execute on each tick.
 * @param intervalMs The polling interval in milliseconds.
 * @param options Polling configuration options (enabled, immediate, refetchOnFocus).
 */
export function usePolling(
  callback: () => void | Promise<unknown>,
  intervalMs: number,
  options: UsePollingOptions = {}
) {
  const { enabled = true, immediate = false, refetchOnFocus = true } = options;
  const savedCallback = useRef(callback);
  const timerRef = useRef<NodeJS.Timeout | null>(null);

  useEffect(() => {
    savedCallback.current = callback;
  }, [callback]);

  const tick = useCallback(async () => {
    if (typeof document !== 'undefined' && document.visibilityState === 'hidden') {
      return;
    }
    try {
      await savedCallback.current();
    } catch {
      // Ignore background polling errors
    }
  }, []);

  useEffect(() => {
    if (!enabled || intervalMs <= 0) {
      if (timerRef.current) {
        clearInterval(timerRef.current);
        timerRef.current = null;
      }
      return;
    }

    if (immediate) {
      tick();
    }

    timerRef.current = setInterval(tick, intervalMs);

    const handleVisibilityChange = () => {
      if (typeof document !== 'undefined' && document.visibilityState === 'visible') {
        if (refetchOnFocus) {
          tick();
        }
        if (timerRef.current) clearInterval(timerRef.current);
        timerRef.current = setInterval(tick, intervalMs);
      }
    };

    if (typeof document !== 'undefined') {
      document.addEventListener('visibilitychange', handleVisibilityChange);
    }

    return () => {
      if (timerRef.current) {
        clearInterval(timerRef.current);
        timerRef.current = null;
      }
      if (typeof document !== 'undefined') {
        document.removeEventListener('visibilitychange', handleVisibilityChange);
      }
    };
  }, [enabled, intervalMs, immediate, refetchOnFocus, tick]);
}
