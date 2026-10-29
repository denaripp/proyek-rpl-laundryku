'use client';

import React, { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import {
  Bell,
  Settings2,
  Mail,
  MessageSquare,
  Smartphone,
  CheckCheck,
  RotateCcw,
  AlertCircle,
} from 'lucide-react';
import {
  notificationApi,
  AppNotification,
  NotificationPreferenceGroup,
} from '@/lib/notification';
import { Pagination } from '@/components/ui/Pagination';
import { cn } from '@/lib/format';

interface NotificationCenterProps {
  className?: string;
  onUnreadCountChange?: (count: number) => void;
}

export const NotificationCenter: React.FC<NotificationCenterProps> = ({
  className = '',
  onUnreadCountChange,
}) => {
  const router = useRouter();

  // Active Tab: 'list' | 'preferences'
  const [activeTab, setActiveTab] = useState<'list' | 'preferences'>('list');

  // Notifications State
  const [notifications, setNotifications] = useState<AppNotification[]>([]);
  const [unreadCount, setUnreadCount] = useState<number>(0);
  const [channelFilter, setChannelFilter] = useState<string>('all');
  const [unreadOnly, setUnreadOnly] = useState<boolean>(false);
  const [page, setPage] = useState<number>(1);
  const [meta, setMeta] = useState<any>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  // Preferences State
  const [preferences, setPreferences] = useState<NotificationPreferenceGroup[]>([]);
  const [prefLoading, setPrefLoading] = useState<boolean>(false);
  const [prefSaving, setPrefSaving] = useState<boolean>(false);
  const [prefNotice, setPrefNotice] = useState<string | null>(null);

  useEffect(() => {
    if (activeTab === 'list') {
      fetchNotifications();
    } else {
      fetchPreferences();
    }
  }, [activeTab, channelFilter, unreadOnly, page]);

  const fetchNotifications = async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await notificationApi.getNotifications({
        channel: channelFilter === 'all' ? undefined : channelFilter,
        unread_only: unreadOnly,
        page,
      });
      setNotifications(res.data.notifications || []);
      setUnreadCount(res.data.unread_count || 0);
      setMeta(res.meta);
      if (onUnreadCountChange) {
        onUnreadCountChange(res.data.unread_count || 0);
      }
    } catch (err: any) {
      setError(err?.message || 'Gagal memuat notifikasi.');
    } finally {
      setLoading(false);
    }
  };

  const fetchPreferences = async () => {
    setPrefLoading(true);
    setError(null);
    try {
      const data = await notificationApi.getPreferences();
      setPreferences(data || []);
    } catch (err: any) {
      setError(err?.message || 'Gagal memuat preferensi kanal notifikasi.');
    } finally {
      setPrefLoading(false);
    }
  };

  const handleMarkAsRead = async (id: string) => {
    try {
      await notificationApi.markAsRead(id);
      setNotifications((prev) =>
        prev.map((n) =>
          n.id === id ? { ...n, status: 'read', read_at: new Date().toISOString() } : n
        )
      );
      const newCount = Math.max(0, unreadCount - 1);
      setUnreadCount(newCount);
      if (onUnreadCountChange) onUnreadCountChange(newCount);
    } catch {
      // ignore
    }
  };

  const handleMarkAllRead = async () => {
    try {
      await notificationApi.markAllAsRead();
      setNotifications((prev) =>
        prev.map((n) => ({ ...n, status: 'read', read_at: new Date().toISOString() }))
      );
      setUnreadCount(0);
      if (onUnreadCountChange) onUnreadCountChange(0);
    } catch (err: any) {
      setError(err?.message || 'Gagal menandai semua notifikasi.');
    }
  };

  const handleTogglePreference = async (channel: string, eventType: string, newValue: boolean) => {
    // Optimistic update
    setPreferences((prev) =>
      prev.map((group) => {
        if (group.channel === channel) {
          return {
            ...group,
            events: group.events.map((evt) =>
              evt.event_type === eventType ? { ...evt, is_enabled: newValue } : evt
            ),
          };
        }
        return group;
      })
    );

    setPrefSaving(true);
    try {
      await notificationApi.updatePreferences([
        {
          channel,
          event_type: eventType,
          is_enabled: newValue,
        },
      ]);
      setPrefNotice('Preferensi berhasil disimpan.');
      setTimeout(() => setPrefNotice(null), 2500);
    } catch (err: any) {
      fetchPreferences();
      setError(err?.message || 'Gagal memperbarui preferensi.');
    } finally {
      setPrefSaving(false);
    }
  };

  const getChannelLabel = (channel: string) => {
    switch (channel) {
      case 'whatsapp':
        return 'WhatsApp';
      case 'email':
        return 'Email';
      case 'in_app':
      default:
        return 'Di aplikasi';
    }
  };

  return (
    <div className={cn('space-y-0', className)}>
      {/* 1. Hero Header Section (Navy-900 with Wave & Bubbles) */}
      <div className="bg-navy-900 text-white relative overflow-hidden pt-8 pb-14 px-4 sm:px-6 lg:px-8 select-none">
        {/* Soap Bubbles Decorative Shapes (aria-hidden="true") */}
        <div className="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
          {/* Bubble 1 (Top Left) */}
          <div className="absolute top-4 left-10 w-24 h-24 rounded-full border border-sky-300/30 bg-sky-400/10 backdrop-blur-[1px] shadow-inner animate-gentle-float">
            <div className="w-2.5 h-2.5 rounded-full bg-white/60 ml-3.5 mt-3.5" />
          </div>

          {/* Bubble 2 (Top Right) */}
          <div className="absolute top-6 right-16 w-32 h-32 rounded-full border border-sky-300/20 bg-sky-300/10 animate-gentle-float-slow">
            <div className="w-3.5 h-3.5 rounded-full bg-white/50 ml-4 mt-4" />
          </div>
        </div>

        {/* Main Hero Content */}
        <div className="max-w-4xl mx-auto relative z-10 space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div className="space-y-1.5 max-w-xl">
              <div className="flex items-center gap-3 flex-wrap">
                <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-white leading-tight">
                  Notifikasi
                </h1>
                {unreadCount > 0 && (
                  <span className="text-xs text-sky-200 font-bold bg-navy-800/90 px-2.5 py-1 rounded-lg border border-navy-700 tabular-nums shadow-2xs">
                    {unreadCount} belum dibaca
                  </span>
                )}
              </div>
              <p className="text-xs text-navy-200 leading-relaxed">
                Pemberitahuan pembaruan pesanan, penawaran, dan informasi penting lainnya.
              </p>
            </div>

            {/* Action Controls in Header: Mark All as Read */}
            {activeTab === 'list' && unreadCount > 0 && (
              <button
                type="button"
                onClick={handleMarkAllRead}
                className="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold text-white bg-navy-800 hover:bg-navy-700 border border-navy-700 rounded-xl transition-all shadow-2xs cursor-pointer self-start sm:self-end"
              >
                <CheckCheck className="w-4 h-4 text-sky-300" strokeWidth={1.75} />
                <span>Tandai semua dibaca</span>
              </button>
            )}
          </div>
        </div>

        {/* Wave SVG Divider at Bottom of Hero */}
        <div className="absolute -bottom-1 left-0 right-0 pointer-events-none overflow-hidden leading-none z-10" aria-hidden="true">
          <svg
            viewBox="0 0 1440 48"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            className="w-full h-8 sm:h-12 text-slate-50 preserve-3d"
          >
            <path
              d="M0 20C240 45 480 5 720 25C960 45 1200 10 1440 20V48H0V20Z"
              fill="currentColor"
            />
          </svg>
        </div>
      </div>

      {/* 2. Main Body Content (Slate-50) */}
      <div className="bg-slate-50 min-h-[calc(100vh-14rem)] pb-12 pt-2">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 -mt-6 sm:-mt-7 relative z-20 space-y-5">
          {/* Card Container for Navigation Tabs & Controls */}
          <div className="bg-white p-3 sm:p-4 rounded-3xl border border-navy-100/90 shadow-2xs space-y-3.5">
            {/* Tabs: Riwayat vs Pengaturan kanal */}
            <div className="flex items-center gap-2 border-b border-slate-100 pb-3">
              <button
                type="button"
                onClick={() => setActiveTab('list')}
                className={cn(
                  'inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer select-none',
                  activeTab === 'list'
                    ? 'bg-navy-50 text-navy-900 border border-navy-200/80 shadow-2xs'
                    : 'text-slate-600 hover:text-navy-900 hover:bg-slate-50'
                )}
              >
                <Bell className="w-4 h-4" strokeWidth={1.75} />
                <span>Riwayat</span>
                {unreadCount > 0 && (
                  <span className="w-2 h-2 rounded-full bg-rose-600 ml-0.5" />
                )}
              </button>

              <button
                type="button"
                onClick={() => setActiveTab('preferences')}
                className={cn(
                  'inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer select-none',
                  activeTab === 'preferences'
                    ? 'bg-navy-50 text-navy-900 border border-navy-200/80 shadow-2xs'
                    : 'text-slate-600 hover:text-navy-900 hover:bg-slate-50'
                )}
              >
                <Settings2 className="w-4 h-4" strokeWidth={1.75} />
                <span>Pengaturan kanal</span>
              </button>
            </div>

            {/* Controls row for TAB 1 (List) */}
            {activeTab === 'list' && (
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-0.5">
                {/* Segmented Channel Control */}
                <div className="inline-flex items-center p-1 bg-slate-100/90 border border-slate-200/80 rounded-2xl overflow-x-auto no-scrollbar shadow-2xs">
                  {[
                    { key: 'all', label: 'Semua', icon: null },
                    { key: 'in_app', label: 'Di aplikasi', icon: Smartphone },
                    { key: 'whatsapp', label: 'WhatsApp', icon: MessageSquare },
                    { key: 'email', label: 'Email', icon: Mail },
                  ].map((item) => {
                    const Icon = item.icon;
                    const isActive = channelFilter === item.key;
                    return (
                      <button
                        key={item.key}
                        type="button"
                        onClick={() => {
                          setChannelFilter(item.key);
                          setPage(1);
                        }}
                        className={cn(
                          'inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl transition-all whitespace-nowrap cursor-pointer',
                          isActive
                            ? 'bg-white text-navy-900 shadow-2xs font-bold border border-navy-100/70'
                            : 'text-slate-600 hover:text-navy-900 hover:bg-white/50'
                        )}
                      >
                        {Icon && <Icon className="w-3.5 h-3.5" strokeWidth={1.75} />}
                        <span>{item.label}</span>
                      </button>
                    );
                  })}
                </div>

                {/* Unread Only Toggle */}
                <label className="inline-flex items-center gap-2 text-xs font-semibold text-navy-900 cursor-pointer select-none bg-slate-50 border border-slate-200/80 px-3 py-2 rounded-xl hover:bg-navy-50/50 transition-colors">
                  <input
                    type="checkbox"
                    checked={unreadOnly}
                    onChange={(e) => {
                      setUnreadOnly(e.target.checked);
                      setPage(1);
                    }}
                    className="w-4 h-4 rounded text-navy-700 focus:ring-navy-700"
                  />
                  <span>Hanya belum dibaca</span>
                </label>
              </div>
            )}
          </div>

          {/* TAB 1: NOTIFICATION LIST */}
          {activeTab === 'list' && (
            <div className="space-y-4">
              {/* Error State */}
              {error ? (
                <div className="bg-white border border-rose-200 rounded-3xl p-8 sm:p-10 text-center space-y-3 shadow-2xs">
                  <div className="w-12 h-12 mx-auto rounded-2xl bg-rose-50 text-rose-600 border border-rose-100 flex items-center justify-center shadow-2xs">
                    <AlertCircle className="w-6 h-6" strokeWidth={1.75} />
                  </div>
                  <div className="space-y-1">
                    <h3 className="text-sm sm:text-base font-bold text-navy-900">Gagal memuat notifikasi</h3>
                    <p className="text-xs text-slate-500 max-w-sm mx-auto leading-relaxed">{error}</p>
                  </div>
                  <button
                    type="button"
                    onClick={fetchNotifications}
                    className="inline-flex items-center gap-1.5 px-4 py-2 bg-navy-700 text-white rounded-xl text-xs font-bold hover:bg-navy-800 transition-colors cursor-pointer shadow-2xs"
                  >
                    <RotateCcw className="w-3.5 h-3.5" strokeWidth={1.75} />
                    <span>Coba lagi</span>
                  </button>
                </div>
              ) : loading ? (
                /* Loading Skeleton */
                <div className="space-y-3">
                  {[1, 2, 3, 4].map((i) => (
                    <div
                      key={i}
                      className="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-4.5 flex items-start gap-3.5 animate-pulse shadow-2xs"
                    >
                      <div className="w-10 h-10 rounded-xl bg-slate-100 shrink-0" />
                      <div className="space-y-2 flex-1">
                        <div className="h-4 bg-slate-200 rounded w-1/3" />
                        <div className="h-3 bg-slate-100 rounded w-3/4" />
                      </div>
                      <div className="h-3 bg-slate-100 rounded w-16" />
                    </div>
                  ))}
                </div>
              ) : notifications.length === 0 ? (
                /* Empty State */
                <div className="bg-white border border-navy-100 rounded-3xl p-8 sm:p-12 text-center space-y-3 shadow-2xs">
                  <div className="w-14 h-14 mx-auto rounded-2xl bg-navy-50 text-navy-700 border border-navy-100 flex items-center justify-center shadow-2xs">
                    <Bell className="w-7 h-7" strokeWidth={1.75} />
                  </div>
                  <div className="space-y-1">
                    <h3 className="text-sm sm:text-base font-bold text-navy-900">
                      {unreadOnly ? 'Semua notifikasi telah dibaca' : 'Belum ada notifikasi'}
                    </h3>
                    <p className="text-xs text-slate-500 max-w-sm mx-auto leading-relaxed">
                      {unreadOnly
                        ? 'Tidak ada pesan yang belum dibaca saat ini.'
                        : 'Pemberitahuan pesanan dan promo akan tampil di sini.'}
                    </p>
                  </div>
                </div>
              ) : (
                /* Notifications List (Individual Squircle Cards) */
                <div className="space-y-3">
                  {notifications.map((notif) => {
                    const isUnread = notif.status === 'unread';
                    const ChannelIcon =
                      notif.channel === 'whatsapp'
                        ? MessageSquare
                        : notif.channel === 'email'
                        ? Mail
                        : notif.channel === 'in_app'
                        ? Smartphone
                        : Bell;

                    return (
                      <div
                        key={notif.id}
                        onClick={() => {
                          if (isUnread) handleMarkAsRead(notif.id);
                          if (notif.payload?.order_id) {
                            router.push(`/orders/${notif.payload.order_id}`);
                          }
                        }}
                        className={cn(
                          'group bg-white border rounded-2xl p-4 sm:p-4.5 flex items-start gap-3.5 transition-all cursor-pointer shadow-2xs hover:shadow-md hover:border-navy-300',
                          isUnread
                            ? 'border-navy-200/90 bg-navy-50/15'
                            : 'border-slate-200/80'
                        )}
                      >
                        {/* Channel Squircle Icon */}
                        <div
                          className={cn(
                            'w-10 h-10 rounded-xl flex items-center justify-center shrink-0 shadow-2xs border transition-colors',
                            isUnread
                              ? 'bg-navy-50 border-navy-200/80 text-navy-800'
                              : 'bg-slate-50 border-slate-200/80 text-slate-500'
                          )}
                          aria-hidden="true"
                        >
                          <ChannelIcon className="w-4 h-4" strokeWidth={1.75} />
                        </div>

                        {/* Content */}
                        <div className="space-y-1 flex-1 min-w-0">
                          <div className="flex items-center gap-2 flex-wrap">
                            <span className="text-[11px] font-bold text-navy-800 bg-navy-50/80 px-2 py-0.5 rounded-md border border-navy-100">
                              {getChannelLabel(notif.channel)}
                            </span>
                            {isUnread && (
                              <span className="w-2 h-2 rounded-full bg-navy-700" title="Belum dibaca" />
                            )}
                            <span className="text-slate-300">·</span>
                            <h3
                              className={cn(
                                'text-xs sm:text-sm truncate transition-colors',
                                isUnread
                                  ? 'font-bold text-navy-950 group-hover:text-navy-700'
                                  : 'font-semibold text-slate-800 group-hover:text-navy-700'
                              )}
                            >
                              {notif.title}
                            </h3>
                          </div>

                          <p className="text-xs text-slate-600 line-clamp-2 leading-relaxed">
                            {notif.message}
                          </p>

                          {notif.payload?.order_number && (
                            <div className="pt-1 flex items-center gap-1.5">
                              <span className="text-[11px] text-slate-500 font-medium">Pesanan:</span>
                              <span className="font-mono text-[11px] font-bold text-navy-800 bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200">
                                #{notif.payload.order_number}
                              </span>
                            </div>
                          )}
                        </div>

                        {/* Timestamp */}
                        <div className="shrink-0 text-right text-[11px] text-slate-400 font-medium tabular-nums pl-1">
                          {new Date(notif.created_at).toLocaleDateString('id-ID', {
                            day: 'numeric',
                            month: 'short',
                            hour: '2-digit',
                            minute: '2-digit',
                          })}
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}

              {/* Pagination */}
              {meta && meta.last_page > 1 && (
                <div className="pt-3 flex justify-center">
                  <Pagination
                    currentPage={meta.current_page}
                    totalPages={meta.last_page}
                    onPageChange={(p) => setPage(p)}
                  />
                </div>
              )}
            </div>
          )}

          {/* TAB 2: MULTI-CHANNEL PREFERENCES */}
          {activeTab === 'preferences' && (
            <div className="space-y-4">
              {prefNotice && (
                <div className="p-3.5 bg-navy-900 text-white text-xs font-semibold rounded-2xl flex items-center justify-between shadow-2xs">
                  <span>{prefNotice}</span>
                  <button
                    type="button"
                    onClick={() => setPrefNotice(null)}
                    className="text-slate-300 hover:text-white cursor-pointer px-2"
                  >
                    ✕
                  </button>
                </div>
              )}

              <div className="p-4 bg-navy-50/70 border border-navy-100 rounded-2xl text-xs text-navy-900 leading-relaxed font-medium">
                Atur penerimaan notifikasi berdasarkan peristiwa transaksi. Kanal WhatsApp dan Notifikasi Push saat ini sedang dalam persiapan integrasi resmi.
              </div>

              {error ? (
                <div className="bg-white border border-rose-200 rounded-3xl p-8 sm:p-10 text-center space-y-3 shadow-2xs">
                  <div className="w-12 h-12 mx-auto rounded-2xl bg-rose-50 text-rose-600 border border-rose-100 flex items-center justify-center shadow-2xs">
                    <AlertCircle className="w-6 h-6" strokeWidth={1.75} />
                  </div>
                  <div className="space-y-1">
                    <h3 className="text-sm sm:text-base font-bold text-navy-900">Gagal memuat preferensi</h3>
                    <p className="text-xs text-slate-500 max-w-sm mx-auto leading-relaxed">{error}</p>
                  </div>
                  <button
                    type="button"
                    onClick={fetchPreferences}
                    className="inline-flex items-center gap-1.5 px-4 py-2 bg-navy-700 text-white rounded-xl text-xs font-bold hover:bg-navy-800 transition-colors cursor-pointer shadow-2xs"
                  >
                    <RotateCcw className="w-3.5 h-3.5" strokeWidth={1.75} />
                    <span>Coba lagi</span>
                  </button>
                </div>
              ) : prefLoading ? (
                <div className="border border-slate-200/90 rounded-3xl bg-white p-5 space-y-3 animate-pulse shadow-2xs">
                  <div className="h-4 bg-slate-200 rounded w-1/4" />
                  <div className="h-12 bg-slate-100 rounded-xl" />
                  <div className="h-12 bg-slate-100 rounded-xl" />
                </div>
              ) : preferences.length === 0 ? (
                <div className="p-8 border border-navy-100 rounded-3xl bg-white text-center text-xs text-slate-500 shadow-2xs">
                  Tidak ada data preferensi yang tersedia.
                </div>
              ) : (
                <div className="border border-navy-100/90 rounded-3xl bg-white overflow-hidden shadow-2xs">
                  <div className="overflow-x-auto">
                    <table className="w-full text-left text-xs border-collapse">
                      <thead>
                        <tr className="bg-slate-50/90 border-b border-slate-200 text-slate-700 font-bold">
                          <th className="py-3.5 px-5">Peristiwa transaksi</th>
                          <th className="py-3.5 px-3 text-center w-28">Di aplikasi</th>
                          <th className="py-3.5 px-3 text-center w-28">Email</th>
                          <th className="py-3.5 px-3 text-center w-32">WhatsApp</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-slate-100">
                        {(preferences.find((g) => g.channel === 'in_app')?.events || []).map((evt) => {
                          const inAppGroup = preferences.find((g) => g.channel === 'in_app');
                          const emailGroup = preferences.find((g) => g.channel === 'email');
                          const waGroup = preferences.find((g) => g.channel === 'whatsapp');

                          const inAppEvt = inAppGroup?.events.find((e) => e.event_type === evt.event_type);
                          const emailEvt = emailGroup?.events.find((e) => e.event_type === evt.event_type);
                          const waEvt = waGroup?.events.find((e) => e.event_type === evt.event_type);

                          return (
                            <tr key={evt.event_type} className="hover:bg-slate-50/50 transition-colors">
                              <td className="py-3.5 px-5 font-semibold text-navy-900">
                                {evt.event_label}
                              </td>

                              {/* In-App Toggle (Di aplikasi) */}
                              <td className="py-3.5 px-3 text-center">
                                <label className="inline-flex items-center justify-center cursor-pointer">
                                  <input
                                    type="checkbox"
                                    checked={inAppEvt ? inAppEvt.is_enabled : true}
                                    disabled={prefSaving}
                                    onChange={(e) =>
                                      handleTogglePreference('in_app', evt.event_type, e.target.checked)
                                    }
                                    className="sr-only peer"
                                  />
                                  <div className="w-9 h-5 bg-slate-200 peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-navy-700 relative shadow-2xs" />
                                </label>
                              </td>

                              {/* Email Toggle */}
                              <td className="py-3.5 px-3 text-center">
                                <label className="inline-flex items-center justify-center cursor-pointer">
                                  <input
                                    type="checkbox"
                                    checked={emailEvt ? emailEvt.is_enabled : true}
                                    disabled={prefSaving}
                                    onChange={(e) =>
                                      handleTogglePreference('email', evt.event_type, e.target.checked)
                                    }
                                    className="sr-only peer"
                                  />
                                  <div className="w-9 h-5 bg-slate-200 peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-navy-700 relative shadow-2xs" />
                                </label>
                              </td>

                              {/* WhatsApp (Segera Hadir) */}
                              <td className="py-3.5 px-3 text-center">
                                <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-100 text-slate-500 border border-slate-200">
                                  Segera hadir
                                </span>
                              </td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default NotificationCenter;
