'use client';

import React from 'react';
import { PublicLayout } from '@/components/layout/PublicLayout';
import { NotificationCenter } from '@/components/notifications/NotificationCenter';

export default function NotificationsPage() {
  return (
    <PublicLayout>
      <div className="py-8 px-4 sm:px-6 lg:px-8">
        <NotificationCenter />
      </div>
    </PublicLayout>
  );
}
