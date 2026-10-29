'use client';

import React, { useState, useEffect, useRef, use } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { PublicLayout } from '@/components/layout/PublicLayout';
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
  Paperclip,
  CheckCheck,
  Check,
  Store,
  ShoppingBag,
  X,
  MessageSquare,
  AlertCircle,
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
      } catch {
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
      <div className="bg-slate-50 min-h-[calc(100vh-8rem)] py-4 sm:py-6">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 space-y-3 sm:space-y-4">
          {/* Header Card: Store Info & Back Button */}
          <div className="bg-white border border-navy-100/90 rounded-3xl p-4 sm:p-5 shadow-2xs flex items-center justify-between gap-4">
            <div className="flex items-center gap-3 min-w-0">
              <Link
                href="/chat"
                className="w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200 border border-slate-200/80 flex items-center justify-center text-navy-800 transition-colors shrink-0 min-h-[40px] cursor-pointer"
                aria-label="Kembali ke daftar pesan"
              >
                <ArrowLeft className="w-5 h-5" strokeWidth={1.75} />
              </Link>

              <div className="flex items-center gap-3 min-w-0">
                <div
                  className="w-11 h-11 rounded-xl bg-navy-50 border border-navy-200/80 flex items-center justify-center text-navy-800 shrink-0 font-bold shadow-2xs"
                  aria-hidden="true"
                >
                  <Store className="w-5 h-5" strokeWidth={1.75} />
                </div>
                <div className="min-w-0">
                  <h1 className="font-bold text-sm sm:text-base text-navy-900 leading-tight truncate">
                    {conversation?.other_party_name || 'Percakapan toko'}
                  </h1>
                  <div className="flex items-center gap-2 mt-1 flex-wrap">
                    <span className="text-[10px] text-navy-800 font-bold bg-navy-50 px-2 py-0.5 rounded-md border border-navy-100">
                      Admin toko
                    </span>
                    {conversation?.order_number && (
                      <Link
                        href={`/orders/${conversation.order_id}`}
                        className="text-[10px] text-slate-500 hover:text-navy-900 flex items-center gap-1 font-mono font-bold bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200 transition-colors"
                      >
                        <ShoppingBag className="w-3 h-3 text-slate-400" strokeWidth={1.75} />
                        <span>#{conversation.order_number}</span>
                      </Link>
                    )}
                  </div>
                </div>
              </div>
            </div>

            {conversation?.order_id && (
              <Link
                href={`/orders/${conversation.order_id}`}
                className="hidden sm:inline-flex items-center gap-1.5 text-xs font-bold text-navy-800 bg-white hover:bg-navy-50 px-3.5 py-2 rounded-xl border border-slate-200/90 hover:border-navy-200 transition-all shadow-2xs shrink-0"
              >
                <ShoppingBag className="w-3.5 h-3.5 text-navy-700" strokeWidth={1.75} />
                <span>Lihat pesanan</span>
              </Link>
            )}
          </div>

          {/* Chat Container Card */}
          <div className="bg-slate-50/70 rounded-3xl border border-navy-100/90 shadow-sm flex flex-col h-[65vh] sm:h-[72vh] overflow-hidden">
            {/* Loading Skeleton */}
            {loading && (
              <div className="flex-1 p-6 space-y-4">
                <div className="h-14 w-2/3 rounded-2xl bg-white border border-slate-200/80 animate-pulse" />
                <div className="h-20 w-1/2 ml-auto rounded-2xl bg-navy-100/50 animate-pulse" />
                <div className="h-14 w-3/5 rounded-2xl bg-white border border-slate-200/80 animate-pulse" />
              </div>
            )}

            {/* Error State */}
            {!loading && error && (
              <div className="flex-1 flex flex-col items-center justify-center p-6 text-center space-y-3">
                <div className="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 border border-rose-100 flex items-center justify-center shadow-2xs">
                  <AlertCircle className="w-6 h-6" strokeWidth={1.75} />
                </div>
                <div className="space-y-1">
                  <h3 className="text-sm font-bold text-navy-900">Gagal memuat pesan</h3>
                  <p className="text-xs text-slate-500 max-w-sm">{error}</p>
                </div>
                <button
                  type="button"
                  onClick={loadConversation}
                  className="inline-flex items-center px-4 py-2 bg-navy-700 text-white rounded-xl text-xs font-bold hover:bg-navy-800 transition-colors cursor-pointer shadow-2xs"
                >
                  Coba lagi
                </button>
              </div>
            )}

            {/* Messages Feed */}
            {!loading && !error && (
              <div className="flex-1 p-4 sm:p-6 overflow-y-auto space-y-3.5">
                {messages.length === 0 ? (
                  <div className="h-full flex flex-col items-center justify-center text-center p-6 text-slate-400 select-none">
                    <div className="w-14 h-14 rounded-2xl bg-navy-50 text-navy-700 border border-navy-100 flex items-center justify-center mb-3 shadow-2xs">
                      <MessageSquare className="w-7 h-7" strokeWidth={1.75} />
                    </div>
                    <p className="text-sm font-bold text-navy-900">Belum ada riwayat pesan</p>
                    <p className="text-xs text-slate-500 mt-1 max-w-xs leading-relaxed">
                      Tanyakan detail layanan, permintaan pencucian, atau konsultasikan pesanan Anda di sini.
                    </p>
                  </div>
                ) : (
                  messages.map((msg, idx) => {
                    const isMe = msg.sender_id === currentUser?.id;
                    const prevMsg = idx > 0 ? messages[idx - 1] : null;
                    const isDifferentDay =
                      !prevMsg ||
                      new Date(prevMsg.created_at).toDateString() !==
                        new Date(msg.created_at).toDateString();

                    return (
                      <React.Fragment key={msg.id}>
                        {/* Date Divider */}
                        {isDifferentDay && (
                          <div className="flex justify-center my-3 select-none">
                            <span className="text-[10px] font-semibold text-slate-500 bg-white border border-slate-200/90 px-3 py-1 rounded-full shadow-2xs">
                              {formatDate(msg.created_at, 'DD MMMM YYYY')}
                            </span>
                          </div>
                        )}

                        <div className={cn('flex flex-col', isMe ? 'items-end' : 'items-start')}>
                          <div
                            className={cn(
                              'max-w-[85%] sm:max-w-[70%] p-3.5 sm:p-4 rounded-2xl text-xs space-y-2 shadow-2xs',
                              isMe
                                ? 'bg-navy-700 text-white rounded-tr-xs'
                                : 'bg-white text-navy-950 border border-slate-200/90 rounded-tl-xs'
                            )}
                          >
                            {/* Sender Name if incoming */}
                            {!isMe && msg.sender_name && (
                              <span className="font-bold text-[11px] text-navy-800 block">
                                {msg.sender_name}
                              </span>
                            )}

                            {/* Image Attachment */}
                            {msg.attachment_url && (
                              <div className="rounded-xl overflow-hidden border border-white/20 my-1">
                                {/* eslint-disable-next-line @next/next/no-img-element */}
                                <img
                                  src={msg.attachment_url}
                                  alt="Lampiran foto"
                                  className="w-full max-h-60 object-cover rounded-xl hover:opacity-95 transition-opacity cursor-pointer"
                                  onClick={() => window.open(msg.attachment_url || '', '_blank')}
                                />
                              </div>
                            )}

                            {/* Message Text Body */}
                            {msg.body && msg.body !== '[Foto]' && (
                              <p className="leading-relaxed whitespace-pre-wrap font-normal">{msg.body}</p>
                            )}

                            {/* Timestamp & Read Indicator */}
                            <div
                              className={cn(
                                'flex items-center justify-end gap-1.5 text-[10px] pt-0.5 select-none',
                                isMe ? 'text-sky-200' : 'text-slate-400'
                              )}
                            >
                              <span className="tabular-nums font-medium">{formatDate(msg.created_at, 'HH:mm')}</span>
                              {isMe && (
                                <span>
                                  {msg.is_read ? (
                                    <CheckCheck className="w-3.5 h-3.5 text-sky-300" strokeWidth={2} />
                                  ) : (
                                    <Check className="w-3.5 h-3.5 text-sky-300/80" strokeWidth={1.75} />
                                  )}
                                </span>
                              )}
                            </div>
                          </div>
                        </div>
                      </React.Fragment>
                    );
                  })
                )}
                <div ref={messagesEndRef} />
              </div>
            )}

            {/* Image Preview Bar before sending */}
            {imagePreview && (
              <div className="px-4 py-2.5 bg-white border-t border-slate-100 flex items-center justify-between shadow-xs">
                <div className="flex items-center gap-2.5">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img src={imagePreview} alt="Preview" className="w-10 h-10 rounded-xl object-cover border border-slate-200" />
                  <div className="text-[11px] truncate max-w-[200px]">
                    <span className="font-semibold text-navy-900 block truncate">Foto terpilih</span>
                    <span className="text-slate-500 truncate block">{selectedImage?.name}</span>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={removeSelectedImage}
                  className="p-1.5 rounded-xl hover:bg-slate-100 text-slate-500 hover:text-navy-900 transition-colors cursor-pointer"
                  title="Hapus foto"
                >
                  <X className="w-4 h-4" strokeWidth={1.75} />
                </button>
              </div>
            )}

            {/* Chat Input Bar */}
            <form onSubmit={handleSendMessage} className="p-3 sm:p-4 bg-white border-t border-navy-100/80 flex items-center gap-2">
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
                className="w-11 h-11 rounded-xl bg-navy-50 hover:bg-navy-100 text-navy-800 border border-navy-200/80 flex items-center justify-center transition-all shrink-0 min-h-[44px] cursor-pointer shadow-2xs disabled:opacity-50"
                title="Kirim foto"
              >
                <Paperclip className="w-4 h-4 text-navy-700" strokeWidth={1.75} />
              </button>

              <input
                type="text"
                value={inputText}
                onChange={(e) => setInputText(e.target.value)}
                placeholder="Ketik pesan untuk toko laundry..."
                disabled={sending}
                className="h-11 px-4 text-xs sm:text-sm font-medium text-navy-900 placeholder:text-slate-400 bg-slate-50/70 border border-slate-200 rounded-xl focus:border-navy-700 focus:ring-2 focus:ring-navy-700/15 focus:outline-hidden transition-all flex-1 min-h-[44px]"
              />

              <button
                type="submit"
                disabled={sending || (!inputText.trim() && !selectedImage)}
                className="w-11 h-11 rounded-xl bg-navy-700 hover:bg-navy-800 active:scale-95 text-white flex items-center justify-center transition-all shrink-0 min-h-[44px] shadow-2xs disabled:opacity-40 cursor-pointer"
                title="Kirim pesan"
              >
                <Send className="w-4 h-4" strokeWidth={1.75} />
              </button>
            </form>
          </div>
        </div>
      </div>
    </PublicLayout>
  );
}
