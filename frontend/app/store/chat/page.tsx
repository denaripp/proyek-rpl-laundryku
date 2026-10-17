'use client';

import React, { useState, useEffect, useRef } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import {
  Card,
  Badge,
  Button,
  Input,
  Skeleton,
  ErrorState,
  EmptyState,
} from '@/components/ui';
import {
  getConversations,
  getConversationDetail,
  sendChatMessage,
  pollChatMessages,
  ConversationItem,
  ChatMessageItem,
} from '@/lib/chat';
import { getStoredUser, logout, AuthUser, LOGIN_PATH } from '@/lib/auth';
import { formatDate, cn } from '@/lib/format';
import {
  MessageSquare,
  User,
  ShoppingBag,
  Send,
  Paperclip,
  CheckCheck,
  Check,
  RotateCw,
  Clock,
  Sparkles,
  X,
  Phone,
} from 'lucide-react';

export default function StoreChatPage() {
  const router = useRouter();
  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);
  const [conversations, setConversations] = useState<ConversationItem[]>([]);
  const [selectedConvId, setSelectedConvId] = useState<string | null>(null);
  const [selectedConv, setSelectedConv] = useState<ConversationItem | null>(null);
  const [messages, setMessages] = useState<ChatMessageItem[]>([]);

  const [loadingList, setLoadingList] = useState<boolean>(true);
  const [loadingChat, setLoadingChat] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  // Input states
  const [inputText, setInputText] = useState<string>('');
  const [selectedImage, setSelectedImage] = useState<File | null>(null);
  const [imagePreview, setImagePreview] = useState<string | null>(null);
  const [sending, setSending] = useState<boolean>(false);

  const messagesEndRef = useRef<HTMLDivElement>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  // Load conversation list
  const loadConversations = async (autoSelectFirst = false) => {
    setLoadingList(true);
    setError(null);
    try {
      const user = getStoredUser();
      setCurrentUser(user);

      const res = await getConversations('store');
      setConversations(res || []);

      if (autoSelectFirst && res && res.length > 0 && !selectedConvId) {
        selectConversation(res[0].id);
      }
    } catch (err: unknown) {
      const errObj = err as { response?: { data?: { error?: { message?: string } } }; message?: string };
      setError(errObj.response?.data?.error?.message || errObj.message || 'Gagal memuat daftar chat.');
    } finally {
      setLoadingList(false);
    }
  };

  // Select a conversation to chat
  const selectConversation = async (convId: string) => {
    setSelectedConvId(convId);
    setLoadingChat(true);
    try {
      const res = await getConversationDetail(convId);
      setSelectedConv(res.conversation);
      setMessages(res.messages || []);

      // Update unread count in list locally
      setConversations((prev) =>
        prev.map((c) => (c.id === convId ? { ...c, unread_count: 0 } : c))
      );

      if (typeof window !== 'undefined') {
        window.dispatchEvent(new Event('laundryku_chat_read'));
      }
    } catch (err: unknown) {
      console.error(err);
    } finally {
      setLoadingChat(false);
    }
  };

  useEffect(() => {
    loadConversations(true);
  }, []);

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  // Polling for selected chat
  useEffect(() => {
    if (!selectedConvId || loadingChat) return;

    const interval = setInterval(async () => {
      try {
        const lastMsg = messages.length > 0 ? messages[messages.length - 1] : null;
        const newMsgs = await pollChatMessages(selectedConvId, lastMsg ? lastMsg.created_at : undefined);

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
        // Ignore polling error
      }
    }, 3000);

    return () => clearInterval(interval);
  }, [selectedConvId, messages, loadingChat]);

  const handleLogout = async () => {
    await logout();
    router.push(LOGIN_PATH);
  };

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

  const handleSendMessage = async (e?: React.FormEvent) => {
    if (e) e.preventDefault();
    if (!selectedConvId || (!inputText.trim() && !selectedImage)) return;

    setSending(true);
    try {
      const newMsg = await sendChatMessage(selectedConvId, {
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
    <DashboardLayout
      userRole="store_admin"
      userName={currentUser?.name || 'Admin Toko'}
      userEmail={currentUser?.email || 'admin@toko.id'}
      onLogout={handleLogout}
    >
      <div className="space-y-6">
        {/* Header Title */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="font-heading font-extrabold text-2xl text-navy-900 flex items-center gap-2.5">
              <MessageSquare className="w-6 h-6 text-teal-600" />
              <span>Chat Pelanggan Outlet</span>
            </h1>
            <p className="text-xs text-slate-500 mt-0.5">
              Komunikasi langsung dengan customer untuk konfirmasi noda pakaian, penjemputan, atau instruksi khusus.
            </p>
          </div>

          <Button
            variant="outline"
            onClick={() => loadConversations(false)}
            disabled={loadingList}
            className="rounded-xl text-xs font-bold min-h-[44px]"
          >
            <RotateCw className={cn('w-4 h-4 mr-2', loadingList && 'animate-spin')} />
            <span>Segarkan</span>
          </Button>
        </div>

        {/* 2-Column Chat Layout */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 h-[72vh]">
          {/* Left Column: Conversations List (4 cols) */}
          <Card
            variant="bento"
            className="lg:col-span-4 p-4 flex flex-col h-full overflow-hidden bg-white shadow-sm"
          >
            <div className="pb-3 mb-3 border-b border-slate-100 flex items-center justify-between">
              <span className="font-heading font-bold text-sm text-navy-900">Daftar Percakapan</span>
              <Badge variant="secondary" className="text-[10px]">
                {conversations.length} Chat
              </Badge>
            </div>

            {loadingList && (
              <div className="space-y-3 p-2">
                <Skeleton className="h-16 rounded-2xl" />
                <Skeleton className="h-16 rounded-2xl" />
                <Skeleton className="h-16 rounded-2xl" />
              </div>
            )}

            {!loadingList && conversations.length === 0 && (
              <div className="flex-1 flex flex-col items-center justify-center text-center p-6 text-slate-400">
                <MessageSquare className="w-8 h-8 text-slate-300 mb-2" />
                <span className="text-xs font-semibold text-slate-600">Belum ada chat masuk</span>
                <span className="text-[11px] text-slate-400 mt-0.5">
                  Percakapan dari customer akan muncul di sini.
                </span>
              </div>
            )}

            {!loadingList && conversations.length > 0 && (
              <div className="flex-1 overflow-y-auto space-y-2 pr-1">
                {conversations.map((conv) => {
                  const isSelected = selectedConvId === conv.id;

                  return (
                    <button
                      key={conv.id}
                      type="button"
                      onClick={() => selectConversation(conv.id)}
                      className={cn(
                        'w-full p-3 rounded-2xl text-left transition-all flex items-start justify-between gap-3 min-h-[44px]',
                        isSelected
                          ? 'bg-sky-50 border border-teal-400 ring-2 ring-teal-100'
                          : 'bg-slate-50/60 hover:bg-slate-100 border border-transparent'
                      )}
                    >
                      <div className="flex items-start gap-3 min-w-0">
                        <div className="w-10 h-10 rounded-xl bg-teal-100 text-teal-700 flex items-center justify-center shrink-0 font-bold">
                          <User className="w-5 h-5" />
                        </div>
                        <div className="min-w-0">
                          <div className="flex items-center gap-1.5 mb-0.5">
                            <span className="font-bold text-xs text-navy-900 truncate block">
                              {conv.other_party_name}
                            </span>
                            {conv.order_number && (
                              <span className="text-[10px] font-mono text-slate-500 bg-white px-1.5 py-0.2 rounded border border-slate-200">
                                #{conv.order_number.slice(-4)}
                              </span>
                            )}
                          </div>
                          <p className="text-[11px] text-slate-500 truncate">
                            {conv.last_message?.message_type === 'image'
                              ? '📷 Mengirimkan foto'
                              : conv.last_message?.body || 'Belum ada pesan.'}
                          </p>
                        </div>
                      </div>

                      <div className="flex flex-col items-end gap-1 shrink-0">
                        <span className="text-[10px] text-slate-400">
                          {formatDate(conv.last_message_at || conv.created_at, 'HH:mm')}
                        </span>
                        {conv.unread_count > 0 && (
                          <span className="min-w-[20px] h-5 px-1.5 rounded-full bg-teal-500 text-navy-900 text-[10px] font-black flex items-center justify-center shadow-xs animate-pulse">
                            {conv.unread_count > 9 ? '9+' : conv.unread_count}
                          </span>
                        )}
                      </div>
                    </button>
                  );
                })}
              </div>
            )}
          </Card>

          {/* Right Column: Active Chat Feed (8 cols) */}
          <Card
            variant="default"
            className="lg:col-span-8 bg-slate-50/80 rounded-3xl border border-slate-200 shadow-sm flex flex-col h-full overflow-hidden"
          >
            {/* When No Conversation Selected */}
            {!selectedConvId && (
              <div className="flex-1 flex flex-col items-center justify-center text-center p-8 text-slate-400">
                <div className="w-14 h-14 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center mb-3">
                  <Sparkles className="w-7 h-7" />
                </div>
                <h3 className="font-heading font-bold text-base text-navy-900">
                  Pilih Percakapan
                </h3>
                <p className="text-xs text-slate-500 mt-1 max-w-sm">
                  Pilih salah satu customer dari daftar di sebelah kiri untuk melihat dan membalas pesan.
                </p>
              </div>
            )}

            {/* When Conversation Selected */}
            {selectedConvId && (
              <>
                {/* Chat Top Header */}
                <div className="p-4 bg-white border-b border-slate-200 flex items-center justify-between gap-4">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-xl bg-teal-500/15 border border-teal-500/30 flex items-center justify-center text-teal-700 shrink-0 font-bold">
                      <User className="w-5 h-5" />
                    </div>
                    <div>
                      <h2 className="font-heading font-bold text-sm text-navy-900">
                        {selectedConv?.other_party_name || 'Pelanggan'}
                      </h2>
                      <span className="text-[11px] text-teal-700 font-semibold block">
                        Pelanggan Aktif
                      </span>
                    </div>
                  </div>

                  {selectedConv?.order_id && (
                    <Link
                      href={`/store/orders/${selectedConv.order_id}`}
                      className="inline-flex items-center gap-1.5 text-xs font-bold text-navy-900 bg-sky-50 px-3 py-1.5 rounded-xl border border-sky-200 hover:bg-sky-100 transition-colors"
                    >
                      <ShoppingBag className="w-3.5 h-3.5 text-teal-600" />
                      <span>Order: {selectedConv.order_number}</span>
                    </Link>
                  )}
                </div>

                {/* Messages Feed */}
                <div className="flex-1 p-4 sm:p-6 overflow-y-auto space-y-4">
                  {loadingChat ? (
                    <div className="space-y-3">
                      <Skeleton className="h-12 w-2/3 rounded-2xl" />
                      <Skeleton className="h-16 w-1/2 ml-auto rounded-2xl" />
                    </div>
                  ) : messages.length === 0 ? (
                    <div className="h-full flex flex-col items-center justify-center text-center p-6 text-slate-400">
                      <p className="text-xs text-slate-500">Belum ada riwayat pesan dalam percakapan ini.</p>
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
                            {!isMe && msg.sender_name && (
                              <span className="font-bold text-[11px] text-teal-600 block">
                                {msg.sender_name}
                              </span>
                            )}

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

                            {msg.body && msg.body !== '[Foto]' && (
                              <p className="leading-relaxed whitespace-pre-wrap">{msg.body}</p>
                            )}

                            <div className="flex items-center justify-end gap-1.5 text-[10px] pt-1 text-slate-400">
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

                {/* Image Preview Bar */}
                {imagePreview && (
                  <div className="px-4 py-2 bg-slate-100 border-t border-slate-200 flex items-center justify-between">
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

                {/* Input Bar */}
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
                    placeholder="Balas pesan customer..."
                    disabled={sending}
                    className="flex-1 text-xs"
                  />

                  <Button
                    type="submit"
                    disabled={sending || (!inputText.trim() && !selectedImage)}
                    className="bg-navy-900 hover:bg-navy-800 text-white font-bold rounded-2xl px-5 min-h-[44px] shrink-0"
                  >
                    <Send className="w-4 h-4 text-teal-400" />
                  </Button>
                </form>
              </>
            )}
          </Card>
        </div>
      </div>
    </DashboardLayout>
  );
}
