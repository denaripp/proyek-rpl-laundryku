'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { PublicLayout } from '@/components/layout/PublicLayout';
import {
  Card,
  Badge,
  Button,
  Skeleton,
  EmptyState,
  ErrorState,
} from '@/components/ui';
import {
  getConversations,
  ConversationItem,
} from '@/lib/chat';
import { getStoredUser, AuthUser } from '@/lib/auth';
import { formatDate, cn } from '@/lib/format';
import {
  MessageSquare,
  Building2,
  ShoppingBag,
  Clock,
  ChevronRight,
  Sparkles,
  ArrowLeft,
  RotateCw,
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
      <div className="bg-gradient-to-b from-sky-50/60 via-white to-sky-50/40 min-h-[calc(100vh-5rem)] py-8">
        <div className="max-w-4xl mx-auto px-4 sm:px-6">
          {/* Header */}
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
              <h1 className="font-heading font-extrabold text-2xl text-navy-900 flex items-center gap-2.5">
                <MessageSquare className="w-6 h-6 text-teal-600" />
                <span>Pesan & Chat Toko</span>
              </h1>
              <p className="text-xs text-slate-500 mt-1">
                Komunikasi langsung dengan pihak outlet laundry untuk konsultasi layanan dan pakaian.
              </p>
            </div>

            <Button
              variant="outline"
              onClick={loadConversations}
              disabled={loading}
              className="rounded-xl text-xs font-bold min-h-[44px]"
            >
              <RotateCw className={cn('w-4 h-4 mr-2', loading && 'animate-spin')} />
              <span>Segarkan</span>
            </Button>
          </div>

          {/* Loading */}
          {loading && (
            <div className="space-y-4">
              <Skeleton className="h-24 rounded-3xl" />
              <Skeleton className="h-24 rounded-3xl" />
              <Skeleton className="h-24 rounded-3xl" />
            </div>
          )}

          {/* Error */}
          {!loading && error && (
            <Card variant="default" className="p-8 text-center bg-white rounded-3xl border border-red-200">
              <ErrorState
                title="Gagal Memuat Percakapan"
                message={error}
                retryText="Coba Lagi"
                onRetry={loadConversations}
              />
            </Card>
          )}

          {/* Conversations List */}
          {!loading && !error && (
            <div>
              {conversations.length === 0 ? (
                <EmptyState
                  title="Belum Ada Percakapan"
                  description="Mulai percakapan dengan toko laundry mitra dari halaman detail toko atau detail pesanan Anda."
                  actionText="Cari Toko Laundry"
                  onAction={() => router.push('/stores')}
                />
              ) : (
                <div className="space-y-3">
                  {conversations.map((conv) => (
                    <Link
                      key={conv.id}
                      href={`/chat/${conv.id}`}
                      className={cn(
                        'block p-4 sm:p-5 rounded-3xl border transition-all group',
                        conv.unread_count > 0
                          ? 'bg-teal-50/25 border-teal-300 shadow-xs hover:border-teal-400 hover:shadow-md'
                          : 'bg-white border-slate-200 hover:border-teal-400 hover:shadow-md'
                      )}
                    >
                      <div className="flex items-center justify-between gap-4">
                        <div className="flex items-center gap-4 min-w-0">
                          {/* Store Avatar */}
                          <div className={cn(
                            'w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform border',
                            conv.unread_count > 0
                              ? 'bg-teal-500 text-navy-900 border-teal-500'
                              : 'bg-teal-500/15 border-teal-500/30 text-teal-700'
                          )}>
                            <Building2 className="w-6 h-6" />
                          </div>

                          {/* Info & Last Message */}
                          <div className="min-w-0">
                            <div className="flex items-center gap-2 mb-1 flex-wrap">
                              <span className={cn(
                                'font-heading text-sm truncate',
                                conv.unread_count > 0 ? 'font-black text-navy-900' : 'font-bold text-navy-900'
                              )}>
                                {conv.other_party_name}
                              </span>
                              {conv.order_number && (
                                <Badge variant="secondary" className="text-[10px] font-mono">
                                  #{conv.order_number}
                                </Badge>
                              )}
                              {conv.unread_count > 0 && (
                                <span className="inline-block w-2 h-2 rounded-full bg-teal-500 shrink-0" title="Ada pesan baru" />
                              )}
                            </div>

                            <p className={cn(
                              'text-xs truncate max-w-md',
                              conv.unread_count > 0 ? 'font-bold text-slate-800' : 'text-slate-500'
                            )}>
                              {conv.last_message?.message_type === 'image'
                                ? '📷 Mengirimkan foto'
                                : conv.last_message?.body || 'Belum ada pesan.'}
                            </p>
                          </div>
                        </div>

                        {/* Right: Timestamp & Unread Badge */}
                        <div className="flex flex-col items-end gap-1.5 shrink-0">
                          <span className="text-[10px] text-slate-400 font-medium">
                            {formatDate(conv.last_message_at || conv.created_at, 'DD MMM, HH:mm')}
                          </span>

                          <div className="flex items-center gap-2">
                            {conv.unread_count > 0 && (
                              <Badge variant="teal" className="px-2 py-0.5 text-[10px] font-extrabold rounded-full animate-pulse">
                                {conv.unread_count > 9 ? '9+' : conv.unread_count} baru
                              </Badge>
                            )}
                            <ChevronRight className="w-4 h-4 text-slate-400 group-hover:text-teal-600 transition-colors" />
                          </div>
                        </div>
                      </div>
                    </Link>
                  ))}
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </PublicLayout>
  );
}
