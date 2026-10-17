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
import { isAuthenticated } from '@/lib/auth';
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
 * Distinctive, clean SVG Icon for the LaundryKu AI Assistant
 * Combines crisp message dialogue geometry with an intelligent spark symbol.
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
    {/* Clean Chat Bubble Outline */}
    <path d="M21 12c0 4.418-4.03 8-9 8a10.2 10.2 0 0 1-3.667-.678L3 21l1.678-4.333A7.54 7.54 0 0 1 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
    {/* Geometric 4-Point Concierge Spark */}
    <path
      d="M12 7.5c0 1.933-1.567 3.5-3.5 3.5 1.933 0 3.5 1.567 3.5 3.5 0-1.933 1.567-3.5 3.5-3.5-1.933 0-3.5-1.567-3.5-3.5z"
      fill="currentColor"
      stroke="none"
      opacity="0.85"
    />
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

  // Check auth status on mount
  useEffect(() => {
    const checkAuth = () => {
      setIsAuth(isAuthenticated());
    };
    checkAuth();
  }, []);

  // Initialize or restore active session when widget is opened
  useEffect(() => {
    if (!isOpen) return;

    const initChat = async () => {
      setIsInitializing(true);
      setHasError(false);
      try {
        if (isAuthenticated()) {
          setIsAuth(true);
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
        } else {
          setIsAuth(false);
        }
      } catch (err: unknown) {
        console.error('Failed to initialize chatbot session:', err);
        setHasError(true);
        setErrorMessage('Gagal memuat riwayat percakapan. Silakan coba kembali.');
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
      console.error('Chatbot error:', err);
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
      } catch (err) {
        console.error('Failed to clear session:', err);
        toastError(
          'Gagal Menghapus',
          'Terjadi kesalahan saat menghapus sesi percakapan.'
        );
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
      {/* 1. Floating AI Assistant Chat Button (Clean, Professional, Non-Slop) */}
      <div className="fixed bottom-20 right-4 sm:bottom-6 sm:right-6 z-40">
        {!isOpen && (
          <button
            onClick={() => setIsOpen(true)}
            className="group flex items-center gap-2.5 bg-[#0F172A] hover:bg-[#1E293B] text-white px-4 py-2.5 rounded-full shadow-lg hover:shadow-xl border border-slate-700/60 text-xs font-medium transition-all duration-200 active:scale-95 cursor-pointer focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2"
            aria-label="Buka Asisten Bantuan AI LaundryKu"
          >
            {/* AI Assistant Icon with subtle micro-interaction */}
            <div className="w-5 h-5 rounded-full bg-slate-800 flex items-center justify-center text-cyan-400 group-hover:text-cyan-300 transition-colors">
              <AiAssistantIcon className="w-3.5 h-3.5 group-hover:scale-110 transition-transform duration-200" />
            </div>

            <span className="tracking-tight">Asisten LaundryKu</span>

            {/* Active Online Status Indicator */}
            <span
              className="w-2 h-2 rounded-full bg-emerald-400 shrink-0"
              title="Online"
              aria-hidden="true"
            />
          </button>
        )}
      </div>

      {/* 2. Modern Minimalist Chat Window */}
      {isOpen && (
        <div
          role="dialog"
          aria-label="Asisten Bantuan LaundryKu"
          className="fixed bottom-20 right-3 left-3 sm:left-auto sm:right-6 sm:bottom-6 sm:w-[390px] h-[530px] max-h-[82vh] bg-white rounded-2xl shadow-2xl border border-slate-200/90 z-50 flex flex-col overflow-hidden font-sans"
        >
          {/* Header */}
          <div className="bg-[#0F172A] text-white px-4 py-3 flex items-center justify-between border-b border-slate-800 select-none">
            <div className="flex items-center gap-2.5">
              <div className="w-7 h-7 rounded-lg bg-slate-800/90 border border-slate-700/60 flex items-center justify-center text-cyan-400">
                <AiAssistantIcon className="w-4 h-4" />
              </div>
              <div>
                <h3 className="font-semibold text-xs text-white leading-tight flex items-center gap-1.5">
                  <span>Asisten LaundryKu</span>
                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 shrink-0" />
                </h3>
                <span className="text-[10px] text-slate-400">
                  Pusat Panduan &amp; Bantuan Layanan
                </span>
              </div>
            </div>

            {/* Header Actions */}
            <div className="flex items-center gap-1">
              {messages.length > 0 && (
                <button
                  onClick={() => setConfirmClearOpen(true)}
                  className="p-1.5 text-slate-400 hover:text-rose-300 hover:bg-slate-800/80 rounded-lg transition-colors"
                  title="Bersihkan Percakapan"
                  aria-label="Bersihkan Percakapan"
                >
                  <Trash2 className="w-3.5 h-3.5" strokeWidth={1.75} />
                </button>
              )}
              <button
                onClick={() => setIsOpen(false)}
                className="p-1.5 text-slate-400 hover:text-white hover:bg-slate-800/80 rounded-lg transition-colors"
                title="Tutup Bantuan"
                aria-label="Tutup Bantuan"
              >
                <X className="w-4 h-4" strokeWidth={1.75} />
              </button>
            </div>
          </div>


          {/* Chat Messages Container */}
          <div className="flex-1 p-4 overflow-y-auto space-y-3 bg-slate-50/40">
            {isInitializing ? (
              <div className="space-y-3 p-2">
                <Skeleton className="h-14 w-3/4 rounded-xl" />
                <Skeleton className="h-16 w-5/6 rounded-xl" />
                <Skeleton className="h-12 w-2/3 rounded-xl" />
              </div>
            ) : hasError ? (
              <div className="p-6 text-center space-y-3 my-auto">
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
              /* Clean & Focused Welcome State */
              <div className="py-12 px-4 text-center my-auto space-y-4">
                <div className="w-12 h-12 mx-auto rounded-2xl bg-slate-100 border border-slate-200/80 text-[#0E7490] flex items-center justify-center">
                  <AiAssistantIcon className="w-6 h-6" strokeWidth={1.5} />
                </div>

                <div className="space-y-2 max-w-[300px] mx-auto">
                  <h4 className="font-semibold text-base sm:text-lg text-slate-900 tracking-tight leading-snug">
                    Ada yang bisa saya bantu?
                  </h4>
                  <p className="text-xs sm:text-[13px] text-slate-500 leading-relaxed font-normal">
                    Tanyakan apa saja seputar pesanan, pembayaran, atau kemitraan. Saya siap membantu Anda.
                  </p>
                </div>
              </div>
            ) : (
              /* Chat Conversation Messages */
              <>
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
                            ? 'bg-[#0F172A] text-white rounded-br-xs shadow-xs'
                            : 'bg-white border border-slate-200 text-slate-800 rounded-bl-xs shadow-xs'
                        )}
                      >
                        {!isUser && (
                          <div className="flex items-center gap-1.5 mb-1 pb-1 border-b border-slate-100 text-[10px] text-slate-500 font-semibold tracking-tight">
                            <AiAssistantIcon className="w-3 h-3 text-[#0E7490]" />
                            <span>Asisten AI</span>
                            {msg.metadata?.is_fallback && (
                              <Badge
                                variant="outline"
                                className="text-[9px] py-0 px-1 ml-auto font-normal text-slate-400 border-slate-200"
                              >
                                Panduan Sistem
                              </Badge>
                            )}
                          </div>
                        )}
                        <div className="space-y-0.5">
                          {isUser ? msg.content : renderFormattedText(msg.content)}
                        </div>
                        {!isUser && (msg.content.includes('Mau tanya soal itu?') || msg.content.includes('cuma bisa bantu soal layanan LaundryKu')) && (
                          <div className="flex flex-wrap gap-1.5 mt-2.5 pt-2 border-t border-slate-100">
                            {['Cara pesan', 'Metode pembayaran', 'Lacak cucian'].map((replyText) => (
                              <button
                                key={replyText}
                                type="button"
                                onClick={() => handleSendMessage(replyText)}
                                className="text-[11px] font-medium px-2.5 py-1 rounded-full bg-cyan-50 hover:bg-cyan-100 text-[#0E7490] border border-cyan-200 transition-colors cursor-pointer active:scale-95"
                              >
                                {replyText}
                              </button>
                            ))}
                          </div>
                        )}
                      </div>
                      <span className="text-[10px] text-slate-400 px-1 mt-1">
                        {new Date(msg.created_at).toLocaleTimeString('id-ID', {
                          hour: '2-digit',
                          minute: '2-digit',
                        })}
                      </span>
                    </div>
                  );
                })}

                {/* Subtle Typing Indicator */}
                {isLoading && (
                  <div className="flex items-start gap-2">
                    <div className="bg-white border border-slate-200 px-3 py-2 rounded-2xl rounded-bl-xs shadow-xs flex items-center gap-1.5">
                      <span className="w-1.5 h-1.5 rounded-full bg-cyan-700 animate-pulse" />
                      <span className="w-1.5 h-1.5 rounded-full bg-cyan-700 animate-pulse [animation-delay:200ms]" />
                      <span className="w-1.5 h-1.5 rounded-full bg-cyan-700 animate-pulse [animation-delay:400ms]" />
                      <span className="text-[11px] text-slate-400 font-medium ml-1.5">
                        Menyiapkan jawaban...
                      </span>
                    </div>
                  </div>
                )}
                <div ref={messagesEndRef} />
              </>
            )}
          </div>

          {/* Input Form Bar (Clean, Spacious, No Leftover Container) */}
          <form
            onSubmit={(e) => {
              e.preventDefault();
              handleSendMessage();
            }}
            className="p-3 bg-white border-t border-slate-200/90 flex items-center gap-2"
          >
            <input
              ref={inputRef}
              type="text"
              value={inputText}
              onChange={(e) => setInputText(e.target.value)}
              placeholder="Ketik pertanyaan Anda..."
              disabled={isLoading}
              className="flex-1 bg-slate-50 hover:bg-slate-100/60 focus:bg-white border border-slate-200 focus:border-slate-400 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-slate-400 transition-colors disabled:opacity-60 min-h-[44px]"
            />
            <Button
              type="submit"
              variant="primary"
              disabled={!inputText.trim() || isLoading}
              className="px-3.5 rounded-xl min-h-[44px] min-w-[44px] flex items-center justify-center shrink-0 bg-[#0E7490] hover:bg-[#155E75] text-white disabled:bg-slate-100 disabled:text-slate-400 transition-colors shadow-xs active:scale-95"
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
        title="Bersihkan Riwayat Percakapan?"
        description="Seluruh percakapan dalam sesi ini akan dibersihkan dan Anda dapat memulai sesi konsultasi baru."
        confirmText="Bersihkan"
        cancelText="Batal"
        variant="danger"
      />
    </>
  );
};
