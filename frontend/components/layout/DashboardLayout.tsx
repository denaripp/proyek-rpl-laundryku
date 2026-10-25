'use client';

import React, { useState, useEffect, useCallback, useRef } from 'react';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import {
  Sparkles,
  LayoutDashboard,
  ShoppingBag,
  Store,
  Truck,
  MapPin,
  User,
  ShieldCheck,
  LogOut,
  Menu,
  X,
  ChevronLeft,
  ChevronRight,
  ChevronDown,
  MessageSquare,
  AlertCircle,
  Star,
  Tag,
  BarChart3,
  Settings,
  AlertTriangle,
  Power,
  Check,
} from 'lucide-react';
import { Avatar } from '@/components/ui/Avatar';
import { Dropdown } from '@/components/ui/Dropdown';
import { AiChatWidget } from '@/components/chatbot/AiChatWidget';
import { cn } from '@/lib/format';
import { getUnreadChatCount } from '@/lib/chat';
import {
  AuthUser,
  getStoredUser,
  getApprovedStores,
  getActiveStoreId,
  setActiveStoreId,
  OwnedStoreItem,
  logout,
  switchUserMode,
  fetchPortalSession,
  hasRole,
} from '@/lib/auth';
import { toggleStoreOperationalStatus, getStoreSidebarCounts, StoreSidebarCounts } from '@/lib/store';
import { useAuth } from '@/lib/AuthContext';

export type UserRole = 'customer' | 'courier' | 'store_admin' | 'super_admin';

export interface DashboardLayoutProps {
  children: React.ReactNode;
  userRole?: UserRole;
  userName?: string;
  userEmail?: string;
  userAvatar?: string | null;
  onLogout?: () => void;
}

export const DashboardLayout = ({
  children,
  userRole = 'customer',
  userName = 'Pelanggan LaundryKu',
  userEmail = 'customer@laundryku.id',
  userAvatar,
  onLogout,
}: DashboardLayoutProps) => {
  const pathname = usePathname();
  const router = useRouter();
  const [collapsed, setCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);
  const [unreadChatCount, setUnreadChatCount] = useState<number>(0);
  const [activeAvatar, setActiveAvatar] = useState<string | null | undefined>(userAvatar);
  const [sidebarCounts, setSidebarCounts] = useState<StoreSidebarCounts | null>(null);
  const isStorePortal = userRole === 'store_admin' || pathname.startsWith('/store');
  const isCourierPortal = userRole === 'courier' || pathname.startsWith('/courier');
  const targetPortal = isStorePortal ? 'mitra' : isCourierPortal ? 'courier' : 'customer';

  const [mounted, setMounted] = useState(false);
  const [portalUser, setPortalUser] = useState<AuthUser | null>(null);

  const { user } = useAuth();
  const availableModes = (user?.available_modes || (userRole === 'store_admin' ? ['customer', 'store'] : userRole === 'courier' ? ['customer', 'courier'] : ['customer'])) as string[];

  // Store owner multi-store and operational state
  const [ownedStores, setOwnedStores] = useState<OwnedStoreItem[]>([]);
  const [activeStore, setActiveStore] = useState<OwnedStoreItem | null>(null);
  const [switcherOpen, setSwitcherOpen] = useState(false);
  const [isUpdatingStatus, setIsUpdatingStatus] = useState(false);
  const switcherRef = useRef<HTMLDivElement>(null);
  const storeId = activeStore?.id;
  const fetchSidebarCounts = useCallback(async () => {
    if (userRole !== 'store_admin') return;
    try {
      const counts = await getStoreSidebarCounts(storeId);
      setSidebarCounts(counts);
    } catch {
      // ignore
    }
  }, [userRole, storeId]);

  useEffect(() => {
    fetchSidebarCounts();
    const interval = setInterval(fetchSidebarCounts, 30000);

    const handleRefresh = () => {
      fetchSidebarCounts();
    };

    window.addEventListener('laundryku_sidebar_count_refresh', handleRefresh);
    window.addEventListener('laundryku_active_store_change', handleRefresh);

    return () => {
      clearInterval(interval);
      window.removeEventListener('laundryku_sidebar_count_refresh', handleRefresh);
      window.removeEventListener('laundryku_active_store_change', handleRefresh);
    };
  }, [fetchSidebarCounts]);

  const syncStores = useCallback((overrideUser?: AuthUser | null) => {
    const user = overrideUser !== undefined
      ? overrideUser
      : (isStorePortal ? (getStoredUser('mitra') || portalUser) : isCourierPortal ? (getStoredUser('courier') || portalUser) : getStoredUser('customer'));

    if (user) {
      setActiveAvatar(user.avatar_url || null);
      const approved = getApprovedStores(user);
      setOwnedStores(approved);
      const currentActiveId = getActiveStoreId(user);
      const current = approved.find((s) => s.id === currentActiveId) || approved[0] || null;
      setActiveStore(current);
    }
  }, [isStorePortal, isCourierPortal, portalUser]);

  useEffect(() => {
    setMounted(true);
    const stored = getStoredUser(targetPortal);
    if (stored) {
      setPortalUser(stored);
      syncStores(stored);
    }

    if (isStorePortal || isCourierPortal) {
      fetchPortalSession(targetPortal).then((freshUser) => {
        if (freshUser) {
          setPortalUser(freshUser);
          syncStores(freshUser);
        }
      });
    }
  }, [isStorePortal, isCourierPortal, targetPortal, syncStores]);

  useEffect(() => {
    syncStores();

    const handleUserUpdate = (e: Event) => {
      const customEvent = e as CustomEvent<AuthUser | null>;
      if (customEvent.detail) {
        if (isStorePortal && (hasRole(customEvent.detail, 'store_owner') || hasRole(customEvent.detail, 'store_admin') || (customEvent.detail.stores && customEvent.detail.stores.length > 0))) {
          setPortalUser(customEvent.detail);
          setActiveAvatar(customEvent.detail.avatar_url || null);
          syncStores(customEvent.detail);
          return;
        }
        if (!isStorePortal && !isCourierPortal) {
          setActiveAvatar(customEvent.detail.avatar_url || null);
          syncStores(customEvent.detail);
        }
      } else {
        syncStores();
      }
    };

    const handleStoreChange = () => {
      syncStores();
    };

    window.addEventListener('auth:user-updated', handleUserUpdate);
    window.addEventListener('laundryku_auth_change', handleUserUpdate);
    window.addEventListener('laundryku_active_store_change', handleStoreChange);

    return () => {
      window.removeEventListener('auth:user-updated', handleUserUpdate);
      window.removeEventListener('laundryku_auth_change', handleUserUpdate);
      window.removeEventListener('laundryku_active_store_change', handleStoreChange);
    };
  }, [syncStores, isStorePortal, isCourierPortal]);

  // Click outside listener for store switcher dropdown
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (switcherRef.current && !switcherRef.current.contains(event.target as Node)) {
        setSwitcherOpen(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const fetchUnread = useCallback(async () => {
    const count = await getUnreadChatCount();
    setUnreadChatCount(count);
  }, []);

  useEffect(() => {
    fetchUnread();
    const interval = setInterval(fetchUnread, 10000);

    const handleChatRead = () => {
      fetchUnread();
    };

    window.addEventListener('laundryku_chat_read', handleChatRead);
    window.addEventListener('laundryku_auth_change', handleChatRead);

    return () => {
      clearInterval(interval);
      window.removeEventListener('laundryku_chat_read', handleChatRead);
      window.removeEventListener('laundryku_auth_change', handleChatRead);
    };
  }, [fetchUnread]);

  // Handle switching active store
  const handleSelectStore = (storeId: string) => {
    setActiveStoreId(storeId);
    const target = ownedStores.find((s) => s.id === storeId) || null;
    setActiveStore(target);
    setSwitcherOpen(false);
    // Reload or emit event for child pages
    router.refresh();
  };

  // Handle toggle operational status (active / inactive)
  const handleToggleStatus = async () => {
    if (!activeStore || activeStore.operational_status === 'suspended' || isUpdatingStatus) return;

    const nextStatus = activeStore.operational_status === 'active' ? 'inactive' : 'active';
    setIsUpdatingStatus(true);
    try {
      const res = await toggleStoreOperationalStatus(activeStore.id, nextStatus);
      setActiveStore((prev) => (prev ? { ...prev, operational_status: res.operational_status } : null));

      // Update stored user store status in localStorage
      const storageKey = isStorePortal ? 'laundryku_mitra_user' : 'laundryku_user';
      const currentUserData = getStoredUser(targetPortal);
      if (currentUserData && currentUserData.stores) {
        const updatedStores = currentUserData.stores.map((s) =>
          s.id === activeStore.id ? { ...s, operational_status: res.operational_status } : s
        );
        currentUserData.stores = updatedStores;
        localStorage.setItem(storageKey, JSON.stringify(currentUserData));
        setPortalUser(currentUserData);
        window.dispatchEvent(new CustomEvent('auth:user-updated', { detail: currentUserData }));
      }
    } catch {
      // Revert if error
    } finally {
      setIsUpdatingStatus(false);
    }
  };

  const handleLogout = onLogout || (async () => {
    if (isStorePortal) {
      await fetch('/api/auth/mitra/logout', { method: 'POST' }).catch(() => null);
      if (typeof window !== 'undefined') localStorage.removeItem('laundryku_mitra_user');
      router.push('/mitra/login');
    } else if (isCourierPortal) {
      await fetch('/api/auth/courier/logout', { method: 'POST' }).catch(() => null);
      if (typeof window !== 'undefined') localStorage.removeItem('laundryku_courier_user');
      router.push('/courier/login');
    } else if (userRole === 'super_admin') {
      await fetch('/api/auth/admin/logout', { method: 'POST' }).catch(() => null);
      if (typeof window !== 'undefined') localStorage.removeItem('laundryku_admin_user');
      router.push('/admin/login');
    } else {
      await logout();
      router.push('/auth');
    }
  });

  const roleNavItems: Record<UserRole, { label: string; href: string; icon: React.ComponentType<{ className?: string }> }[]> = {
    customer: [
      { label: 'Ringkasan', href: '/profile', icon: LayoutDashboard },
      { label: 'Alamat Saya', href: '/profile#alamat', icon: MapPin },
      { label: 'Gabung Mitra', href: '/upgrade', icon: ShieldCheck },
    ],
    courier: [
      { label: 'Dashboard Kurir', href: '/courier', icon: Truck },
      { label: 'Tugas Pengantaran', href: '/courier/tasks', icon: ShoppingBag },
    ],
    store_admin: [
      { label: 'Dashboard', href: '/store', icon: LayoutDashboard },
      { label: 'Pesanan', href: '/store/orders', icon: ShoppingBag },
      { label: 'Layanan', href: '/store/services', icon: Sparkles },
      { label: 'Harga', href: '/store/pricing', icon: Tag },
      { label: 'Kurir', href: '/store/couriers', icon: Truck },
      { label: 'Laporan', href: '/store/reports', icon: BarChart3 },
      { label: 'Pengaturan', href: '/store/settings', icon: Settings },
    ],
    super_admin: [
      { label: 'Admin Overview', href: '/admin', icon: LayoutDashboard },
      { label: 'Kelola Toko', href: '/admin/stores', icon: Store },
      { label: 'Kelola User', href: '/admin/users', icon: User },
    ],
  };

  const currentNav = roleNavItems[userRole] || roleNavItems.customer;
  const isSuspended = activeStore?.operational_status === 'suspended';
  const isOpen = activeStore?.operational_status === 'active';

  const serverFallbackName = isStorePortal
    ? 'Admin Toko'
    : isCourierPortal
    ? 'Kurir LaundryKu'
    : userRole === 'super_admin'
    ? 'Super Admin'
    : 'Pelanggan LaundryKu';

  const serverFallbackEmail = isStorePortal
    ? 'admin@toko.id'
    : isCourierPortal
    ? 'kurir@laundryku.id'
    : 'customer@laundryku.id';

  // Before mounted, both server and initial client hydration render MUST output identical defaults
  const displayUser = mounted ? portalUser : null;

  const resolvedUserName = mounted && displayUser?.name
    ? displayUser.name
    : mounted && userName !== 'Pelanggan LaundryKu' && userName !== 'Admin Toko' && userName !== 'Kurir LaundryKu'
    ? userName
    : serverFallbackName;

  const resolvedUserEmail = mounted && (displayUser?.email || displayUser?.phone)
    ? (displayUser.email || displayUser.phone)
    : mounted && userEmail !== 'customer@laundryku.id' && userEmail !== 'admin@toko.id' && userEmail !== 'kurir@laundryku.id'
    ? userEmail
    : serverFallbackEmail;

  const resolvedAvatar = mounted
    ? (displayUser?.avatar_url || activeAvatar || userAvatar)
    : (userAvatar || null);

  return (
    <div className="min-h-screen bg-slate-50 flex font-sans text-slate-900">
      {/* Mobile Backdrop */}
      {mobileOpen && (
        <div
          className="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-40 md:hidden"
          onClick={() => setMobileOpen(false)}
        />
      )}

      {/* Sidebar (Desktop & Mobile Drawer) */}
      <aside
        className={cn(
          'fixed md:static top-0 bottom-0 left-0 z-50 flex flex-col bg-[#1A3A5C] text-white transition-all duration-300 shadow-xl md:shadow-none border-r border-slate-800',
          collapsed ? 'w-20' : 'w-60',
          mobileOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'
        )}
      >
        {/* Brand Header */}
        <div className="h-16 flex items-center justify-between px-4 border-b border-white/10">
          <Link href="/" className="flex items-center gap-2.5 overflow-hidden">
            <div className="w-8 h-8 rounded-md bg-white/15 border border-white/20 flex items-center justify-center shrink-0 text-white font-bold text-xs tracking-wider">
              LK
            </div>
            {!collapsed && (
              <div className="flex flex-col truncate">
                <span className="font-bold text-base text-white tracking-tight">
                  Laundry<span className="text-sky-300">Ku</span>
                </span>
                <span className="text-[10px] text-slate-300 font-medium uppercase tracking-wider">
                  {userRole === 'store_admin' ? 'Dashboard Mitra' : userRole.replace('_', ' ')}
                </span>
              </div>
            )}
          </Link>
          <button
            onClick={() => setMobileOpen(false)}
            className="md:hidden text-slate-300 hover:text-white p-2 min-h-[44px] min-w-[44px]"
            aria-label="Tutup menu navigasi"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Navigation Items */}
        <nav className="flex-1 py-4 px-2.5 space-y-1 overflow-y-auto">
          {currentNav.map((item) => {
            const isExactOnly = item.href === '/courier' || item.href === '/store' || item.href === '/admin';
            const isActive =
              pathname === item.href ||
              (!isExactOnly && pathname.startsWith(`${item.href}/`));
            const Icon = item.icon;

            // Calculate badge and accessible label
            let badgeCount = 0;
            let badgeAriaLabel = item.label;
            if (userRole === 'store_admin' && sidebarCounts) {
              if (item.label === 'Kurir') {
                badgeCount = sidebarCounts.pelamar_kurir_pending || 0;
                if (badgeCount > 0) {
                  badgeAriaLabel = `Kurir, ${badgeCount} pelamar menunggu`;
                }
              } else if (item.label === 'Pesanan') {
                badgeCount = sidebarCounts.pesanan_baru || 0;
                if (badgeCount > 0) {
                  badgeAriaLabel = `Pesanan, ${badgeCount} pesanan baru`;
                }
              }
            }

            return (
              <Link
                key={item.href}
                href={item.href}
                onClick={() => setMobileOpen(false)}
                aria-label={badgeAriaLabel}
                className={cn(
                  'flex items-center gap-3 px-3 py-2.5 rounded-md text-xs font-medium transition-colors min-h-[40px]',
                  isActive
                    ? 'bg-white text-[#1A3A5C] font-semibold shadow-xs'
                    : 'text-slate-200 hover:bg-white/10 hover:text-white'
                )}
                title={collapsed ? badgeAriaLabel : undefined}
              >
                <Icon className={cn('w-4 h-4 shrink-0', isActive ? 'text-[#1A3A5C]' : 'text-slate-300')} />
                {!collapsed && <span className="truncate flex-1">{item.label}</span>}
                {!collapsed && badgeCount > 0 && (
                  <span className="ml-auto px-1.5 py-0.5 text-[10px] font-semibold text-slate-900 bg-slate-100 rounded-[6px] shrink-0 tabular-nums">
                    {badgeCount}
                  </span>
                )}
                {collapsed && badgeCount > 0 && (
                  <span className="w-2 h-2 rounded-full bg-slate-100 shrink-0" />
                )}
              </Link>
            );
          })}
        </nav>

        {/* Sidebar Footer & Collapse Toggle */}
        <div className="p-2.5 border-t border-white/10 space-y-1">
          <button
            onClick={() => setCollapsed(!collapsed)}
            className="hidden md:flex items-center justify-center w-full py-2 rounded-md text-slate-300 hover:bg-white/10 hover:text-white transition-colors text-xs font-medium min-h-[38px] cursor-pointer"
            aria-label={collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'}
          >
            {collapsed ? <ChevronRight className="w-4 h-4" /> : <ChevronLeft className="w-4 h-4 mr-1.5" />}
            {!collapsed && 'Ciutkan Menu'}
          </button>

          <button
            onClick={handleLogout}
            className={cn(
              'flex items-center gap-2.5 w-full px-3 py-2 rounded-md text-rose-300 hover:bg-rose-900/30 hover:text-rose-200 transition-colors text-xs font-medium min-h-[38px] cursor-pointer',
              collapsed && 'justify-center'
            )}
          >
            <LogOut className="w-4 h-4 shrink-0" />
            {!collapsed && <span>Keluar</span>}
          </button>
        </div>
      </aside>

      {/* Main Content Area */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Top Header Bar */}
        <header className="h-16 bg-white border-b border-slate-200 sticky top-0 z-30 px-4 sm:px-6 flex items-center justify-between">
          <div className="flex items-center gap-3 min-w-0">
            <button
              onClick={() => setMobileOpen(true)}
              className="md:hidden p-2 rounded-md text-slate-600 hover:bg-slate-100 min-h-[44px] min-w-[44px] flex items-center justify-center"
              aria-label="Buka menu navigasi"
            >
              <Menu className="w-5 h-5" />
            </button>

            {/* Store Switcher for Store Admin */}
            {userRole === 'store_admin' && activeStore ? (
              <div className="relative" ref={switcherRef}>
                {ownedStores.length > 1 ? (
                  <button
                    type="button"
                    onClick={() => setSwitcherOpen(!switcherOpen)}
                    className="flex items-center gap-2 px-2.5 py-1.5 rounded-md border border-slate-200 hover:bg-slate-50 transition-colors text-left cursor-pointer"
                    aria-label="Pilih Toko Aktif"
                    aria-expanded={switcherOpen}
                  >
                    <Store className="w-4 h-4 text-[#1A3A5C] shrink-0" />
                    <div className="min-w-0 max-w-[180px] sm:max-w-[240px]">
                      <div className="text-xs font-bold text-slate-900 truncate leading-tight">
                        {activeStore.name}
                      </div>
                      <div className="text-[10px] text-slate-500 truncate">
                        {ownedStores.length} Toko Terdaftar · Ganti Toko
                      </div>
                    </div>
                    <ChevronDown className="w-3.5 h-3.5 text-slate-400 shrink-0 ml-1" />
                  </button>
                ) : (
                  <div className="flex items-center gap-2">
                    <div className="w-8 h-8 rounded-md bg-slate-100 border border-slate-200 flex items-center justify-center text-[#1A3A5C]">
                      <Store className="w-4 h-4" />
                    </div>
                    <div className="min-w-0">
                      <div className="text-xs font-bold text-slate-900 truncate leading-tight">
                        {activeStore.name}
                      </div>
                      <div className="text-[10px] text-slate-500">Admin Toko</div>
                    </div>
                  </div>
                )}

                {/* Dropdown Menu for Switching Store */}
                {switcherOpen && ownedStores.length > 1 && (
                  <div className="absolute left-0 mt-1 w-64 bg-white rounded-md border border-slate-200 shadow-lg py-1 z-50 animate-fadeIn text-xs">
                    <div className="px-3 py-1.5 text-[10px] font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                      Pilih Toko Aktif
                    </div>
                    {ownedStores.map((s) => {
                      const isCurrent = s.id === activeStore.id;
                      return (
                        <button
                          key={s.id}
                          type="button"
                          onClick={() => handleSelectStore(s.id)}
                          className={cn(
                            'w-full flex items-center justify-between px-3 py-2 text-left transition-colors cursor-pointer hover:bg-slate-50',
                            isCurrent && 'bg-slate-50 font-semibold text-[#1A3A5C]'
                          )}
                        >
                          <div className="min-w-0 flex-1 pr-2">
                            <div className="truncate text-slate-900">{s.name}</div>
                            <div className="text-[10px] text-slate-500 capitalize">
                              Status: {s.operational_status}
                            </div>
                          </div>
                          {isCurrent && <Check className="w-4 h-4 text-[#1A3A5C] shrink-0" />}
                        </button>
                      );
                    })}
                  </div>
                )}
              </div>
            ) : (
              <div className="flex items-center gap-3">
                <h1 className="font-bold text-base text-slate-900 hidden sm:block">
                  {userRole === 'courier' ? 'Dashboard Kurir' : 'Portal LaundryKu'}
                </h1>
                {userRole === 'courier' && (
                  <button
                    type="button"
                    onClick={async () => {
                      try {
                        await switchUserMode('customer');
                      } catch {
                        // ignore
                      }
                      router.push('/stores');
                    }}
                    className="text-xs px-2.5 py-1 rounded-md border border-slate-300 text-slate-700 bg-white hover:bg-slate-50 transition-colors cursor-pointer"
                  >
                    Kembali ke Customer
                  </button>
                )}
              </div>
            )}
          </div>

          {/* Right Header Actions */}
          <div className="flex items-center gap-3">
            {/* Mode Switcher */}
            {mounted && availableModes.length > 1 && (
              <div className="flex items-center bg-slate-100 p-0.5 rounded-md border border-slate-200 text-[11px] mr-1">
                {availableModes.includes('customer') && (
                  <button
                    type="button"
                    onClick={async () => {
                      try {
                        await switchUserMode('customer');
                      } catch {
                        // ignore
                      }
                      router.push('/stores');
                    }}
                    className={cn(
                      'px-2 py-0.5 rounded text-[11px] font-medium transition-colors cursor-pointer',
                      user?.active_mode === 'customer'
                        ? 'bg-white text-slate-900 shadow-xs font-semibold'
                        : 'text-slate-500 hover:text-slate-800'
                    )}
                  >
                    Pelanggan
                  </button>
                )}
                {availableModes.includes('store') && (
                  <button
                    type="button"
                    onClick={async () => {
                      try {
                        await switchUserMode('store');
                      } catch {
                        // ignore
                      }
                      router.push('/store');
                    }}
                    className={cn(
                      'px-2 py-0.5 rounded text-[11px] font-medium transition-colors cursor-pointer',
                      user?.active_mode === 'store' || (!user?.active_mode && userRole === 'store_admin')
                        ? 'bg-white text-slate-900 shadow-xs font-semibold'
                        : 'text-slate-500 hover:text-slate-800'
                    )}
                  >
                    Toko
                  </button>
                )}
                {availableModes.includes('courier') && (
                  <button
                    type="button"
                    onClick={async () => {
                      try {
                        await switchUserMode('courier');
                      } catch {
                        // ignore
                      }
                      router.push('/courier');
                    }}
                    className={cn(
                      'px-2 py-0.5 rounded text-[11px] font-medium transition-colors cursor-pointer',
                      user?.active_mode === 'courier'
                        ? 'bg-white text-slate-900 shadow-xs font-semibold'
                        : 'text-slate-500 hover:text-slate-800'
                    )}
                  >
                    Kurir
                  </button>
                )}
              </div>
            )}

            {/* Operational Status Toggle for Store Admin */}
            {userRole === 'store_admin' && activeStore && (
              <div className="flex items-center gap-2">
                {isSuspended ? (
                  <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                    <AlertTriangle className="w-3 h-3" />
                    <span>Ditangguhkan</span>
                  </span>
                ) : (
                  <button
                    type="button"
                    onClick={handleToggleStatus}
                    disabled={isUpdatingStatus}
                    className={cn(
                      'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold border transition-colors cursor-pointer disabled:opacity-50',
                      isOpen
                        ? 'bg-emerald-50 text-emerald-800 border-emerald-200 hover:bg-emerald-100'
                        : 'bg-slate-100 text-slate-700 border-slate-300 hover:bg-slate-200'
                    )}
                    aria-label={`Ubah status toko, saat ini ${isOpen ? 'Buka' : 'Tutup'}`}
                    title="Klik untuk mengubah status operasional toko"
                  >
                    <Power className={cn('w-3.5 h-3.5', isOpen ? 'text-emerald-600' : 'text-slate-400')} />
                    <span>{isOpen ? 'Toko Buka' : 'Toko Tutup'}</span>
                  </button>
                )}
              </div>
            )}

            {/* Chat Icon Button */}
            <Link
              href={userRole === 'store_admin' ? '/store/chat' : '/chat'}
              className={cn(
                'relative p-2 rounded-md transition-colors border text-slate-600 hover:text-slate-900 hover:bg-slate-50 border-slate-200',
                (pathname === '/chat' || pathname.startsWith('/chat/') || pathname === '/store/chat' || pathname.startsWith('/store/chat/'))
                  ? 'bg-slate-100 text-slate-900 border-slate-300'
                  : ''
              )}
              aria-label="Pesan Chat"
              title="Pesan Chat"
            >
              <MessageSquare className="w-4 h-4" />
              {unreadChatCount > 0 && (
                <span className="absolute -top-1 -right-1 min-w-[16px] h-[16px] px-1 bg-rose-600 text-white text-[9px] font-bold rounded-full flex items-center justify-center">
                  {unreadChatCount > 9 ? '9+' : unreadChatCount}
                </span>
              )}
            </Link>

            {/* Profile Dropdown */}
            <Dropdown
              trigger={
                <div className="flex items-center gap-2 pl-2 pr-1 py-1 rounded-md border border-slate-200 hover:bg-slate-50 transition-colors cursor-pointer text-xs">
                  <Avatar src={resolvedAvatar} name={resolvedUserName} size="sm" className="w-6 h-6" />
                  <span className="font-medium text-slate-800 max-w-[100px] truncate hidden sm:inline-block">
                    {resolvedUserName}
                  </span>
                  <ChevronDown className="w-3.5 h-3.5 text-slate-400" />
                </div>
              }
              items={[
                ...(isStorePortal
                  ? [{ id: 'profile', label: 'Pengaturan Toko', onClick: () => router.push('/store/settings') }]
                  : userRole !== 'courier'
                  ? [{ id: 'profile', label: 'Profil Saya', onClick: () => router.push('/profile') }]
                  : []),
                ...(isStorePortal
                  ? [{ id: 'marketplace', label: 'Mode Pelanggan', onClick: () => router.push('/stores') }]
                  : [
                      {
                        id: 'marketplace',
                        label: 'Cari Toko',
                        onClick: async () => {
                          try {
                            await switchUserMode('customer');
                          } catch {
                            // ignore
                          }
                          router.push('/stores');
                        },
                      },
                    ]),
                { id: 'div-1', label: '', divider: true },
                { id: 'logout', label: 'Keluar', danger: true, onClick: handleLogout },
              ]}
            />
          </div>
        </header>

        {/* Suspended Alert Banner */}
        {userRole === 'store_admin' && isSuspended && (
          <div className="bg-rose-50 border-b border-rose-200 px-4 sm:px-6 py-3 flex items-start gap-3 text-xs text-rose-900">
            <AlertTriangle className="w-4 h-4 text-rose-600 shrink-0 mt-0.5" />
            <div className="space-y-0.5 flex-1">
              <div className="font-bold">Toko Anda Sedang Ditangguhkan</div>
              <p className="text-rose-700 leading-relaxed">
                {activeStore?.rejection_reason
                  ? `Alasan penangguhan: ${activeStore.rejection_reason}. `
                  : 'Toko Anda dinonaktifkan sementara oleh Administrator Platform. '}
                Toko tidak menerima order baru dan tidak tampil di katalog marketplace, namun pesanan aktif yang sedang berjalan tetap dapat diselesaikan.
              </p>
            </div>
          </div>
        )}

        {/* Page Content Body */}
        <main className="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
          {children}
        </main>
      </div>

      {/* AI Assistant Chatbot Widget */}
      <AiChatWidget />
    </div>
  );
};
