'use client';

import React, { useState, useEffect, useCallback, useRef } from 'react';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import {
  Store,
  Package,
  Bell,
  MessageSquare,
  LogOut,
  User,
  MapPin,
  Menu,
  X,
  ChevronDown,
  Briefcase,
  Home,
} from 'lucide-react';
import { AiChatWidget } from '@/components/chatbot/AiChatWidget';
import { Avatar } from '@/components/ui/Avatar';
import { cn } from '@/lib/format';
import { useAuth } from '@/lib/AuthContext';
import { getUnreadChatCount } from '@/lib/chat';
import { notificationApi } from '@/lib/notification';
import { hasRole, hasApprovedStore, getUserDisplayContact } from '@/lib/auth';
import { usePolling } from '@/hooks/usePolling';

export interface PublicLayoutProps {
  children: React.ReactNode;
}

export const PublicLayout = ({ children }: PublicLayoutProps) => {
  const router = useRouter();
  const pathname = usePathname();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [accountDropdownOpen, setAccountDropdownOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const menuRef = useRef<HTMLDivElement>(null);

  const handleTriggerKeyDown = (e: React.KeyboardEvent<HTMLButtonElement>) => {
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      setAccountDropdownOpen(true);
      setTimeout(() => {
        if (!menuRef.current) return;
        const items = menuRef.current.querySelectorAll<HTMLElement>('[role="menuitem"]');
        if (items.length > 0) {
          if (e.key === 'ArrowUp') {
            items[items.length - 1].focus();
          } else {
            items[0].focus();
          }
        }
      }, 50);
    } else if (e.key === 'Escape' && accountDropdownOpen) {
      e.preventDefault();
      setAccountDropdownOpen(false);
      triggerRef.current?.focus();
    }
  };

  const handleMenuKeyDown = (e: React.KeyboardEvent<HTMLDivElement>) => {
    if (!menuRef.current) return;
    const items = Array.from(menuRef.current.querySelectorAll<HTMLElement>('[role="menuitem"]'));
    if (items.length === 0) return;

    const currentIndex = items.indexOf(document.activeElement as HTMLElement);

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      const nextIndex = currentIndex < items.length - 1 ? currentIndex + 1 : 0;
      items[nextIndex].focus();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      const prevIndex = currentIndex > 0 ? currentIndex - 1 : items.length - 1;
      items[prevIndex].focus();
    } else if (e.key === 'Home') {
      e.preventDefault();
      items[0].focus();
    } else if (e.key === 'End') {
      e.preventDefault();
      items[items.length - 1].focus();
    } else if (e.key === 'Escape') {
      e.preventDefault();
      setAccountDropdownOpen(false);
      triggerRef.current?.focus();
    } else if (e.key === 'Tab') {
      setAccountDropdownOpen(false);
    }
  };

  const { user, loading, isAuthenticated, logoutUser } = useAuth();
  const [unreadChatCount, setUnreadChatCount] = useState<number>(0);
  const [unreadNotifCount, setUnreadNotifCount] = useState<number>(0);

  // Close dropdown on click outside
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setAccountDropdownOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Fetch unread counters (chat & notifications)
  const fetchCounters = useCallback(async () => {
    if (!isAuthenticated) {
      setUnreadChatCount(0);
      setUnreadNotifCount(0);
      return;
    }

    try {
      const [chatCount, notifRes] = await Promise.all([
        getUnreadChatCount(),
        notificationApi.getNotifications({ unread_only: true }),
      ]);
      setUnreadChatCount(chatCount);
      setUnreadNotifCount(notifRes.data.unread_count || 0);
    } catch {
      // fallback silently
    }
  }, [isAuthenticated]);

  // 30s auto-refresh polling with visibility guard & immediate refocus refetch
  usePolling(fetchCounters, 30000, {
    enabled: isAuthenticated,
    immediate: true,
  });

  useEffect(() => {
    const handleReadEvent = () => fetchCounters();
    window.addEventListener('laundryku_chat_read', handleReadEvent);
    window.addEventListener('laundryku_auth_change', handleReadEvent);

    return () => {
      window.removeEventListener('laundryku_chat_read', handleReadEvent);
      window.removeEventListener('laundryku_auth_change', handleReadEvent);
    };
  }, [fetchCounters]);

  // Close mobile menu on route change
  useEffect(() => {
    setMobileMenuOpen(false);
    setAccountDropdownOpen(false);
  }, [pathname]);

  const isChatActive = pathname === '/chat' || pathname.startsWith('/chat/');
  const isNotifActive = pathname === '/notifications';

  const isApprovedOwner = hasRole(user, 'store_admin') || hasApprovedStore(user);
  const hasPendingOrOtherStore = !isApprovedOwner && Boolean(user?.stores && user.stores.length > 0);

  return (
    <div className="min-h-screen flex flex-col bg-white text-slate-900 font-sans pb-24 md:pb-0">
      {/* Top Header Navigation (Floating Island - Sticky top-3) */}
      <header className="sticky top-3 z-40 px-3 sm:px-6 lg:px-8 mb-3">
        <div className="max-w-7xl mx-auto bg-white/95 backdrop-blur-md border border-navy-100/90 shadow-sm rounded-2xl transition-all">
          <div className="h-16 px-4 sm:px-6 flex items-center justify-between">
            {/* Brand Wordmark Logo with Squircle & Mini Soap Bubbles */}
            <Link href={isAuthenticated ? '/stores' : '/'} className="flex items-center gap-2.5 group select-none">
              <div className="relative">
                <div className="w-9 h-9 rounded-xl bg-navy-700 group-hover:bg-navy-800 transition-colors flex items-center justify-center text-white font-extrabold text-sm tracking-wider shadow-2xs border border-navy-600/40">
                  LK
                </div>
                {/* Mini Soap Bubbles decorative accent (aria-hidden="true") */}
                <div className="absolute -top-1 -right-1 pointer-events-none" aria-hidden="true">
                  <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="8" cy="6" r="4.5" className="fill-sky-400/30 stroke-sky-400" strokeWidth="1" />
                    <circle cx="9" cy="5" r="1.2" fill="white" fillOpacity="0.8" />
                    <circle cx="3" cy="10" r="2" className="fill-sky-300/40 stroke-sky-300" strokeWidth="0.8" />
                  </svg>
                </div>
              </div>
              <span className="font-extrabold text-lg tracking-tight text-navy-900 group-hover:text-navy-700 transition-colors">
                Laundry<span className="text-navy-700">Ku</span>
              </span>
            </Link>

            {/* Desktop Navigation Links with Pill Accent */}
            <nav className="hidden md:flex items-center gap-1.5 lg:gap-2">
              {!isAuthenticated ? (
                <>
                  <Link
                    href="/"
                    className={cn(
                      'text-xs font-semibold px-3 py-1.5 rounded-xl transition-all',
                      pathname === '/'
                        ? 'bg-navy-50 text-navy-900 border border-navy-200/70 shadow-2xs'
                        : 'text-slate-600 hover:text-navy-900 hover:bg-slate-50'
                    )}
                  >
                    Beranda
                  </Link>
                  <Link
                    href="/stores"
                    className={cn(
                      'text-xs font-semibold px-3 py-1.5 rounded-xl transition-all',
                      pathname === '/stores'
                        ? 'bg-navy-50 text-navy-900 border border-navy-200/70 shadow-2xs'
                        : 'text-slate-600 hover:text-navy-900 hover:bg-slate-50'
                    )}
                  >
                    Cari Toko
                  </Link>
                  <Link
                    href="/upgrade"
                    className={cn(
                      'text-xs font-semibold px-3 py-1.5 rounded-xl transition-all',
                      pathname === '/upgrade'
                        ? 'bg-navy-50 text-navy-900 border border-navy-200/70 shadow-2xs'
                        : 'text-slate-600 hover:text-navy-900 hover:bg-slate-50'
                    )}
                  >
                    Gabung Mitra
                  </Link>
                </>
              ) : (
                <>
                  <Link
                    href="/stores"
                    className={cn(
                      'text-xs font-semibold px-3 py-1.5 rounded-xl transition-all flex items-center gap-1.5',
                      pathname === '/stores'
                        ? 'bg-navy-50 text-navy-900 border border-navy-200/70 shadow-2xs'
                        : 'text-slate-600 hover:text-navy-900 hover:bg-slate-50'
                    )}
                  >
                    <Store className="w-4 h-4 text-navy-700" strokeWidth={1.75} />
                    <span>Cari Toko</span>
                  </Link>
                  <Link
                    href="/orders"
                    className={cn(
                      'text-xs font-semibold px-3 py-1.5 rounded-xl transition-all flex items-center gap-1.5',
                      pathname === '/orders' || pathname.startsWith('/orders/')
                        ? 'bg-navy-50 text-navy-900 border border-navy-200/70 shadow-2xs'
                        : 'text-slate-600 hover:text-navy-900 hover:bg-slate-50'
                    )}
                  >
                    <Package className="w-4 h-4 text-navy-700" strokeWidth={1.75} />
                    <span>Pesanan Saya</span>
                  </Link>
                  {hasPendingOrOtherStore ? (
                    <Link
                      href="/upgrade"
                      className={cn(
                        'text-xs font-semibold px-3 py-1.5 rounded-xl transition-all flex items-center gap-1.5',
                        pathname === '/upgrade'
                          ? 'bg-navy-50 text-navy-900 border border-navy-200/70 shadow-2xs'
                          : 'text-slate-600 hover:text-navy-900 hover:bg-slate-50'
                      )}
                    >
                      <Briefcase className="w-4 h-4 text-navy-700" strokeWidth={1.75} />
                      <span>Status Pengajuan</span>
                    </Link>
                  ) : (
                    <Link
                      href="/upgrade"
                      className={cn(
                        'text-xs font-semibold px-3 py-1.5 rounded-xl transition-all flex items-center gap-1.5',
                        pathname === '/upgrade'
                          ? 'bg-navy-50 text-navy-900 border border-navy-200/70 shadow-2xs'
                          : 'text-slate-600 hover:text-navy-900 hover:bg-slate-50'
                      )}
                    >
                      <Briefcase className="w-4 h-4 text-navy-700" strokeWidth={1.75} />
                      <span>Gabung Mitra</span>
                    </Link>
                  )}
                </>
              )}
            </nav>

            {/* Desktop Right Action Area */}
            <div className="hidden md:flex items-center gap-2.5">
              {loading ? (
                <div className="h-8 w-24 bg-slate-100 animate-pulse rounded-xl" />
              ) : isAuthenticated && user ? (
                <div className="flex items-center gap-2">
                  {/* Chat Button */}
                  <Link
                    href="/chat"
                    className={cn(
                      'relative p-2 rounded-xl transition-colors border',
                      isChatActive
                        ? 'bg-navy-50 text-navy-900 border-navy-200 shadow-2xs'
                        : 'text-slate-600 hover:text-navy-900 hover:bg-slate-50 border-slate-200/80'
                    )}
                    aria-label="Pesan Chat"
                    title="Pesan Chat"
                  >
                    <MessageSquare className="w-4 h-4" strokeWidth={1.75} />
                    {unreadChatCount > 0 && (
                      <span className="absolute -top-1 -right-1 min-w-[16px] h-[16px] px-1 bg-rose-600 text-white text-[9px] font-bold rounded-full flex items-center justify-center tabular-nums shadow-xs">
                        {unreadChatCount > 9 ? '9+' : unreadChatCount}
                      </span>
                    )}
                  </Link>

                  {/* Notification Bell */}
                  <Link
                    href="/notifications"
                    className={cn(
                      'relative p-2 rounded-xl transition-colors border',
                      isNotifActive
                        ? 'bg-navy-50 text-navy-900 border-navy-200 shadow-2xs'
                        : 'text-slate-600 hover:text-navy-900 hover:bg-slate-50 border-slate-200/80'
                    )}
                    aria-label="Notifikasi"
                    title="Notifikasi"
                  >
                    <Bell className="w-4 h-4" strokeWidth={1.75} />
                    {unreadNotifCount > 0 && (
                      <span className="absolute -top-1 -right-1 min-w-[16px] h-[16px] px-1 bg-rose-600 text-white text-[9px] font-bold rounded-full flex items-center justify-center tabular-nums shadow-xs">
                        {unreadNotifCount > 9 ? '9+' : unreadNotifCount}
                      </span>
                    )}
                  </Link>

                  {/* Account Menu Dropdown */}
                  <div className="relative" ref={dropdownRef}>
                    <button
                      ref={triggerRef}
                      type="button"
                      onClick={() => setAccountDropdownOpen(!accountDropdownOpen)}
                      onKeyDown={handleTriggerKeyDown}
                      className={cn(
                        'inline-flex items-center gap-2 pl-1.5 pr-2.5 py-1 rounded-xl border text-xs font-semibold cursor-pointer transition-all duration-200 focus:outline-hidden focus:ring-2 focus:ring-navy-600/20',
                        accountDropdownOpen
                          ? 'bg-navy-50 border-navy-200 text-navy-900 shadow-2xs'
                          : 'bg-white border-slate-200/80 text-navy-900 hover:bg-slate-50 hover:border-slate-300'
                      )}
                      aria-label="Menu Akun"
                      aria-expanded={accountDropdownOpen}
                      aria-haspopup="menu"
                    >
                      <Avatar src={user.avatar_url} name={user.name} size="sm" className="h-8 w-8 shrink-0 rounded-full overflow-hidden" />
                      <span className="max-w-[110px] truncate text-navy-900 font-semibold">{user.name}</span>
                      <ChevronDown
                        className={cn(
                          'w-3.5 h-3.5 text-slate-500 transition-transform duration-200 shrink-0',
                          accountDropdownOpen && 'rotate-180 text-navy-800'
                        )}
                        strokeWidth={1.75}
                      />
                    </button>

                    {accountDropdownOpen && (
                      <div
                        ref={menuRef}
                        role="menu"
                        aria-label="Menu Profil"
                        onKeyDown={handleMenuKeyDown}
                        className="absolute right-0 mt-2 w-72 bg-white rounded-2xl border border-navy-100 shadow-xl z-50 overflow-hidden animate-menu-slide text-xs"
                      >
                        {/* Header panel aksen gelap navy-900 dengan mini bubbles */}
                        <div className="bg-navy-900 text-white p-3.5 border-b border-navy-800 relative overflow-hidden">
                          <div className="absolute top-1 right-2 pointer-events-none opacity-20" aria-hidden="true">
                            <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                              <circle cx="20" cy="20" r="16" stroke="white" strokeWidth="1" />
                              <circle cx="28" cy="12" r="5" stroke="white" strokeWidth="1" />
                            </svg>
                          </div>
                          <div className="flex items-start gap-3 relative z-10">
                            <Avatar
                              src={user.avatar_url}
                              name={user.name}
                              size="md"
                              className="w-10 h-10 rounded-xl border border-white/20 bg-white/15 text-white shrink-0 text-sm font-bold shadow-2xs"
                            />
                            <div className="min-w-0 flex-1">
                              <div className="font-bold text-sm text-white truncate leading-tight">
                                {user.name}
                              </div>
                              <div className="text-[11px] text-slate-300 truncate mt-0.5">
                                {getUserDisplayContact(user)}
                              </div>
                              <div className="mt-1.5">
                                <span className="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-semibold bg-white/15 text-white border border-white/20 tracking-wide">
                                  Pelanggan
                                </span>
                              </div>
                            </div>
                          </div>
                        </div>

                        {/* Grup Menu: Akun Saya */}
                        <div className="py-1">
                          <div className="px-3.5 pt-2 pb-1 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                            Akun Saya
                          </div>
                          <Link
                            href="/profile"
                            role="menuitem"
                            tabIndex={-1}
                            onClick={() => setAccountDropdownOpen(false)}
                            className="flex items-center gap-3 px-3.5 py-2 text-slate-800 hover:bg-navy-50/70 focus:bg-navy-50/70 focus:outline-hidden transition-colors group cursor-pointer"
                          >
                            <User className="w-4 h-4 text-slate-500 shrink-0 group-hover:text-navy-800 group-focus:text-navy-800" strokeWidth={1.75} />
                            <div className="min-w-0 flex-1">
                              <div className="text-xs font-semibold text-navy-900">Profil Saya</div>
                              <div className="text-[11px] text-slate-500">Kelola info akun dan data pribadi</div>
                            </div>
                          </Link>
                          <Link
                            href="/profile#addresses"
                            role="menuitem"
                            tabIndex={-1}
                            onClick={() => setAccountDropdownOpen(false)}
                            className="flex items-center gap-3 px-3.5 py-2 text-slate-800 hover:bg-navy-50/70 focus:bg-navy-50/70 focus:outline-hidden transition-colors group cursor-pointer"
                          >
                            <MapPin className="w-4 h-4 text-slate-500 shrink-0 group-hover:text-navy-800 group-focus:text-navy-800" strokeWidth={1.75} />
                            <div className="min-w-0 flex-1">
                              <div className="text-xs font-semibold text-navy-900">Daftar Alamat</div>
                              <div className="text-[11px] text-slate-500">Atur alamat pengiriman laundry</div>
                            </div>
                          </Link>
                        </div>

                        {/* Grup Menu: Kemitraan */}
                        <div className="py-1 border-t border-slate-100">
                          <div className="px-3.5 pt-2 pb-1 text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                            Kemitraan
                          </div>
                          {hasPendingOrOtherStore ? (
                            <Link
                              href="/upgrade"
                              role="menuitem"
                              tabIndex={-1}
                              onClick={() => setAccountDropdownOpen(false)}
                              className="flex items-center gap-3 px-3.5 py-2 text-slate-800 hover:bg-navy-50/70 focus:bg-navy-50/70 focus:outline-hidden transition-colors group cursor-pointer"
                            >
                              <Briefcase className="w-4 h-4 text-slate-500 shrink-0 group-hover:text-navy-800 group-focus:text-navy-800" strokeWidth={1.75} />
                              <div className="min-w-0 flex-1">
                                <div className="text-xs font-semibold text-navy-900">Status Pengajuan</div>
                                <div className="text-[11px] text-slate-500">Pantau verifikasi mitra laundry</div>
                              </div>
                            </Link>
                          ) : (
                            <Link
                              href="/upgrade"
                              role="menuitem"
                              tabIndex={-1}
                              onClick={() => setAccountDropdownOpen(false)}
                              className="flex items-center gap-3 px-3.5 py-2 text-slate-800 hover:bg-navy-50/70 focus:bg-navy-50/70 focus:outline-hidden transition-colors group cursor-pointer"
                            >
                              <Briefcase className="w-4 h-4 text-slate-500 shrink-0 group-hover:text-navy-800 group-focus:text-navy-800" strokeWidth={1.75} />
                              <div className="min-w-0 flex-1">
                                <div className="text-xs font-semibold text-navy-900">Gabung Mitra</div>
                                <div className="text-[11px] text-slate-500">Daftarkan usaha laundry Anda</div>
                              </div>
                            </Link>
                          )}
                        </div>

                        {/* Item Keluar dengan pemisah */}
                        <div className="p-1.5 border-t border-slate-100 bg-slate-50/60">
                          <button
                            type="button"
                            role="menuitem"
                            tabIndex={-1}
                            onClick={async () => {
                              setAccountDropdownOpen(false);
                              await logoutUser();
                            }}
                            className="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-rose-600 hover:bg-rose-50 focus:bg-rose-50 focus:outline-hidden transition-colors text-left cursor-pointer group font-medium"
                          >
                            <LogOut className="w-4 h-4 shrink-0 text-rose-500 group-hover:text-rose-600 group-focus:text-rose-600" strokeWidth={1.75} />
                            <div className="min-w-0 flex-1">
                              <div className="text-xs font-semibold text-rose-600">Keluar</div>
                              <div className="text-[11px] text-rose-400">Keluar dari sesi akun saat ini</div>
                            </div>
                          </button>
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              ) : (
                <div className="flex items-center gap-2">
                  <Link
                    href="/auth"
                    className="px-3.5 py-1.5 text-xs font-bold text-navy-800 hover:text-navy-950 hover:bg-navy-50/70 rounded-xl border border-navy-200/80 transition-colors"
                  >
                    Masuk
                  </Link>
                  <Link
                    href="/auth"
                    className="px-4 py-1.5 text-xs font-bold text-white bg-navy-700 hover:bg-navy-800 active:scale-95 rounded-xl transition-all shadow-2xs"
                  >
                    Daftar
                  </Link>
                </div>
              )}
            </div>

            {/* Mobile Right Bar: Notifications + Hamburger Toggle */}
            <div className="md:hidden flex items-center gap-1.5">
              {isAuthenticated && user && (
                <Link
                  href="/notifications"
                  className="relative p-2 text-slate-600 hover:text-navy-900 rounded-xl transition-colors"
                  aria-label="Notifikasi"
                >
                  <Bell className="w-5 h-5" strokeWidth={1.75} />
                  {unreadNotifCount > 0 && (
                    <span className="absolute top-1 right-1 min-w-[16px] h-[16px] px-1 bg-rose-600 text-white text-[9px] font-bold rounded-full flex items-center justify-center tabular-nums shadow-xs">
                      {unreadNotifCount > 9 ? '9+' : unreadNotifCount}
                    </span>
                  )}
                </Link>
              )}

              <button
                onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                className="p-2 rounded-xl text-slate-700 hover:text-navy-900 hover:bg-slate-100 transition-colors cursor-pointer"
                aria-label="Buka menu navigasi"
              >
                {mobileMenuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
              </button>
            </div>
          </div>

          {/* Mobile Drawer Menu (Contained within Floating Island) */}
          {mobileMenuOpen && (
            <div className="md:hidden border-t border-slate-100 bg-white/98 px-4 py-3.5 space-y-3 animate-fadeIn">
              {isAuthenticated && user && (
                <div className="p-3 rounded-xl bg-navy-50/70 border border-navy-100 mb-2">
                  <div className="font-bold text-xs text-navy-900 truncate">{user.name}</div>
                  <div className="text-[11px] text-slate-500 truncate">{getUserDisplayContact(user)}</div>
                </div>
              )}

              <nav className="space-y-1 text-xs font-semibold text-slate-700">
                {!isAuthenticated ? (
                  <>
                    <Link
                      href="/"
                      className={cn(
                        'block px-3 py-2 rounded-xl transition-colors',
                        pathname === '/' ? 'bg-navy-50 text-navy-900 font-bold' : 'hover:bg-slate-50 hover:text-navy-900'
                      )}
                    >
                      Beranda
                    </Link>
                    <Link
                      href="/stores"
                      className={cn(
                        'block px-3 py-2 rounded-xl transition-colors',
                        pathname === '/stores' ? 'bg-navy-50 text-navy-900 font-bold' : 'hover:bg-slate-50 hover:text-navy-900'
                      )}
                    >
                      Cari Toko
                    </Link>
                    <Link
                      href="/upgrade"
                      className={cn(
                        'block px-3 py-2 rounded-xl transition-colors',
                        pathname === '/upgrade' ? 'bg-navy-50 text-navy-900 font-bold' : 'hover:bg-slate-50 hover:text-navy-900'
                      )}
                    >
                      Gabung Mitra
                    </Link>
                  </>
                ) : (
                  <>
                    <Link
                      href="/stores"
                      className={cn(
                        'block px-3 py-2 rounded-xl transition-colors',
                        pathname === '/stores' ? 'bg-navy-50 text-navy-900 font-bold' : 'hover:bg-slate-50 hover:text-navy-900'
                      )}
                    >
                      Cari Toko
                    </Link>
                    <Link
                      href="/orders"
                      className={cn(
                        'block px-3 py-2 rounded-xl transition-colors',
                        pathname === '/orders' || pathname.startsWith('/orders/')
                          ? 'bg-navy-50 text-navy-900 font-bold'
                          : 'hover:bg-slate-50 hover:text-navy-900'
                      )}
                    >
                      Pesanan Saya
                    </Link>
                    <Link
                      href="/chat"
                      className={cn(
                        'block px-3 py-2 rounded-xl transition-colors',
                        isChatActive ? 'bg-navy-50 text-navy-900 font-bold' : 'hover:bg-slate-50 hover:text-navy-900'
                      )}
                    >
                      Chat Toko {unreadChatCount > 0 && `(${unreadChatCount})`}
                    </Link>
                    <Link
                      href="/profile"
                      className={cn(
                        'block px-3 py-2 rounded-xl transition-colors',
                        pathname === '/profile' ? 'bg-navy-50 text-navy-900 font-bold' : 'hover:bg-slate-50 hover:text-navy-900'
                      )}
                    >
                      Profil Akun
                    </Link>
                    {hasPendingOrOtherStore ? (
                      <Link
                        href="/upgrade"
                        className={cn(
                          'block px-3 py-2 rounded-xl transition-colors',
                          pathname === '/upgrade' ? 'bg-navy-50 text-navy-900 font-bold' : 'hover:bg-slate-50 hover:text-navy-900'
                        )}
                      >
                        Status Pengajuan
                      </Link>
                    ) : (
                      <Link
                        href="/upgrade"
                        className={cn(
                          'block px-3 py-2 rounded-xl transition-colors',
                          pathname === '/upgrade' ? 'bg-navy-50 text-navy-900 font-bold' : 'hover:bg-slate-50 hover:text-navy-900'
                        )}
                      >
                        Gabung Mitra
                      </Link>
                    )}
                  </>
                )}
              </nav>

              <div className="pt-3 border-t border-slate-100">
                {isAuthenticated ? (
                  <button
                    onClick={async () => {
                      setMobileMenuOpen(false);
                      await logoutUser();
                    }}
                    className="w-full py-2.5 px-3 text-xs font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-xl text-center transition-colors cursor-pointer"
                  >
                    Keluar dari Akun
                  </button>
                ) : (
                  <div className="flex gap-2">
                    <Link
                      href="/auth"
                      className="flex-1 py-2 text-center text-xs font-bold text-navy-800 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors"
                    >
                      Masuk
                    </Link>
                    <Link
                      href="/auth"
                      className="flex-1 py-2 text-center text-xs font-bold text-white bg-navy-700 hover:bg-navy-800 rounded-xl shadow-2xs transition-colors"
                    >
                      Daftar
                    </Link>
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      </header>

      {/* Main Content Area */}
      <main className="flex-1">{children}</main>

      {/* Footer */}
      <footer className="bg-navy-950 text-slate-300 pt-12 pb-20 md:pb-12 border-t border-navy-900 text-xs">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-1 md:grid-cols-12 gap-8 pb-8 border-b border-navy-900">
            {/* Brand Column */}
            <div className="md:col-span-4 space-y-3">
              <div className="flex items-center gap-2">
                <div className="w-7 h-7 rounded-lg bg-navy-900 border border-navy-700 flex items-center justify-center text-white font-bold text-xs shadow-xs">
                  LK
                </div>
                <span className="font-semibold text-base text-white tracking-tight">LaundryKu</span>
              </div>
              <p className="text-slate-400 text-xs leading-relaxed max-w-sm">
                Marketplace layanan laundry antar-jemput yang menghubungkan pelanggan dengan outlet terverifikasi dan kurir terpercaya.
              </p>
            </div>

            {/* Links Columns */}
            <div className="md:col-span-8 grid grid-cols-2 sm:grid-cols-3 gap-6">
              {/* Produk */}
              <div>
                <h4 className="font-semibold text-white uppercase tracking-wider text-[11px] mb-3">Produk</h4>
                <ul className="space-y-2 text-slate-400 text-xs">
                  <li>
                    <Link href="/stores" className="hover:text-white transition-colors">
                      Cari Toko
                    </Link>
                  </li>
                  <li>
                    <Link href="/upgrade" className="hover:text-white transition-colors">
                      Daftarkan Laundry
                    </Link>
                  </li>
                </ul>
              </div>

              {/* Bantuan */}
              <div>
                <h4 className="font-semibold text-white uppercase tracking-wider text-[11px] mb-3">Bantuan</h4>
                <ul className="space-y-2 text-slate-400 text-xs">
                  <li>
                    <Link href="/#faq" className="hover:text-white transition-colors">
                      FAQ
                    </Link>
                  </li>
                  <li>
                    <a
                      href={`mailto:${process.env.NEXT_PUBLIC_CS_EMAIL || 'bantuan@laundryku.com'}`}
                      className="hover:text-white transition-colors"
                    >
                      Hubungi CS
                    </a>
                  </li>
                </ul>
              </div>

              {/* Legal */}
              <div>
                <h4 className="font-semibold text-white uppercase tracking-wider text-[11px] mb-3">Legal</h4>
                <ul className="space-y-2 text-slate-400 text-xs">
                  <li>
                    <span className="text-slate-500 cursor-default">
                      Syarat &amp; Ketentuan <span className="text-[10px] text-slate-600">(segera hadir)</span>
                    </span>
                  </li>
                  <li>
                    <span className="text-slate-500 cursor-default">
                      Kebijakan Privasi <span className="text-[10px] text-slate-600">(segera hadir)</span>
                    </span>
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <div className="pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-slate-500 text-[11px]">
            <p>&copy; {new Date().getFullYear()} LaundryKu. Hak Cipta Dilindungi.</p>
            <div className="flex items-center gap-4 text-slate-500 text-[11px]">
              <span>Layanan Terverifikasi &amp; Aman</span>
            </div>
          </div>
        </div>
      </footer>

      {/* Mobile Bottom Navigation Bar (Floating Island) */}
      <nav
        className="md:hidden fixed bottom-3 inset-x-3 sm:inset-x-6 max-w-md mx-auto z-40 bg-white/95 backdrop-blur-md rounded-2xl border border-navy-100/90 shadow-lg px-2 py-1.5"
        aria-label="Navigasi Bawah Mobile"
      >
        <div className="flex items-center justify-around">
          {!isAuthenticated ? (
            <>
              <Link
                href="/"
                className={cn(
                  'flex flex-col items-center justify-center py-1 px-2.5 rounded-xl transition-all min-h-[44px] min-w-[56px] text-center',
                  pathname === '/'
                    ? 'bg-navy-50 text-navy-900 font-bold border border-navy-200/70 shadow-2xs'
                    : 'text-slate-500 hover:text-navy-800 hover:bg-slate-50/80 font-medium'
                )}
              >
                <Home className="w-4 h-4 mb-0.5 shrink-0" strokeWidth={pathname === '/' ? 2 : 1.75} />
                <span className="text-[10px] leading-tight tracking-tight">Beranda</span>
              </Link>
              <Link
                href="/stores"
                className={cn(
                  'flex flex-col items-center justify-center py-1 px-2.5 rounded-xl transition-all min-h-[44px] min-w-[56px] text-center',
                  pathname === '/stores'
                    ? 'bg-navy-50 text-navy-900 font-bold border border-navy-200/70 shadow-2xs'
                    : 'text-slate-500 hover:text-navy-800 hover:bg-slate-50/80 font-medium'
                )}
              >
                <Store className="w-4 h-4 mb-0.5 shrink-0" strokeWidth={pathname === '/stores' ? 2 : 1.75} />
                <span className="text-[10px] leading-tight tracking-tight">Cari Toko</span>
              </Link>
              <Link
                href="/upgrade"
                className={cn(
                  'flex flex-col items-center justify-center py-1 px-2.5 rounded-xl transition-all min-h-[44px] min-w-[56px] text-center',
                  pathname === '/upgrade'
                    ? 'bg-navy-50 text-navy-900 font-bold border border-navy-200/70 shadow-2xs'
                    : 'text-slate-500 hover:text-navy-800 hover:bg-slate-50/80 font-medium'
                )}
              >
                <Briefcase className="w-4 h-4 mb-0.5 shrink-0" strokeWidth={pathname === '/upgrade' ? 2 : 1.75} />
                <span className="text-[10px] leading-tight tracking-tight">Mitra</span>
              </Link>
              <Link
                href="/auth"
                className={cn(
                  'flex flex-col items-center justify-center py-1 px-2.5 rounded-xl transition-all min-h-[44px] min-w-[56px] text-center',
                  pathname === '/auth'
                    ? 'bg-navy-50 text-navy-900 font-bold border border-navy-200/70 shadow-2xs'
                    : 'text-slate-500 hover:text-navy-800 hover:bg-slate-50/80 font-medium'
                )}
              >
                <User className="w-4 h-4 mb-0.5 shrink-0" strokeWidth={pathname === '/auth' ? 2 : 1.75} />
                <span className="text-[10px] leading-tight tracking-tight">Masuk</span>
              </Link>
            </>
          ) : (
            <>
              <Link
                href="/stores"
                className={cn(
                  'flex flex-col items-center justify-center py-1 px-2.5 rounded-xl transition-all min-h-[44px] min-w-[56px] text-center',
                  pathname === '/stores'
                    ? 'bg-navy-50 text-navy-900 font-bold border border-navy-200/70 shadow-2xs'
                    : 'text-slate-500 hover:text-navy-800 hover:bg-slate-50/80 font-medium'
                )}
              >
                <Store className="w-4 h-4 mb-0.5 shrink-0" strokeWidth={pathname === '/stores' ? 2 : 1.75} />
                <span className="text-[10px] leading-tight tracking-tight">Toko</span>
              </Link>
              <Link
                href="/orders"
                className={cn(
                  'flex flex-col items-center justify-center py-1 px-2.5 rounded-xl transition-all min-h-[44px] min-w-[56px] text-center',
                  pathname === '/orders' || pathname.startsWith('/orders/')
                    ? 'bg-navy-50 text-navy-900 font-bold border border-navy-200/70 shadow-2xs'
                    : 'text-slate-500 hover:text-navy-800 hover:bg-slate-50/80 font-medium'
                )}
              >
                <Package
                  className="w-4 h-4 mb-0.5 shrink-0"
                  strokeWidth={pathname === '/orders' || pathname.startsWith('/orders/') ? 2 : 1.75}
                />
                <span className="text-[10px] leading-tight tracking-tight">Pesanan</span>
              </Link>
              <Link
                href="/chat"
                className={cn(
                  'relative flex flex-col items-center justify-center py-1 px-2.5 rounded-xl transition-all min-h-[44px] min-w-[56px] text-center',
                  isChatActive
                    ? 'bg-navy-50 text-navy-900 font-bold border border-navy-200/70 shadow-2xs'
                    : 'text-slate-500 hover:text-navy-800 hover:bg-slate-50/80 font-medium'
                )}
              >
                <MessageSquare className="w-4 h-4 mb-0.5 shrink-0" strokeWidth={isChatActive ? 2 : 1.75} />
                {unreadChatCount > 0 && (
                  <span className="absolute top-1 right-2 min-w-[14px] h-[14px] px-0.5 bg-rose-600 text-white text-[8px] font-bold rounded-full flex items-center justify-center tabular-nums shadow-xs">
                    {unreadChatCount > 9 ? '9+' : unreadChatCount}
                  </span>
                )}
                <span className="text-[10px] leading-tight tracking-tight">Chat</span>
              </Link>
              <Link
                href="/profile"
                className={cn(
                  'flex flex-col items-center justify-center py-1 px-2.5 rounded-xl transition-all min-h-[44px] min-w-[56px] text-center',
                  pathname === '/profile'
                    ? 'bg-navy-50 text-navy-900 font-bold border border-navy-200/70 shadow-2xs'
                    : 'text-slate-500 hover:text-navy-800 hover:bg-slate-50/80 font-medium'
                )}
              >
                <User className="w-4 h-4 mb-0.5 shrink-0" strokeWidth={pathname === '/profile' ? 2 : 1.75} />
                <span className="text-[10px] leading-tight tracking-tight">Akun</span>
              </Link>
            </>
          )}
        </div>
      </nav>

      {/* Neutral Floating Bantuan Widget */}
      <AiChatWidget />
    </div>
  );
};
