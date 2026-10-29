'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { PublicLayout } from '@/components/layout/PublicLayout';
import {
  getConversations,
  ConversationItem,
} from '@/lib/chat';
import { getStoredUser, AuthUser } from '@/lib/auth';
import { formatDate, cn } from '@/lib/format';
import {
  MessageSquare,
  Store,
  ChevronRight,
  RotateCw,
  AlertCircle,
  ArrowRight,
} from 'lucide-react';

export default function CustomerConversationsPage() {
  const router = useRouter();
  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);
  const [conversations, setConversations] = useState<ConversationItem[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  const loadConversations = async () => {
    setLoading(true);
    setError(null);
    try {
      const user = getStoredUser();
      setCurrentUser(user);

      const res = await getConversations('customer');
      setConversations(res || []);
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { error?: { message?: string } } }; message?: string };
      setError(errObj.response?.data?.error?.message || errObj.message || 'Gagal memuat daftar percakapan.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadConversations();
  }, []);

  return (
    <PublicLayout>
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

        {/* Hero Content */}
        <div className="max-w-4xl mx-auto relative z-10 space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div className="space-y-1.5 max-w-xl">
              <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-white leading-tight">
                Pesan &amp; chat toko
              </h1>
              <p className="text-xs text-navy-200 leading-relaxed">
                Komunikasi langsung dengan pihak outlet laundry untuk konsultasi layanan dan pakaian.
              </p>
            </div>

            <button
              type="button"
              onClick={loadConversations}
              disabled={loading}
              className="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold text-white bg-navy-800 hover:bg-navy-700 border border-navy-700 rounded-xl transition-all shadow-2xs cursor-pointer self-start sm:self-end disabled:opacity-50"
            >
              <RotateCw className={cn('w-4 h-4 text-sky-300', loading && 'animate-spin text-white')} strokeWidth={1.75} />
              <span>Segarkan</span>
            </button>
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
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 -mt-6 sm:-mt-7 relative z-20 space-y-4">
          {/* Loading Skeletons */}
          {loading && (
            <div className="space-y-3">
              {[1, 2, 3].map((i) => (
                <div key={i} className="h-24 bg-white border border-slate-200/90 rounded-3xl animate-pulse shadow-2xs" />
              ))}
            </div>
          )}

          {/* Error State */}
          {!loading && error && (
            <div className="bg-white border border-rose-200 rounded-3xl p-8 sm:p-10 text-center space-y-3 shadow-2xs">
              <div className="w-12 h-12 mx-auto rounded-2xl bg-rose-50 text-rose-600 border border-rose-100 flex items-center justify-center shadow-2xs">
                <AlertCircle className="w-6 h-6" strokeWidth={1.75} />
              </div>
              <div className="space-y-1">
                <h2 className="text-sm sm:text-base font-bold text-navy-900">Gagal memuat percakapan</h2>
                <p className="text-xs text-slate-500 max-w-sm mx-auto leading-relaxed">{error}</p>
              </div>
              <button
                type="button"
                onClick={loadConversations}
                className="inline-flex items-center gap-1.5 px-4 py-2 bg-navy-700 text-white rounded-xl text-xs font-bold hover:bg-navy-800 transition-colors cursor-pointer shadow-2xs"
              >
                <RotateCw className="w-3.5 h-3.5" strokeWidth={1.75} />
                <span>Coba lagi</span>
              </button>
            </div>
          )}

          {/* Conversations List */}
          {!loading && !error && (
            <div>
              {conversations.length === 0 ? (
                /* Empty State */
                <div className="bg-white border border-navy-100 rounded-3xl p-8 sm:p-12 text-center space-y-3 shadow-2xs">
                  <div className="w-14 h-14 mx-auto rounded-2xl bg-navy-50 text-navy-700 border border-navy-100 flex items-center justify-center shadow-2xs">
                    <MessageSquare className="w-7 h-7" strokeWidth={1.75} />
                  </div>
                  <div className="space-y-1">
                    <h3 className="text-sm sm:text-base font-bold text-navy-900">Belum ada percakapan</h3>
                    <p className="text-xs text-slate-500 max-w-sm mx-auto leading-relaxed">
                      Mulai percakapan dengan toko laundry mitra dari halaman detail toko atau detail pesanan Anda.
                    </p>
                  </div>
                  <div className="pt-2">
                    <Link
                      href="/stores"
                      className="inline-flex items-center gap-1.5 px-4 py-2 bg-navy-700 text-white rounded-xl text-xs font-bold hover:bg-navy-800 transition-colors shadow-2xs"
                    >
                      <span>Cari toko laundry</span>
                      <ArrowRight className="w-3.5 h-3.5" strokeWidth={1.75} />
                    </Link>
                  </div>
                </div>
              ) : (
                /* Conversations Cards */
                <div className="space-y-3">
                  {conversations.map((conv) => {
                    const hasUnread = conv.unread_count > 0;
                    return (
                      <Link
                        key={conv.id}
                        href={`/chat/${conv.id}`}
                        className={cn(
                          'group bg-white border rounded-3xl p-4 sm:p-5 flex items-center justify-between gap-4 transition-all shadow-2xs hover:shadow-md hover:border-navy-300 cursor-pointer',
                          hasUnread
                            ? 'border-navy-300 bg-navy-50/15'
                            : 'border-slate-200/90'
                        )}
                      >
                        <div className="flex items-center gap-3.5 min-w-0 flex-1">
                          {/* Store Squircle Monogram Avatar */}
                          <div
                            className={cn(
                              'w-12 h-12 rounded-2xl flex items-center justify-center font-extrabold text-sm tracking-wider shrink-0 shadow-2xs border transition-transform group-hover:scale-105',
                              hasUnread
                                ? 'bg-navy-700 text-white border-navy-800'
                                : 'bg-navy-50 text-navy-800 border-navy-200/80'
                            )}
                          >
                            <Store className="w-5 h-5" strokeWidth={1.75} />
                          </div>

                          {/* Info & Last Message */}
                          <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-2 mb-1 flex-wrap">
                              <span
                                className={cn(
                                  'text-sm truncate transition-colors group-hover:text-navy-700',
                                  hasUnread ? 'font-black text-navy-950' : 'font-bold text-navy-900'
                                )}
                              >
                                {conv.other_party_name}
                              </span>

                              {conv.order_number && (
                                <span className="font-mono text-[10px] font-bold text-navy-800 bg-navy-50 border border-navy-100 px-2 py-0.5 rounded-md">
                                  #{conv.order_number}
                                </span>
                              )}

                              {hasUnread && (
                                <span className="w-2 h-2 rounded-full bg-navy-700 shrink-0" title="Ada pesan belum dibaca" />
                              )}
                            </div>

                            <p
                              className={cn(
                                'text-xs truncate max-w-md leading-relaxed',
                                hasUnread ? 'font-bold text-navy-950' : 'font-medium text-slate-500'
                              )}
                            >
                              {conv.last_message?.message_type === 'image'
                                ? 'Foto terkirim'
                                : conv.last_message?.body || 'Belum ada pesan.'}
                            </p>
                          </div>
                        </div>

                        {/* Right: Timestamp & Unread Badge */}
                        <div className="flex flex-col items-end gap-1.5 shrink-0 pl-1">
                          <span className="text-[11px] text-slate-400 font-medium tabular-nums">
                            {formatDate(conv.last_message_at || conv.created_at, 'DD MMM, HH:mm')}
                          </span>

                          <div className="flex items-center gap-2">
                            {hasUnread && (
                              <span className="px-2.5 py-0.5 text-[10px] font-extrabold text-white bg-navy-700 rounded-full shadow-2xs tabular-nums">
                                {conv.unread_count > 9 ? '9+' : conv.unread_count} baru
                              </span>
                            )}
                            <ChevronRight className="w-4 h-4 text-slate-400 group-hover:text-navy-700 transition-colors" strokeWidth={1.75} />
                          </div>
                        </div>
                      </Link>
                    );
                  })}
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </PublicLayout>
  );
}
