'use client';

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';

export default function CourierOrdersRedirectPage() {
  const router = useRouter();

  useEffect(() => {
    router.replace('/courier/tasks');
  }, [router]);

  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-50 text-xs text-slate-500">
      Mengalihkan ke tugas pengantaran...
    </div>
  );
}
