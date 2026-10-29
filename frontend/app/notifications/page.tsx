'use client';

import React from 'react';
import { PublicLayout } from '@/components/layout/PublicLayout';
import { NotificationCenter } from '@/components/notifications/NotificationCenter';

export default function NotificationsPage() {
  return (
    <PublicLayout>
      <NotificationCenter />
    </PublicLayout>
  );
}
