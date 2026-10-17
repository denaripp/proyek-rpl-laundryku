'use client';

import React, { useState, useEffect, useRef, use } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { PublicLayout } from '@/components/layout/PublicLayout';
import {
  Card,
  Badge,
  Button,
  Input,
  Skeleton,
  ErrorState,
} from '@/components/ui';
import {
  getConversationDetail,
  sendChatMessage,
  pollChatMessages,
  ConversationItem,
  ChatMessageItem,
} from '@/lib/chat';
import { getStoredUser, AuthUser } from '@/lib/auth';
import { formatDate, cn } from '@/lib/format';
import {
  ArrowLeft,
  Send,
  Image as ImageIcon,
  Paperclip,
  CheckCheck,
  Check,
  Building2,
  User,
  ShoppingBag,
  Clock,
  X,
  Sparkles,
} from 'lucide-react';

export default function ChatDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const resolvedParams = use(params);
  const conversationId = resolvedParams.id;
  const router = useRouter();

  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);
  const [conversation, setConversation] = useState<ConversationItem | null>(null);
  const [messages, setMessages] = useState<ChatMessageItem[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  // Input state
  const [inputText, setInputText] = useState<string>('');
  const [selectedImage, setSelectedImage] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState<string | null>(null);
  const [sending, setSending] = useState<boolean>(false);

  const messagesEndRef = useRef<HTMLDivElement>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Scroll to bottom smoothly
  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  // Initial load
  const loadConversation = async () => {
    setLoading(true);
    setError(null);
    try {
      const user = getStoredUser();
      setCurrentUser(user);

      const res = await getConversationDetail(conversationId);
      setConversation(res.conversation);
      setMessages(res.messages || []);

      if (typeof window !== 'undefined') {
        window.dispatchEvent(new Event('laundryku_chat_read'));
      }
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { error?: { message?: string } } }; message?: string };
      setError(errObj.response?.data?.error?.message || errObj.message || 'Gagal memuat percakapan.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadConversation();
  }, [conversationId]);

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  // Polling for new messages every 3 seconds
  useEffect(() => {
    if (!conversationId || loading || error) return;

    const interval = setInterval(async () => {
      try {
        const lastMsg = messages.length > 0 ? messages[messages.length - 1] : null;
        const newMsgs = await pollChatMessages(conversationId, lastMsg ? lastMsg.created_at : undefined);

        if (newMsgs && newMsgs.length > 0) {
          setMessages((prev) => {
            const existingIds = new Set(prev.map((m) => m.id));
            const uniqueNew = newMsgs.filter((m) => !existingIds.has(m.id));
            if (uniqueNew.length > 0) {
              return [...prev, ...uniqueNew];
            }
            return prev;
          });
        }
      } catch (e) {
        // Fail silently during polling
      }
    }, 3000);

    return () => clearInterval(interval);
  }, [conversationId, messages, loading, error]);

  // Handle Image File Selection
  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      if (file.size > 5 * 1024 * 1024) {
        alert('Ukuran foto maksimal adalah 5MB.');
        return;
      }
      setSelectedImage(file);
      setImagePreview(URL.createObjectURL(file));
    }
  };

  const removeSelectedImage = () => {
    setSelectedImage(null);
    if (imagePreview) {
      URL.revokeObjectURL(imagePreview);
      setImagePreview(null);
    }
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  // Handle Send Message
  const handleSendMessage = async (e?: React.FormEvent) => {
    if (e) e.preventDefault();
    if (!inputText.trim() && !selectedImage) return;

    setSending(true);
    try {
      const newMsg = await sendChatMessage(conversationId, {
        body: inputText.trim() || undefined,
        image: selectedImage,
      });

      setMessages((prev) => [...prev, newMsg]);
      setInputText('');
      removeSelectedImage();
    } catch (err: unknown) {
      const errObj = err as { message?: string };
      alert(errObj.message || 'Gagal mengirim pesan.');
    } finally {
      setSending(false);
    }
  };

  return (
    <PublicLayout>
      <div className="bg-gradient-to-b from-sky-50/60 via-white to-sky-50/40 min-h-[calc(100vh-5rem)] py-6 sm:py-8">
        <div className="max-w-4xl mx-auto px-4 sm:px-6">
          {/* Header Card */}
          <Card variant="bento" className="p-4 sm:p-5 mb-4 shadow-sm">
            <div className="flex items-center justify-between gap-4">
              <div className="flex items-center gap-3">
                <Link
                  href="/chat"
                  className="w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200 flex items-center justify-center text-slate-600 transition-colors shrink-0 min-h-[40px]"
                >
                  <ArrowLeft className="w-5 h-5" />
                </Link>

                <div className="flex items-center gap-3">
                  <div className="w-11 h-11 rounded-2xl bg-teal-500/15 border border-teal-500/30 flex items-center justify-center text-teal-700 shrink-0 font-bold">
                    <Building2 className="w-5 h-5" />
                  </div>
                  <div>
                    <h1 className="font-heading font-extrabold text-base text-navy-900 leading-tight">
                      {conversation?.other_party_name || 'Percakapan Toko'}
                    </h1>
                    <div className="flex items-center gap-2 mt-0.5">
                      <span className="text-[11px] text-teal-700 font-semibold bg-teal-50 px-2 py-0.5 rounded border border-teal-200/60">
                        Admin Toko
                      </span>
                      {conversation?.order_number && (
                        <Link
                          href={`/orders/${conversation.order_id}`}
                          className="text-[11px] text-slate-500 hover:text-navy-900 flex items-center gap-1 font-mono"
                        >
                          <ShoppingBag className="w-3 h-3" />
                          <span>{conversation.order_number}</span>
                        </Link>
                      )}
                    </div>
                  </div>
                </div>
              </div>

              {conversation?.order_id && (
                <Link
                  href={`/orders/${conversation.order_id}`}
                  className="hidden sm:inline-flex items-center gap-1.5 text-xs font-bold text-teal-700 bg-sky-50 px-3 py-1.5 rounded-xl border border-sky-200 hover:bg-sky-100 transition-colors"
                >
                  <ShoppingBag className="w-3.5 h-3.5" />
                  <span>Lihat Pesanan</span>
                </Link>
              )}
            </div>
          </Card>

          {/* Chat Container Card */}
          <Card variant="default" className="bg-slate-50/80 rounded-3xl border border-slate-200 shadow-sm flex flex-col h-[65vh] sm:h-[70vh] overflow-hidden">
            {/* Loading */}
            {loading && (
              <div className="flex-1 p-6 space-y-4">
                <Skeleton className="h-14 w-2/3 rounded-2xl" />
                <Skeleton className="h-20 w-1/2 ml-auto rounded-2xl" />
                <Skeleton className="h-14 w-3/5 rounded-2xl" />
              </div>
            )}

            {/* Error */}
            {!loading && error && (
              <div className="flex-1 flex items-center justify-center p-6">
                <ErrorState
                  title="Gagal Memuat Pesan"
                  message={error}
                  retryText="Coba Lagi"
                  onRetry={loadConversation}
                />
              </div>
            )}

            {/* Messages Feed */}
            {!loading && !error && (
              <div className="flex-1 p-4 sm:p-6 overflow-y-auto space-y-4">
                {messages.length === 0 ? (
                  <div className="h-full flex flex-col items-center justify-center text-center p-6 text-slate-400">
                    <div className="w-12 h-12 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center mb-3">
                      <Sparkles className="w-6 h-6" />
                    </div>
                    <p className="text-xs font-semibold text-slate-600">Belum ada riwayat pesan.</p>
                    <p className="text-[11px] text-slate-400 mt-1 max-w-xs">
                      Tanyakan detail layanan, request pencucian, atau konsultasikan pesanan Anda di sini.
                    </p>
                  </div>
                ) : (
                  messages.map((msg) => {
                    const isMe = msg.sender_id === currentUser?.id;

                    return (
                      <div
                        key={msg.id}
                        className={cn('flex flex-col', isMe ? 'items-end' : 'items-start')}
                      >
                        <div
                          className={cn(
                            'max-w-[85%] sm:max-w-[70%] p-3.5 sm:p-4 rounded-3xl text-xs space-y-2 shadow-sm',
                            isMe
                              ? 'bg-navy-900 text-white rounded-tr-none'
                              : 'bg-white text-slate-800 border border-slate-200 rounded-tl-none'
                          )}
                        >
                          {/* Sender Name if incoming */}
                          {!isMe && msg.sender_name && (
                            <span className="font-bold text-[11px] text-teal-600 block">
                              {msg.sender_name}
                            </span>
                          )}

                          {/* Image Attachment */}
                          {msg.attachment_url && (
                            <div className="rounded-2xl overflow-hidden border border-white/20 my-1">
                              {/* eslint-disable-next-line @next/next/no-img-element */}
                              <img
                                src={msg.attachment_url}
                                alt="Lampiran Foto"
                                className="w-full max-h-60 object-cover rounded-xl hover:opacity-95 transition-opacity cursor-pointer"
                                onClick={() => window.open(msg.attachment_url || '', '_blank')}
                              />
                            </div>
                          )}

                          {/* Message Text Body */}
                          {msg.body && msg.body !== '[Foto]' && (
                            <p className="leading-relaxed whitespace-pre-wrap">{msg.body}</p>
                          )}

                          {/* Timestamp & Read Indicator */}
                          <div
                            className={cn(
                              'flex items-center justify-end gap-1.5 text-[10px] pt-1',
                              isMe ? 'text-slate-400' : 'text-slate-400'
                            )}
                          >
                            <span>{formatDate(msg.created_at, 'HH:mm')}</span>
                            {isMe && (
                              <span>
                                {msg.is_read ? (
                                  <CheckCheck className="w-3.5 h-3.5 text-teal-400" />
                                ) : (
                                  <Check className="w-3.5 h-3.5 text-slate-400" />
                                )}
                              </span>
                            )}
                          </div>
                        </div>
                      </div>
                    );
                  })
                )}
                <div ref={messagesEndRef} />
              </div>
            )}

            {/* Image Preview Bar before sending */}
            {imagePreview && (
              <div className="px-4 py-2 bg-slate-100/90 border-t border-slate-200 flex items-center justify-between">
                <div className="flex items-center gap-2">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={imagePreview} alt="Preview" className="w-10 h-10 rounded-lg object-cover border border-slate-300" />
                  <span className="text-[11px] text-slate-600 truncate max-w-[200px]">
                    {selectedImage?.name}
                  </span>
                </div>
                <button
                  type="button"
                  onClick={removeSelectedImage}
                  className="p-1.5 rounded-full hover:bg-slate-200 text-slate-500"
                >
                  <X className="w-4 h-4" />
                </button>
              </div>
            )}

            {/* Chat Input Bar */}
            <form onSubmit={handleSendMessage} className="p-3 sm:p-4 bg-white border-t border-slate-200 flex items-center gap-2">
              <input
                type="file"
                ref={fileInputRef}
                onChange={handleFileChange}
                accept="image/jpeg,image/png,image/jpg,image/webp"
                className="hidden"
              />

              <button
                type="button"
                onClick={() => fileInputRef.current?.click()}
                disabled={sending}
                className="w-11 h-11 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition-colors shrink-0 min-h-[44px]"
                title="Kirim Foto"
              >
                <Paperclip className="w-5 h-5 text-teal-600" />
              </button>

              <Input
                type="text"
                value={inputText}
                onChange={(e) => setInputText(e.target.value)}
                placeholder="Ketik pesan untuk toko laundry..."
                disabled={sending}
                className="flex-1 text-xs"
              />

              <Button
                type="submit"
                disabled={sending || (!inputText.trim() && !selectedImage)}
                className="bg-teal-600 hover:bg-teal-700 text-white font-bold rounded-2xl px-5 min-h-[44px] shrink-0"
              >
                <Send className="w-4 h-4" />
              </Button>
            </form>
          </Card>
        </div>
      </div>
    </PublicLayout>
  );
}
