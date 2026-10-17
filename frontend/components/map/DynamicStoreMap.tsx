'use client';

import dynamic from 'next/dynamic';
import React from 'react';
import { Skeleton } from '@/components/ui';
import type { StoreMapProps } from './StoreMap';

const StoreMap = dynamic(() => import('./StoreMap'), {
  ssr: false,
  loading: () => (
    <div className="w-full h-full min-h-[350px] bg-sky-50 rounded-2xl border border-slate-200 flex flex-col items-center justify-center p-6 text-center">
      <Skeleton className="w-full h-full min-h-[300px] rounded-xl" />
      <p className="mt-3 text-sm text-slate-500 font-medium">Memuat peta OpenStreetMap...</p>
    </div>
  ),
});

export const DynamicStoreMap: React.FC<StoreMapProps> = (props) => {
  return <StoreMap {...props} />;
};

export default DynamicStoreMap;
