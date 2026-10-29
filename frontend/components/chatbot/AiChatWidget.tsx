'use client';

import React, { useState, useEffect, useRef } from 'react';
import Link from 'next/link';
import {
  Send,
  Trash2,
  X,
  AlertCircle,
  RotateCcw,
  ExternalLink,
  Info,
} from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Skeleton } from '@/components/ui/Skeleton';
import { useToast } from '@/components/ui/Toast';
import { cn } from '@/lib/format';
import { isAuthenticated, clearAuthSession, getStoredUser, AuthUser } from '@/lib/auth';
import {
  ChatbotMessageItem,
  ChatbotSessionItem,
  getChatbotSessions,
  createChatbotSession,
  getChatbotSessionDetail,
  sendChatbotMessage,
  deleteChatbotSession,
  guestAskChatbot,
} from '@/lib/chatbot';

/**
 * Friendly Soap Bubble Mascot SVG Icon for the LaundryKu AI Assistant (Concept 1)
 * Clean vector illustration of a cheerful soap bubble with sparkle reflection and smile.
 */
export const AiAssistantIcon = ({
  className = 'w-4 h-4',
  strokeWidth = 1.75,
}: {
  className?: string;
  strokeWidth?: number;
}) => (
  <svg
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    strokeWidth={strokeWidth}
    strokeLinecap="round"
    strokeLinejoin="round"
    className={className}
    aria-hidden="true"
  >
    {/* Friendly Soap Bubble Circle */}
    <circle cx="12" cy="12" r="9" />
    {/* Soap Bubble Shine Highlight */}
    <path d="M8 7.5a4.5 4.5 0 0 1 4.5 -1.5" strokeLinecap="round" />
    {/* Friendly Calm Eyes */}
    <circle cx="9.5" cy="12.5" r="1" fill="currentColor" stroke="none" />
    <circle cx="14.5" cy="12.5" r="1" fill="currentColor" stroke="none" />
    {/* Gentle Smile */}
    <path d="M10 15a2.5 2.5 0 0 0 4 0" strokeLinecap="round" />
  </svg>
);

export const AiChatWidget = () => {
  const { success: toastSuccess, info: toastInfo, error: toastError } = useToast();
  const [isOpen, setIsOpen] = useState(false);
  const [isAuth, setIsAuth] = useState(false);
  const [currentSession, setCurrentSession] = useState<ChatbotSessionItem | null>(null);
  const [messages, setMessages] = useState<ChatbotMessageItem[]>([]);
  const [inputText, setInputText] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [isInitializing, setIsInitializing] = useState(false);
  const [hasError, setHasError] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');
  const [confirmClearOpen, setConfirmClearOpen] = useState(false);

  const messagesEndRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  // Auto-scroll to latest message
  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(() => {
    if (isOpen) {
      scrollToBottom();
    }
  }, [messages, isLoading, isOpen]);

  const activeUserIdRef = useRef<string | null>(null);

  // Check auth status on mount & listen to auth updates
  useEffect(() => {
    const handleAuthChange = (event?: Event) => {
      const customEvent = event as CustomEvent<AuthUser | null> | undefined;
      const user = customEvent?.detail !== undefined ? customEvent.detail : getStoredUser();
      const currentUserId = user?.id ? String(user.id) : null;
      const authed = isAuthenticated() && !!currentUserId;
      setIsAuth(authed);

      // Whenever auth state changes or the active user changes, immediately clear chat state
      if (!authed || currentUserId !== activeUserIdRef.current) {
        activeUserIdRef.current = currentUserId;
        setCurrentSession(null);
        setMessages([]);
        setHasError(false);
        setErrorMessage('');
      }
    };

    handleAuthChange();
    window.addEventListener('auth:user-updated', handleAuthChange);
    return () => {
      window.removeEventListener('auth:user-updated', handleAuthChange);
    };
  }, []);

  // Initialize or restore active session when widget is opened
  useEffect(() => {
    if (!isOpen) return;

    const initChat = async () => {
      const loggedIn = isAuthenticated();
      setIsAuth(loggedIn);

      // Only fetch sessions if user is logged in
      if (!loggedIn) {
        setIsInitializing(false);
        setCurrentSession(null);
        return;
      }

      setIsInitializing(true);
      setHasError(false);
      try {
        const sessions = await getChatbotSessions();
        if (sessions.length > 0) {
          const latestSession = sessions[0];
          const detail = await getChatbotSessionDetail(latestSession.id);
          setCurrentSession(detail);
          setMessages(detail.messages || []);
        } else {
          const newSession = await createChatbotSession({
            title: 'Percakapan Asisten AI',
          });
          setCurrentSession(newSession);
          setMessages([]);
        }
      } catch (err: unknown) {
        const status = (err as { status?: number })?.status;
        const msg = (err as Error)?.message || '';
        const code = (err as { code?: string })?.code;
        const is401 = status === 401 || code === 'UNAUTHENTICATED' || msg.includes('Unauthenticated');

        if (is401) {
          // Token expired or unauthenticated: reset chat session state
          setIsAuth(false);
          setCurrentSession(null);
          setMessages([]);
        } else {
          console.error('Failed to initialize chatbot session:', err);
          setHasError(true);
          setErrorMessage('Gagal memuat riwayat percakapan. Silakan coba kembali.');
        }
      } finally {
        setIsInitializing(false);
      }
    };

    if (!currentSession && messages.length === 0) {
      initChat();
    }
  }, [isOpen, currentSession, messages.length]);

  // Focus input when opened
  useEffect(() => {
    if (isOpen && !isInitializing) {
      setTimeout(() => {
        inputRef.current?.focus();
      }, 100);
    }
  }, [isOpen, isInitializing]);

  // Handle sending a message
  const handleSendMessage = async (textToSend?: string) => {
    const text = (textToSend || inputText).trim();
    if (!text || isLoading) return;

    setInputText('');
    setHasError(false);

    // Optimistic user message
    const tempUserMsg: ChatbotMessageItem = {
      id: 'temp-user-' + Date.now(),
      session_id: currentSession?.id || 'guest',
      role: 'user',
      content: text,
      created_at: new Date().toISOString(),
    };

    setMessages((prev) => [...prev, tempUserMsg]);
    setIsLoading(true);

    try {
      if (isAuth && currentSession) {
        // Authenticated message sending
        const response = await sendChatbotMessage(currentSession.id, text);
        setMessages((prev) => [
          ...prev.filter((m) => m.id !== tempUserMsg.id),
          response.user_message,
          response.assistant_message,
        ]);
      } else if (isAuth && !currentSession) {
        // Authenticated but no session yet
        const newSession = await createChatbotSession({ message: text });
        setCurrentSession(newSession);
        setMessages(newSession.messages || []);
      } else {
        // Guest mode asking with prior conversation history
        const priorHistory = messages
          .filter((m) => m.id !== tempUserMsg.id)
          .map((m) => ({ role: m.role, content: m.content }));
        const guestResult = await guestAskChatbot(text, priorHistory);
        const assistantMsg: ChatbotMessageItem = {
          id: 'temp-assistant-' + Date.now(),
          session_id: 'guest',
          role: 'assistant',
          content: guestResult.answer,
          metadata: {
            is_fallback: guestResult.is_fallback,
            provider: guestResult.provider,
          },
          created_at: new Date().toISOString(),
        };
        setMessages((prev) => [...prev, assistantMsg]);
      }
    } catch (err: unknown) {
      const status = (err as { status?: number })?.status;
      const msg = (err as Error)?.message || '';
      const code = (err as { code?: string })?.code;
      const is401 = status === 401 || code === 'UNAUTHENTICATED' || msg.includes('Unauthenticated');
      const isNotFound = status === 404 || code === 'SESSION_NOT_FOUND';

      if (is401) {
        setIsAuth(false);
        setCurrentSession(null);
        setMessages([]);
      } else if (isNotFound) {
        setCurrentSession(null);
        setMessages([]);
      } else {
        console.error('Chatbot error:', err);
      }
      const fallbackAssistantMsg: ChatbotMessageItem = {
        id: 'fallback-' + Date.now(),
        session_id: currentSession?.id || 'guest',
        role: 'assistant',
        content:
          'Maaf, Asisten AI sedang mengalami kendala jaringan. Anda dapat melihat status pesanan langsung di menu **Pesanan Saya** atau menghubungi pihak toko via **Chat Toko**.',
        metadata: { is_fallback: true },
        created_at: new Date().toISOString(),
      };
      setMessages((prev) => [...prev, fallbackAssistantMsg]);
      toastInfo(
        'Koneksi Dialihkan',
        'Pertanyaan Anda dialihkan ke sistem bantuan darurat.'
      );
    } finally {
      setIsLoading(false);
    }
  };

  // Handle clearing chat history
  const handleClearChat = async () => {
    if (currentSession && isAuth) {
      try {
        await deleteChatbotSession(currentSession.id);
        setCurrentSession(null);
        setMessages([]);
        toastSuccess(
          'Percakapan Direset',
          'Riwayat percakapan dengan Asisten AI telah dibersihkan.'
        );
      } catch (err: unknown) {
        const status = (err as { status?: number })?.status;
        const code =
          (err as { code?: string })?.code ||
          (err as { responseData?: { error?: { code?: string } } })?.responseData?.error?.code;
        const msg = (err as Error)?.message || '';

        const isSessionNotFound =
          status === 404 ||
          code === 'SESSION_NOT_FOUND' ||
          msg.includes('SESSION_NOT_FOUND') ||
          msg.includes('Sesi percakapan AI tidak ditemukan');

        if (isSessionNotFound) {
          // Jika sesi tidak ditemukan di server (404 / SESSION_NOT_FOUND), anggap sukses
          setCurrentSession(null);
          setMessages([]);
          toastSuccess(
            'Percakapan Direset',
            'Riwayat percakapan dengan Asisten AI telah dibersihkan.'
          );
        } else {
          console.error('Failed to clear session:', err);
          toastError(
            'Gagal Menghapus',
            'Terjadi kesalahan saat menghapus sesi percakapan.'
          );
        }
      }
    } else {
      setMessages([]);
      toastSuccess(
        'Percakapan Direset',
        'Riwayat percakapan telah dibersihkan.'
      );
    }
    setConfirmClearOpen(false);
  };

  // Clean Markdown & text renderer
  const renderFormattedText = (text: string) => {
    const lines = text.split('\n');
    return lines.map((line, idx) => {
      // Bold formatter
      const parts = line.split(/(\*\*.*?\*\*)/g);
      const formattedLine = parts.map((part, pIdx) => {
        if (part.startsWith('**') && part.endsWith('**')) {
          return (
            <strong key={pIdx} className="font-semibold text-slate-900">
              {part.slice(2, -2)}
            </strong>
          );
        }
        return part;
      });

      if (line.startsWith('•') || line.startsWith('-')) {
        return (
          <div key={idx} className="flex items-start gap-2 my-0.5 ml-1">
            <span className="text-slate-400 select-none">•</span>
            <span className="flex-1">{formattedLine}</span>
          </div>
        );
      }

      if (/^\d+\./.test(line)) {
        return (
          <div key={idx} className="my-0.5 ml-1 leading-relaxed">
            {formattedLine}
          </div>
        );
      }

      return (
        <p key={idx} className={cn(line === '' ? 'h-1.5' : 'my-0.5 leading-relaxed')}>
          {formattedLine}
        </p>
      );
    });
  };

  return (
    <>
      {/* 1. Floating AI Assistant Chat Button (Soap Bubble Concept) */}
      <div className="fixed bottom-20 right-4 sm:bottom-6 sm:right-6 z-40">
        {!isOpen && (
          <button
            onClick={() => setIsOpen(true)}
            className="group flex items-center gap-2.5 bg-navy-700 hover:bg-navy-800 text-white px-4 py-2.5 rounded-2xl shadow-lg hover:shadow-xl border border-white/20 text-xs font-semibold transition-all duration-200 active:scale-95 cursor-pointer focus:outline-none focus:ring-2 focus:ring-navy-400 focus:ring-offset-2 motion-safe:animate-gentle-float"
            aria-label="Buka Asisten LaundryKu"
          >
            {/* Friendly Mascot Squircle Icon */}
            <div className="w-6 h-6 rounded-xl bg-navy-800/90 border border-white/10 flex items-center justify-center text-sky-300 group-hover:text-white transition-colors">
              <AiAssistantIcon className="w-4 h-4 group-hover:scale-110 transition-transform duration-200" />
            </div>

            <span className="tracking-tight font-bold">Asisten LaundryKu</span>

            {/* Active Online Status Indicator with soft pulse */}
            <span className="relative flex h-2 w-2" title="Online" aria-hidden="true">
              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
              <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-400" />
            </span>
          </button>
        )}
      </div>

      {/* 2. Modern Soap-Bubble Chat Window */}
      {isOpen && (
        <div
          role="dialog"
          aria-label="Asisten LaundryKu"
          className="fixed bottom-20 right-3 left-3 sm:left-auto sm:right-6 sm:bottom-6 sm:w-[390px] h-[540px] max-h-[82vh] bg-white rounded-3xl shadow-2xl border border-navy-200/90 z-50 flex flex-col overflow-hidden font-sans"
        >
          {/* Header Panel (Navy-900 with Wave Divider) */}
          <div className="bg-navy-900 text-white px-4 pt-3.5 pb-2.5 relative select-none">
            {/* Top Row: Mascot + Titles + Actions */}
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2.5">
                <div className="w-8 h-8 rounded-xl bg-navy-800 border border-navy-700 flex items-center justify-center text-sky-300 shadow-2xs">
                  <AiAssistantIcon className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="font-extrabold text-xs text-white leading-tight flex items-center gap-1.5">
                    <span>Asisten LaundryKu</span>
                    <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 shrink-0" />
                  </h3>
                  <span className="text-[11px] text-navy-200/80 font-normal">
                    Siap bantu seputar pesanan &amp; layanan
                  </span>
                </div>
              </div>

              {/* Header Actions */}
              <div className="flex items-center gap-1">
                {messages.length > 0 && (
                  <button
                    onClick={() => setConfirmClearOpen(true)}
                    className="p-1.5 text-navy-200 hover:text-rose-300 hover:bg-navy-800 rounded-lg transition-colors cursor-pointer"
                    title="Hapus percakapan ini?"
                    aria-label="Hapus percakapan ini?"
                  >
                    <Trash2 className="w-3.5 h-3.5" strokeWidth={1.75} />
                  </button>
                )}
                <button
                  onClick={() => setIsOpen(false)}
                  className="p-1.5 text-navy-200 hover:text-white hover:bg-navy-800 rounded-lg transition-colors cursor-pointer"
                  title="Tutup Bantuan"
                  aria-label="Tutup Bantuan"
                >
                  <X className="w-4 h-4" strokeWidth={1.75} />
                </button>
              </div>
            </div>

            {/* Wave SVG Divider at bottom of Header (aria-hidden="true") */}
            <div className="absolute -bottom-2 left-0 right-0 pointer-events-none overflow-hidden leading-none z-10" aria-hidden="true">
              <svg
                viewBox="0 0 390 12"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
                className="w-full h-2 text-white preserve-3d"
              >
                <path
                  d="M0 0C65 8 130 0 195 4C260 8 325 2 390 0V12H0V0Z"
                  fill="currentColor"
                />
              </svg>
            </div>
          </div>

          {/* Chat Messages Container */}
          <div className="flex-1 p-4 overflow-y-auto space-y-3 bg-navy-50/40 relative">
            {/* Subtle Soap Bubble Background Accent (aria-hidden="true") */}
            <div className="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
              <div className="absolute -top-6 -right-6 w-24 h-24 rounded-full border border-sky-300/20 bg-sky-200/10" />
              <div className="absolute bottom-10 -left-6 w-20 h-20 rounded-full border border-sky-300/15 bg-sky-200/10" />
            </div>

            {isInitializing ? (
              <div className="space-y-3 p-2 relative z-10">
                <Skeleton className="h-14 w-3/4 rounded-2xl" />
                <Skeleton className="h-16 w-5/6 rounded-2xl" />
                <Skeleton className="h-12 w-2/3 rounded-2xl" />
              </div>
            ) : hasError ? (
              <div className="p-6 text-center space-y-3 my-auto relative z-10">
                <div className="w-10 h-10 mx-auto rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center">
                  <AlertCircle className="w-5 h-5" />
                </div>
                <p className="text-xs font-medium text-slate-700">{errorMessage}</p>
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => setIsOpen(false)}
                  className="rounded-lg text-xs"
                >
                  <RotateCcw className="w-3.5 h-3.5 mr-1.5" />
                  Tutup &amp; Coba Lagi
                </Button>
              </div>
            ) : messages.length === 0 ? (
              /* Clean & Friendly Welcome State */
              <div className="py-12 px-4 text-center my-auto space-y-4 relative z-10">
                <div className="w-14 h-14 mx-auto rounded-2xl bg-navy-100/70 border border-navy-200 text-navy-700 flex items-center justify-center shadow-xs">
                  <AiAssistantIcon className="w-7 h-7" strokeWidth={1.5} />
                </div>

                <div className="space-y-1.5 max-w-[280px] mx-auto">
                  <h4 className="font-extrabold text-base text-navy-900 tracking-tight leading-snug">
                    Halo! Ada yang bisa kami bantu?
                  </h4>
                  <p className="text-xs text-slate-600 leading-relaxed font-normal">
                    Tanyakan cara pesan, cara bayar, atau info mitra laundry.
                  </p>
                </div>
              </div>
            ) : (
              /* Chat Conversation Messages */
              <div className="space-y-3 relative z-10">
                {messages.map((msg) => {
                  const isUser = msg.role === 'user';
                  return (
                    <div
                      key={msg.id}
                      className={cn(
                        'flex flex-col',
                        isUser ? 'items-end' : 'items-start'
                      )}
                    >
                      <div
                        className={cn(
                          'p-3 rounded-2xl text-xs sm:text-[13px] leading-relaxed max-w-[88%] break-words',
                          isUser
                            ? 'bg-navy-700 text-white rounded-br-xs shadow-2xs'
                            : 'bg-white border border-navy-100 text-navy-900 rounded-bl-xs shadow-2xs'
                        )}
                      >
                        {!isUser && (
                          <div className="flex items-center gap-1.5 mb-1 pb-1 border-b border-slate-100 text-[10px] text-navy-700 font-bold tracking-tight">
                            <AiAssistantIcon className="w-3 h-3 text-navy-700" />
                            <span>Asisten LaundryKu</span>
                            {msg.metadata?.is_fallback && (
                              <Badge
                                variant="outline"
                                className="text-[9px] py-0 px-1 ml-auto font-normal text-slate-500 border-slate-200"
                              >
                                Panduan
                              </Badge>
                            )}
                          </div>
                        )}
                        <div className="space-y-0.5">
                          {isUser ? msg.content : renderFormattedText(msg.content)}
                        </div>
                      </div>
                      <span className="text-[10px] text-slate-400 px-1 mt-1 font-medium">
                        {new Date(msg.created_at).toLocaleTimeString('id-ID', {
                          hour: '2-digit',
                          minute: '2-digit',
                        })}
                      </span>
                    </div>
                  );
                })}

                {/* Soap Bubble Typing Indicator */}
                {isLoading && (
                  <div className="flex items-start gap-2">
                    <div className="bg-white border border-navy-100 px-3 py-2.5 rounded-2xl rounded-bl-xs shadow-2xs flex items-center gap-1.5">
                      <span className="w-2 h-2 rounded-full bg-navy-600 animate-bounce [animation-delay:-0.3s]" />
                      <span className="w-2 h-2 rounded-full bg-navy-600 animate-bounce [animation-delay:-0.15s]" />
                      <span className="w-2 h-2 rounded-full bg-navy-600 animate-bounce" />
                      <span className="text-xs text-slate-500 font-medium ml-1.5">
                        Sedang mengetik...
                      </span>
                    </div>
                  </div>
                )}
                <div ref={messagesEndRef} />
              </div>
            )}
          </div>

          {/* Input Form Bar */}
          <form
            onSubmit={(e) => {
              e.preventDefault();
              handleSendMessage();
            }}
            className="p-3 bg-white border-t border-navy-100 flex items-center gap-2 relative z-10"
          >
            <input
              ref={inputRef}
              type="text"
              value={inputText}
              onChange={(e) => setInputText(e.target.value)}
              placeholder="Tulis pesan atau pertanyaan..."
              disabled={isLoading}
              className="flex-1 bg-slate-50/70 hover:bg-slate-100/60 focus:bg-white border border-slate-200 focus:border-navy-700 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-navy-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-navy-700 transition-colors disabled:opacity-60 min-h-[44px]"
            />
            <Button
              type="submit"
              variant="primary"
              disabled={!inputText.trim() || isLoading}
              className="px-3.5 rounded-xl min-h-[44px] min-w-[44px] flex items-center justify-center shrink-0 bg-navy-700 hover:bg-navy-800 text-white disabled:bg-slate-100 disabled:text-slate-400 transition-colors shadow-xs active:scale-95 cursor-pointer"
              aria-label="Kirim pertanyaan"
            >
              <Send className="w-4 h-4" />
            </Button>
          </form>
        </div>
      )}

      {/* Confirm Clear History Dialog */}
      <ConfirmDialog
        isOpen={confirmClearOpen}
        onClose={() => setConfirmClearOpen(false)}
        onConfirm={handleClearChat}
        title="Hapus percakapan ini?"
        description="Pesan obrolan ini akan dihapus dan Anda bisa memulai obrolan baru."
        confirmText="Hapus"
        cancelText="Batal"
        variant="danger"
      />
    </>
  );
};
