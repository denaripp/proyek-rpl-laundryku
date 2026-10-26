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
} from 'lucide-react';
import { AiChatWidget } from '@/components/chatbot/AiChatWidget';
import { Avatar } from '@/components/ui/Avatar';
import { cn } from '@/lib/format';
import { useAuth } from '@/lib/AuthContext';
import { getUnreadChatCount } from '@/lib/chat';
import { notificationApi } from '@/lib/notification';
import { hasRole, hasApprovedStore, getUserDisplayContact } from '@/lib/auth';

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

  useEffect(() => {
    fetchCounters();
    const interval = setInterval(fetchCounters, 15000);

    const handleReadEvent = () => fetchCounters();
    window.addEventListener('laundryku_chat_read', handleReadEvent);
    window.addEventListener('laundryku_auth_change', handleReadEvent);

    return () => {
      clearInterval(interval);
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
    <div className="min-h-screen flex flex-col bg-white text-slate-900 font-sans pb-16 md:pb-0">
      {/* Top Header Navigation */}
      <header className="sticky top-0 z-40 bg-white border-b border-slate-200">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
          {/* Brand Wordmark Logo */}
          <Link href={isAuthenticated ? '/stores' : '/'} className="flex items-center gap-2 group">
            <div className="w-8 h-8 rounded-md bg-[#1A3A5C] flex items-center justify-center text-white font-bold text-sm tracking-wider">
              LK
            </div>
            <span className="font-semibold text-lg tracking-tight text-slate-900">
              Laundry<span className="text-[#1A3A5C]">Ku</span>
            </span>
          </Link>

          {/* Desktop Navigation Links */}
          <nav className="hidden md:flex items-center gap-6">
            {!isAuthenticated ? (
              <>
                <Link
                  href="/"
                  className={cn(
                    'text-xs font-medium transition-colors',
                    pathname === '/' ? 'text-slate-900 font-semibold' : 'text-slate-600 hover:text-slate-900'
                  )}
                >
                  Beranda
                </Link>
                <Link
                  href="/stores"
                  className={cn(
                    'text-xs font-medium transition-colors',
                    pathname === '/stores' ? 'text-slate-900 font-semibold' : 'text-slate-600 hover:text-slate-900'
                  )}
                >
                  Cari Toko
                </Link>
                <Link
                  href="/upgrade"
                  className={cn(
                    'text-xs font-medium transition-colors',
                    pathname === '/upgrade' ? 'text-slate-900 font-semibold' : 'text-slate-600 hover:text-slate-900'
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
                    'text-xs font-medium transition-colors flex items-center gap-1.5',
                    pathname === '/stores' ? 'text-[#1A3A5C] font-semibold' : 'text-slate-600 hover:text-slate-900'
                  )}
                >
                  <Store className="w-4 h-4" strokeWidth={1.5} />
                  <span>Cari Toko</span>
                </Link>
                <Link
                  href="/orders"
                  className={cn(
                    'text-xs font-medium transition-colors flex items-center gap-1.5',
                    pathname === '/orders' || pathname.startsWith('/orders/')
                      ? 'text-[#1A3A5C] font-semibold'
                      : 'text-slate-600 hover:text-slate-900'
                  )}
                >
                  <Package className="w-4 h-4" strokeWidth={1.5} />
                  <span>Pesanan Saya</span>
                </Link>
                {hasPendingOrOtherStore ? (
                  <Link
                    href="/upgrade"
                    className={cn(
                      'text-xs font-medium transition-colors flex items-center gap-1.5',
                      pathname === '/upgrade' ? 'text-[#1A3A5C] font-semibold' : 'text-slate-600 hover:text-slate-900'
                    )}
                  >
                    <Briefcase className="w-4 h-4" strokeWidth={1.5} />
                    <span>Status Pengajuan</span>
                  </Link>
                ) : (
                  <Link
                    href="/upgrade"
                    className={cn(
                      'text-xs font-medium transition-colors flex items-center gap-1.5',
                      pathname === '/upgrade' ? 'text-[#1A3A5C] font-semibold' : 'text-slate-600 hover:text-slate-900'
                    )}
                  >
                    <Briefcase className="w-4 h-4" strokeWidth={1.5} />
                    <span>Gabung Mitra</span>
                  </Link>
                )}
              </>
            )}
          </nav>

          {/* Desktop Right Action Area */}
          <div className="hidden md:flex items-center gap-3">
            {loading ? (
              <div className="h-8 w-24 bg-slate-100 animate-pulse rounded-md" />
            ) : isAuthenticated && user ? (
              <div className="flex items-center gap-2">
                {/* Chat Button */}
                <Link
                  href="/chat"
                  className={cn(
                    'relative p-2 rounded-md transition-colors border',
                    isChatActive
                      ? 'bg-slate-100 text-slate-900 border-slate-300'
                      : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 border-slate-200'
                  )}
                  aria-label="Pesan Chat"
                  title="Pesan Chat"
                >
                  <MessageSquare className="w-4 h-4" strokeWidth={1.5} />
                  {unreadChatCount > 0 && (
                    <span className="absolute -top-1 -right-1 min-w-[16px] h-[16px] px-1 bg-rose-600 text-white text-[9px] font-bold rounded-full flex items-center justify-center tabular-nums">
                      {unreadChatCount > 9 ? '9+' : unreadChatCount}
                    </span>
                  )}
                </Link>

                {/* Notification Bell */}
                <Link
                  href="/notifications"
                  className={cn(
                    'relative p-2 rounded-md transition-colors border',
                    isNotifActive
                      ? 'bg-slate-100 text-slate-900 border-slate-300'
                      : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 border-slate-200'
                  )}
                  aria-label="Notifikasi"
                  title="Notifikasi"
                >
                  <Bell className="w-4 h-4" strokeWidth={1.5} />
                  {unreadNotifCount > 0 && (
                    <span className="absolute -top-1 -right-1 min-w-[16px] h-[16px] px-1 bg-rose-600 text-white text-[9px] font-bold rounded-full flex items-center justify-center tabular-nums">
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
                      'flex items-center gap-2 pl-2 pr-2.5 py-1.5 rounded-md border text-xs font-medium cursor-pointer transition-all duration-200 focus:outline-hidden focus:ring-2 focus:ring-[#1A3A5C]/20',
                      accountDropdownOpen
                        ? 'bg-slate-100 border-slate-300 text-slate-900 shadow-2xs'
                        : 'bg-white border-slate-200 text-slate-800 hover:bg-slate-50 hover:border-slate-300'
                    )}
                    aria-label="Menu Akun"
                    aria-expanded={accountDropdownOpen}
                    aria-haspopup="menu"
                  >
                    <Avatar src={user.avatar_url} name={user.name} size="sm" className="w-6 h-6 text-[10px]" />
                    <span className="max-w-[110px] truncate text-slate-800 font-medium">{user.name}</span>
                    <ChevronDown
                      className={cn(
                        'w-3.5 h-3.5 text-slate-500 transition-transform duration-200 shrink-0',
                        accountDropdownOpen && 'rotate-180 text-slate-800'
                      )}
                      strokeWidth={1.5}
                    />
                  </button>

                  {accountDropdownOpen && (
                    <div
                      ref={menuRef}
                      role="menu"
                      aria-label="Menu Profil"
                      onKeyDown={handleMenuKeyDown}
                      className="absolute right-0 mt-2 w-72 bg-white rounded-[10px] border border-slate-200 shadow-lg z-50 overflow-hidden animate-menu-slide text-xs"
                    >
                      {/* Header panel aksen gelap #1A3A5C */}
                      <div className="bg-[#1A3A5C] text-white p-3.5 border-b border-slate-800/20">
                        <div className="flex items-start gap-3">
                          <Avatar
                            src={user.avatar_url}
                            name={user.name}
                            size="md"
                            className="w-10 h-10 border border-white/20 bg-white/15 text-white shrink-0 text-sm font-semibold"
                          />
                          <div className="min-w-0 flex-1">
                            <div className="font-semibold text-sm text-white truncate leading-tight">
                              {user.name}
                            </div>
                            <div className="text-[11px] text-slate-300 truncate mt-0.5">
                              {getUserDisplayContact(user)}
                            </div>
                            <div className="mt-1.5">
                              <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-white/15 text-white border border-white/20 tracking-wide">
                                Pelanggan
                              </span>
                            </div>
                          </div>
                        </div>
                      </div>

                      {/* Grup Menu: Akun Saya */}
                      <div className="py-1">
                        <div className="px-3.5 pt-2 pb-1 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">
                          Akun Saya
                        </div>
                        <Link
                          href="/profile"
                          role="menuitem"
                          tabIndex={-1}
                          onClick={() => setAccountDropdownOpen(false)}
                          className="flex items-center gap-3 px-3.5 py-2 text-slate-800 hover:bg-slate-50 focus:bg-slate-50 focus:outline-hidden transition-colors group cursor-pointer"
                        >
                          <User className="w-4 h-4 text-slate-500 shrink-0 group-hover:text-slate-800 group-focus:text-slate-800" strokeWidth={1.5} />
                          <div className="min-w-0 flex-1">
                            <div className="text-xs font-medium text-slate-800">Profil Saya</div>
                            <div className="text-[11px] text-slate-500">Kelola info akun dan data pribadi</div>
                          </div>
                        </Link>
                        <Link
                          href="/profile#addresses"
                          role="menuitem"
                          tabIndex={-1}
                          onClick={() => setAccountDropdownOpen(false)}
                          className="flex items-center gap-3 px-3.5 py-2 text-slate-800 hover:bg-slate-50 focus:bg-slate-50 focus:outline-hidden transition-colors group cursor-pointer"
                        >
                          <MapPin className="w-4 h-4 text-slate-500 shrink-0 group-hover:text-slate-800 group-focus:text-slate-800" strokeWidth={1.5} />
                          <div className="min-w-0 flex-1">
                            <div className="text-xs font-medium text-slate-800">Daftar Alamat</div>
                            <div className="text-[11px] text-slate-500">Atur alamat pengiriman laundry</div>
                          </div>
                        </Link>
                      </div>

                      {/* Grup Menu: Kemitraan */}
                      <div className="py-1 border-t border-slate-100">
                        <div className="px-3.5 pt-2 pb-1 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">
                          Kemitraan
                        </div>
                        {hasPendingOrOtherStore ? (
                          <Link
                            href="/upgrade"
                            role="menuitem"
                            tabIndex={-1}
                            onClick={() => setAccountDropdownOpen(false)}
                            className="flex items-center gap-3 px-3.5 py-2 text-slate-800 hover:bg-slate-50 focus:bg-slate-50 focus:outline-hidden transition-colors group cursor-pointer"
                          >
                            <Briefcase className="w-4 h-4 text-slate-500 shrink-0 group-hover:text-slate-800 group-focus:text-slate-800" strokeWidth={1.5} />
                            <div className="min-w-0 flex-1">
                              <div className="text-xs font-medium text-slate-800">Status Pengajuan</div>
                              <div className="text-[11px] text-slate-500">Pantau verifikasi mitra laundry</div>
                            </div>
                          </Link>
                        ) : (
                          <Link
                            href="/upgrade"
                            role="menuitem"
                            tabIndex={-1}
                            onClick={() => setAccountDropdownOpen(false)}
                            className="flex items-center gap-3 px-3.5 py-2 text-slate-800 hover:bg-slate-50 focus:bg-slate-50 focus:outline-hidden transition-colors group cursor-pointer"
                          >
                            <Briefcase className="w-4 h-4 text-slate-500 shrink-0 group-hover:text-slate-800 group-focus:text-slate-800" strokeWidth={1.5} />
                            <div className="min-w-0 flex-1">
                              <div className="text-xs font-medium text-slate-800">Gabung Mitra</div>
                              <div className="text-[11px] text-slate-500">Daftarkan usaha laundry Anda</div>
                            </div>
                          </Link>
                        )}
                      </div>

                      {/* Item Keluar dengan pemisah */}
                      <div className="p-1.5 border-t border-slate-100 bg-slate-50/50">
                        <button
                          type="button"
                          role="menuitem"
                          tabIndex={-1}
                          onClick={async () => {
                            setAccountDropdownOpen(false);
                            await logoutUser();
                          }}
                          className="w-full flex items-center gap-3 px-3 py-2 rounded-md text-rose-600 hover:bg-rose-50 focus:bg-rose-50 focus:outline-hidden transition-colors text-left cursor-pointer group"
                        >
                          <LogOut className="w-4 h-4 shrink-0 text-rose-500 group-hover:text-rose-600 group-focus:text-rose-600" strokeWidth={1.5} />
                          <div className="min-w-0 flex-1">
                            <div className="text-xs font-medium text-rose-600">Keluar</div>
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
                  className="px-3 py-1.5 text-xs font-medium text-slate-700 hover:text-slate-900 hover:bg-slate-50 rounded-md border border-slate-200 transition-colors"
                >
                  Masuk
                </Link>
                <Link
                  href="/auth"
                  className="px-3 py-1.5 text-xs font-medium text-white bg-[#1A3A5C] hover:bg-[#132d47] rounded-md transition-colors"
                >
                  Daftar
                </Link>
              </div>
            )}
          </div>

          {/* Mobile Right Bar: Notifications + Hamburger Toggle */}
          <div className="md:hidden flex items-center gap-2">
            {isAuthenticated && user && (
              <>
                <Link
                  href="/notifications"
                  className="relative p-2 text-slate-600 hover:text-slate-900 rounded-md"
                  aria-label="Notifikasi"
                >
                  <Bell className="w-5 h-5" strokeWidth={1.5} />
                  {unreadNotifCount > 0 && (
                    <span className="absolute top-1 right-1 min-w-[16px] h-[16px] px-1 bg-rose-600 text-white text-[9px] font-bold rounded-full flex items-center justify-center">
                      {unreadNotifCount > 9 ? '9+' : unreadNotifCount}
                    </span>
                  )}
                </Link>
              </>
            )}

            <button
              onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              className="p-2 rounded-md text-slate-700 hover:bg-slate-100"
              aria-label="Buka menu navigasi"
            >
              {mobileMenuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
            </button>
          </div>
        </div>

        {/* Mobile Drawer Menu */}
        {mobileMenuOpen && (
          <div className="md:hidden border-b border-slate-200 bg-white px-4 py-4 space-y-3 animate-fadeIn">
            {isAuthenticated && user && (
              <div className="p-3 rounded-md bg-slate-50 border border-slate-200 mb-3">
                <div className="font-semibold text-xs text-slate-900 truncate">{user.name}</div>
                <div className="text-[11px] text-slate-500 truncate">{getUserDisplayContact(user)}</div>
              </div>
            )}

            <nav className="space-y-1 text-xs font-medium text-slate-700">
              {!isAuthenticated ? (
                <>
                  <Link href="/" className="block px-3 py-2 rounded-md hover:bg-slate-50">
                    Beranda
                  </Link>
                  <Link href="/stores" className="block px-3 py-2 rounded-md hover:bg-slate-50">
                    Cari Toko
                  </Link>
                  <Link href="/upgrade" className="block px-3 py-2 rounded-md hover:bg-slate-50">
                    Gabung Mitra
                  </Link>
                </>
              ) : (
                <>
                  <Link href="/stores" className="block px-3 py-2 rounded-md hover:bg-slate-50">
                    Cari Toko
                  </Link>
                  <Link href="/orders" className="block px-3 py-2 rounded-md hover:bg-slate-50">
                    Pesanan Saya
                  </Link>
                  <Link href="/chat" className="block px-3 py-2 rounded-md hover:bg-slate-50">
                    Chat Toko {unreadChatCount > 0 && `(${unreadChatCount})`}
                  </Link>
                  <Link href="/profile" className="block px-3 py-2 rounded-md hover:bg-slate-50">
                    Profil Akun
                  </Link>
                  {hasPendingOrOtherStore ? (
                    <Link href="/upgrade" className="block px-3 py-2 rounded-md hover:bg-slate-50">
                      Status Pengajuan
                    </Link>
                  ) : (
                    <Link href="/upgrade" className="block px-3 py-2 rounded-md hover:bg-slate-50">
                      Gabung Mitra
                    </Link>
                  )}
                </>
              )}
            </nav>

            <div className="pt-3 border-t border-slate-200">
              {isAuthenticated ? (
                <button
                  onClick={async () => {
                    setMobileMenuOpen(false);
                    await logoutUser();
                  }}
                  className="w-full py-2 px-3 text-xs font-medium text-rose-600 bg-rose-50 hover:bg-rose-100 rounded-md text-center transition-colors"
                >
                  Keluar dari Akun
                </button>
              ) : (
                <div className="flex gap-2">
                  <Link
                    href="/auth"
                    className="flex-1 py-2 text-center text-xs font-medium text-slate-700 bg-slate-100 rounded-md"
                  >
                    Masuk
                  </Link>
                  <Link
                    href="/auth"
                    className="flex-1 py-2 text-center text-xs font-medium text-white bg-[#1A3A5C] rounded-md"
                  >
                    Daftar
                  </Link>
                </div>
              )}
            </div>
          </div>
        )}
      </header>

      {/* Main Content Area */}
      <main className="flex-1">{children}</main>

      {/* Footer */}
      <footer className="bg-slate-900 text-slate-300 pt-12 pb-20 md:pb-12 border-t border-slate-800 text-xs">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-1 md:grid-cols-12 gap-8 pb-8 border-b border-slate-800">
            {/* Brand Column */}
            <div className="md:col-span-4 space-y-3">
              <div className="flex items-center gap-2">
                <div className="w-7 h-7 rounded bg-slate-800 border border-slate-700 flex items-center justify-center text-white font-bold text-xs">
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

      {/* Mobile Bottom Navigation Bar */}
      <nav
        className="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-slate-200 px-2 py-1"
        aria-label="Navigasi Bawah Mobile"
      >
        <div className="flex items-center justify-around">
          {!isAuthenticated ? (
            <>
              <Link
                href="/"
                className={cn(
                  'flex flex-col items-center justify-center py-1.5 px-3 rounded-md transition-colors min-h-[44px]',
                  pathname === '/' ? 'text-[#1A3A5C] font-semibold' : 'text-slate-500'
                )}
              >
                <Store className="w-4 h-4 mb-0.5" strokeWidth={1.5} />
                <span className="text-[10px]">Beranda</span>
              </Link>
              <Link
                href="/stores"
                className={cn(
                  'flex flex-col items-center justify-center py-1.5 px-3 rounded-md transition-colors min-h-[44px]',
                  pathname === '/stores' ? 'text-[#1A3A5C] font-semibold' : 'text-slate-500'
                )}
              >
                <Store className="w-4 h-4 mb-0.5" strokeWidth={1.5} />
                <span className="text-[10px]">Cari Toko</span>
              </Link>
              <Link
                href="/upgrade"
                className={cn(
                  'flex flex-col items-center justify-center py-1.5 px-3 rounded-md transition-colors min-h-[44px]',
                  pathname === '/upgrade' ? 'text-[#1A3A5C] font-semibold' : 'text-slate-500'
                )}
              >
                <Briefcase className="w-4 h-4 mb-0.5" strokeWidth={1.5} />
                <span className="text-[10px]">Mitra</span>
              </Link>
              <Link
                href="/auth"
                className={cn(
                  'flex flex-col items-center justify-center py-1.5 px-3 rounded-md transition-colors min-h-[44px]',
                  pathname === '/auth' ? 'text-[#1A3A5C] font-semibold' : 'text-slate-500'
                )}
              >
                <User className="w-4 h-4 mb-0.5" strokeWidth={1.5} />
                <span className="text-[10px]">Masuk</span>
              </Link>
            </>
          ) : (
            <>
              <Link
                href="/stores"
                className={cn(
                  'flex flex-col items-center justify-center py-1.5 px-3 rounded-md transition-colors min-h-[44px]',
                  pathname === '/stores' ? 'text-[#1A3A5C] font-semibold' : 'text-slate-500'
                )}
              >
                <Store className="w-4 h-4 mb-0.5" strokeWidth={1.5} />
                <span className="text-[10px]">Toko</span>
              </Link>
              <Link
                href="/orders"
                className={cn(
                  'flex flex-col items-center justify-center py-1.5 px-3 rounded-md transition-colors min-h-[44px]',
                  pathname === '/orders' || pathname.startsWith('/orders/')
                    ? 'text-[#1A3A5C] font-semibold'
                    : 'text-slate-500'
                )}
              >
                <Package className="w-4 h-4 mb-0.5" strokeWidth={1.5} />
                <span className="text-[10px]">Pesanan</span>
              </Link>
              <Link
                href="/chat"
                className={cn(
                  'relative flex flex-col items-center justify-center py-1.5 px-3 rounded-md transition-colors min-h-[44px]',
                  isChatActive ? 'text-[#1A3A5C] font-semibold' : 'text-slate-500'
                )}
              >
                <MessageSquare className="w-4 h-4 mb-0.5" strokeWidth={1.5} />
                {unreadChatCount > 0 && (
                  <span className="absolute top-1 right-2 w-2 h-2 bg-rose-600 rounded-full" />
                )}
                <span className="text-[10px]">Chat</span>
              </Link>
              <Link
                href="/profile"
                className={cn(
                  'flex flex-col items-center justify-center py-1.5 px-3 rounded-md transition-colors min-h-[44px]',
                  pathname === '/profile' ? 'text-[#1A3A5C] font-semibold' : 'text-slate-500'
                )}
              >
                <User className="w-4 h-4 mb-0.5" strokeWidth={1.5} />
                <span className="text-[10px]">Akun</span>
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
