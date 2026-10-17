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
} from 'lucide-react';
import {
  notificationApi,
  AppNotification,
  NotificationPreferenceGroup,
} from '@/lib/notification';
import { Pagination } from '@/components/ui/Pagination';

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
        return 'In-App';
    }
  };

  return (
    <div className={`space-y-5 max-w-4xl mx-auto ${className}`}>
      {/* Header Bar */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-200">
        <div className="flex items-center gap-2.5">
          <h1 className="text-xl sm:text-2xl font-semibold text-slate-900 tracking-tight">
            Notifikasi
          </h1>
          {unreadCount > 0 && (
            <span className="text-xs text-slate-600 bg-slate-100 border border-slate-200 px-2 py-0.5 rounded font-mono font-medium">
              {unreadCount} belum dibaca
            </span>
          )}
        </div>

        {activeTab === 'list' && unreadCount > 0 && (
          <button
            type="button"
            onClick={handleMarkAllRead}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-slate-700 bg-white hover:bg-slate-50 border border-slate-200 rounded-md transition-colors cursor-pointer self-start sm:self-auto"
          >
            <CheckCheck className="w-3.5 h-3.5 text-slate-500" strokeWidth={1.5} />
            <span>Tandai semua dibaca</span>
          </button>
        )}
      </div>

      {/* Tabs */}
      <div className="flex items-center gap-6 border-b border-slate-200 text-xs sm:text-sm">
        <button
          type="button"
          onClick={() => setActiveTab('list')}
          className={`flex items-center gap-1.5 pb-2.5 font-medium border-b-2 transition-colors cursor-pointer ${
            activeTab === 'list'
              ? 'border-slate-900 text-slate-900'
              : 'border-transparent text-slate-500 hover:text-slate-800'
          }`}
        >
          <Bell className="w-4 h-4" strokeWidth={1.5} />
          <span>Riwayat</span>
        </button>

        <button
          type="button"
          onClick={() => setActiveTab('preferences')}
          className={`flex items-center gap-1.5 pb-2.5 font-medium border-b-2 transition-colors cursor-pointer ${
            activeTab === 'preferences'
              ? 'border-slate-900 text-slate-900'
              : 'border-transparent text-slate-500 hover:text-slate-800'
          }`}
        >
          <Settings2 className="w-4 h-4" strokeWidth={1.5} />
          <span>Pengaturan Kanal</span>
        </button>
      </div>

      {/* TAB 1: NOTIFICATION LIST */}
      {activeTab === 'list' && (
        <div className="space-y-4">
          {/* Controls: Segmented Filter + Unread Checkbox */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-2.5 border border-slate-200 rounded-lg">
            {/* Segmented Channel Control */}
            <div className="inline-flex items-center p-0.5 bg-slate-100 border border-slate-200 rounded-md overflow-x-auto">
              {[
                { key: 'all', label: 'Semua', icon: null },
                { key: 'in_app', label: 'In-App', icon: Smartphone },
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
                    className={`inline-flex items-center gap-1.5 px-3 py-1 text-xs font-medium rounded transition-colors whitespace-nowrap cursor-pointer ${
                      isActive
                        ? 'bg-white text-slate-900 shadow-2xs font-semibold'
                        : 'text-slate-600 hover:text-slate-900'
                    }`}
                  >
                    {Icon && <Icon className="w-3.5 h-3.5" strokeWidth={1.5} />}
                    <span>{item.label}</span>
                  </button>
                );
              })}
            </div>

            {/* Unread Only Toggle */}
            <label className="inline-flex items-center gap-2 text-xs text-slate-700 cursor-pointer select-none">
              <input
                type="checkbox"
                checked={unreadOnly}
                onChange={(e) => {
                  setUnreadOnly(e.target.checked);
                  setPage(1);
                }}
                className="w-3.5 h-3.5 rounded border-slate-300 text-[#1A3A5C] focus:ring-[#1A3A5C]"
              />
              <span>Hanya belum dibaca</span>
            </label>
          </div>

          {/* List Content */}
          {error ? (
            <div className="p-6 border border-slate-200 rounded-lg bg-white text-center space-y-2">
              <p className="text-xs text-rose-600">{error}</p>
              <button
                type="button"
                onClick={fetchNotifications}
                className="inline-flex items-center gap-1 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 border border-slate-200 rounded-md"
              >
                <RotateCcw className="w-3.5 h-3.5" strokeWidth={1.5} />
                <span>Coba lagi</span>
              </button>
            </div>
          ) : loading ? (
            <div className="border border-slate-200 rounded-lg bg-white divide-y divide-slate-100 overflow-hidden">
              {[1, 2, 3, 4, 5].map((i) => (
                <div key={i} className="p-3.5 flex items-start gap-3 animate-pulse">
                  <div className="w-2 h-2 rounded-full bg-slate-200 mt-1.5 shrink-0" />
                  <div className="space-y-2 flex-1">
                    <div className="h-3.5 bg-slate-200 rounded w-1/3" />
                    <div className="h-3 bg-slate-100 rounded w-3/4" />
                  </div>
                  <div className="h-3 bg-slate-100 rounded w-16" />
                </div>
              ))}
            </div>
          ) : notifications.length === 0 ? (
            <div className="p-8 border border-slate-200 rounded-lg bg-white text-center">
              <p className="text-xs text-slate-500">
                {unreadOnly
                  ? 'Semua notifikasi telah dibaca.'
                  : 'Belum ada notifikasi yang tercatat.'}
              </p>
            </div>
          ) : (
            <div className="border border-slate-200 rounded-lg bg-white divide-y divide-slate-100 overflow-hidden">
              {notifications.map((notif) => {
                const isUnread = notif.status === 'unread';
                return (
                  <div
                    key={notif.id}
                    onClick={() => {
                      if (isUnread) handleMarkAsRead(notif.id);
                      if (notif.payload?.order_id) {
                        router.push(`/orders/${notif.payload.order_id}`);
                      }
                    }}
                    className={`p-3.5 sm:p-4 flex items-start gap-3 transition-colors cursor-pointer ${
                      isUnread ? 'bg-slate-50/70 hover:bg-slate-50' : 'hover:bg-slate-50/40'
                    }`}
                  >
                    {/* Status Dot */}
                    <div className="mt-1 shrink-0">
                      {isUnread ? (
                        <span className="block w-2 h-2 rounded-full bg-[#1A3A5C]" />
                      ) : (
                        <span className="block w-2 h-2 rounded-full bg-transparent" />
                      )}
                    </div>

                    {/* Content */}
                    <div className="space-y-1 flex-1 min-w-0">
                      <div className="flex items-center gap-2 flex-wrap">
                        <span className="text-xs text-slate-400 font-medium">
                          {getChannelLabel(notif.channel)}
                        </span>
                        <span className="text-slate-300">•</span>
                        <h3
                          className={`text-xs sm:text-sm truncate ${
                            isUnread ? 'font-semibold text-slate-900' : 'font-medium text-slate-800'
                          }`}
                        >
                          {notif.title}
                        </h3>
                      </div>

                      <p className="text-xs text-slate-600 line-clamp-2 sm:line-clamp-1 leading-relaxed">
                        {notif.message}
                      </p>

                      {notif.payload?.order_number && (
                        <div className="text-[11px] font-mono text-slate-500 pt-0.5">
                          Pesanan: #{notif.payload.order_number}
                        </div>
                      )}
                    </div>

                    {/* Timestamp */}
                    <div className="shrink-0 text-right text-[11px] text-slate-400 tabular-nums">
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
            <div className="pt-2 flex justify-center">
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
            <div className="p-3 bg-slate-900 text-white text-xs rounded-md flex items-center justify-between">
              <span>{prefNotice}</span>
              <button
                type="button"
                onClick={() => setPrefNotice(null)}
                className="text-slate-400 hover:text-white"
              >
                ✕
              </button>
            </div>
          )}

          <div className="p-3.5 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-600 leading-relaxed">
            Atur penerimaan notifikasi berdasarkan peristiwa transaksi. Kanal WhatsApp dan Notifikasi Push saat ini sedang dalam persiapan integrasi resmi.
          </div>

          {error ? (
            <div className="p-6 border border-slate-200 rounded-lg bg-white text-center space-y-2">
              <p className="text-xs text-rose-600">{error}</p>
              <button
                type="button"
                onClick={fetchPreferences}
                className="inline-flex items-center gap-1 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50 border border-slate-200 rounded-md"
              >
                <RotateCcw className="w-3.5 h-3.5" strokeWidth={1.5} />
                <span>Coba lagi</span>
              </button>
            </div>
          ) : prefLoading ? (
            <div className="border border-slate-200 rounded-lg bg-white p-4 space-y-3 animate-pulse">
              <div className="h-4 bg-slate-200 rounded w-1/4" />
              <div className="h-10 bg-slate-100 rounded" />
              <div className="h-10 bg-slate-100 rounded" />
            </div>
          ) : preferences.length === 0 ? (
            <div className="p-8 border border-slate-200 rounded-lg bg-white text-center text-xs text-slate-500">
              Tidak ada data preferensi yang tersedia.
            </div>
          ) : (
            <div className="space-y-4">
              {/* Event Matrix Configuration Table */}
              <div className="border border-slate-200 rounded-lg bg-white overflow-hidden">
                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs border-collapse">
                    <thead>
                      <tr className="bg-slate-50 border-b border-slate-200 text-slate-700 font-semibold">
                        <th className="py-3 px-4">Peristiwa Transaksi</th>
                        <th className="py-3 px-3 text-center w-28">In-App</th>
                        <th className="py-3 px-3 text-center w-28">Email</th>
                        <th className="py-3 px-3 text-center w-32">WhatsApp</th>
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
                          <tr key={evt.event_type} className="hover:bg-slate-50/40 transition-colors">
                            <td className="py-3.5 px-4 font-medium text-slate-900">
                              {evt.event_label}
                            </td>

                            {/* In-App Toggle */}
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
                                <div className="w-8 h-4 bg-slate-200 peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3.5 after:transition-all peer-checked:bg-[#1A3A5C] relative" />
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
                                <div className="w-8 h-4 bg-slate-200 peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3.5 after:transition-all peer-checked:bg-[#1A3A5C] relative" />
                              </label>
                            </td>

                            {/* WhatsApp (Segera Hadir) */}
                            <td className="py-3.5 px-3 text-center">
                              <div className="inline-flex flex-col items-center">
                                <div className="w-8 h-4 bg-slate-100 rounded-full relative opacity-50 cursor-not-allowed">
                                  <div className="h-3 w-3.5 bg-slate-300 rounded-full absolute top-[2px] left-[2px]" />
                                </div>
                                <span className="text-[10px] text-slate-400 mt-0.5">segera hadir</span>
                              </div>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default NotificationCenter;
