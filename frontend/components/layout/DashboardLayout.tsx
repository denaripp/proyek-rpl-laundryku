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
  CreditCard,
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

const isUserEqual = (a: AuthUser | null, b: AuthUser | null): boolean => {
  if (a === b) return true;
  if (!a || !b) return false;
  return (
    a.id === b.id &&
    a.name === b.name &&
    a.email === b.email &&
    a.avatar_url === b.avatar_url
  );
};

const isStoreEqual = (a: OwnedStoreItem | null, b: OwnedStoreItem | null): boolean => {
  if (a === b) return true;
  if (!a || !b) return false;
  return (
    a.id === b.id &&
    a.name === b.name &&
    a.operational_status === b.operational_status &&
    a.approval_status === b.approval_status
  );
};

const areStoresEqual = (a: OwnedStoreItem[], b: OwnedStoreItem[]): boolean => {
  if (a === b) return true;
  if (a.length !== b.length) return false;
  return a.every(
    (item, idx) =>
      item.id === b[idx]?.id &&
      item.name === b[idx]?.name &&
      item.operational_status === b[idx]?.operational_status &&
      item.approval_status === b[idx]?.approval_status
  );
};

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
  const isStorePortal = userRole === 'store_admin' || pathname === '/store' || pathname.startsWith('/store/');
  const isCourierPortal = userRole === 'courier' || pathname === '/courier' || pathname.startsWith('/courier/');
  const targetPortal = isStorePortal ? 'mitra' : isCourierPortal ? 'courier' : 'customer';

  const [mounted, setMounted] = useState(false);
  const [portalUser, setPortalUser] = useState<AuthUser | null>(null);

  const { user } = useAuth();

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
      setSidebarCounts((prev) => {
        if (!prev && !counts) return prev;
        if (
          prev &&
          counts &&
          prev.pelamar_kurir_pending === counts.pelamar_kurir_pending &&
          prev.pesanan_baru === counts.pesanan_baru &&
          prev.unread_chat === counts.unread_chat &&
          prev.store_id === counts.store_id
        ) {
          return prev;
        }
        return counts;
      });
    } catch {
      // ignore
    }
  }, [userRole, storeId]);

  useEffect(() => {
    fetchSidebarCounts();
    const interval = setInterval(fetchSidebarCounts, 10000);

    const handleRefresh = () => {
      fetchSidebarCounts();
    };

    window.addEventListener('laundryku_sidebar_count_refresh', handleRefresh);
    window.addEventListener('laundryku_active_store_change', handleRefresh);
    window.addEventListener('laundryku_store_changed', handleRefresh);

    return () => {
      clearInterval(interval);
      window.removeEventListener('laundryku_sidebar_count_refresh', handleRefresh);
      window.removeEventListener('laundryku_active_store_change', handleRefresh);
      window.removeEventListener('laundryku_store_changed', handleRefresh);
    };
  }, [fetchSidebarCounts]);

  // Synchronize store list and current active store
  const syncStores = useCallback((currentUser: AuthUser | null) => {
    if (!currentUser) return;
    const stores = getApprovedStores(currentUser);
    setOwnedStores((prev) => (areStoresEqual(prev, stores) ? prev : stores));

    if (stores.length > 0) {
      const currentActiveId = getActiveStoreId();
      const matched = stores.find((s) => s.id === currentActiveId) || stores[0];
      setActiveStore((prev) => (isStoreEqual(prev, matched) ? prev : matched));
      if (currentActiveId !== matched.id) {
        setActiveStoreId(matched.id);
      }
    } else {
      setActiveStore(null);
    }
  }, []);

  // Hydrate portal-specific user from localStorage immediately on mount
  useEffect(() => {
    setMounted(true);
    let cancelled = false;

    const stored = getStoredUser(targetPortal);
    if (stored) {
      setPortalUser((prev) => (isUserEqual(prev, stored) ? prev : stored));
      if (stored.avatar_url) {
        setActiveAvatar(stored.avatar_url);
      }
      syncStores(stored);
    }

    if (isStorePortal || isCourierPortal) {
      fetchPortalSession(targetPortal).then((freshUser) => {
        if (!cancelled && freshUser) {
          setPortalUser((prev) => (isUserEqual(prev, freshUser) ? prev : freshUser));
          if (freshUser.avatar_url) {
            setActiveAvatar(freshUser.avatar_url);
          }
          syncStores(freshUser);
        }
      });
    }

    return () => {
      cancelled = true;
    };
  }, [targetPortal, isStorePortal, isCourierPortal, syncStores]);

  useEffect(() => {
    if (isStorePortal || isCourierPortal) {
      const handleStoreChange = () => {
        const u = getStoredUser(targetPortal);
        if (u) syncStores(u);
      };
      window.addEventListener('laundryku_store_changed', handleStoreChange);
      window.addEventListener('laundryku_active_store_change', handleStoreChange);
      return () => {
        window.removeEventListener('laundryku_store_changed', handleStoreChange);
        window.removeEventListener('laundryku_active_store_change', handleStoreChange);
      };
    }
  }, [isStorePortal, isCourierPortal, targetPortal, syncStores]);

  // Handle immediate avatar updates
  useEffect(() => {
    const handleUserUpdate = (e: Event) => {
      const customEvent = e as CustomEvent<AuthUser | null>;
      if (customEvent.detail !== undefined) {
        setPortalUser((prev) => (isUserEqual(prev, customEvent.detail) ? prev : customEvent.detail));
        if (customEvent.detail?.avatar_url !== undefined) {
          setActiveAvatar(customEvent.detail.avatar_url);
        }
        syncStores(customEvent.detail);
      }
    };

    window.addEventListener('auth:user-updated', handleUserUpdate);
    window.addEventListener('laundryku_auth_change', handleUserUpdate);

    return () => {
      window.removeEventListener('auth:user-updated', handleUserUpdate);
      window.removeEventListener('laundryku_auth_change', handleUserUpdate);
    };
  }, [isStorePortal, isCourierPortal, targetPortal, syncStores]);

  // Click outside listener for store switcher dropdown
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (switcherRef.current && !switcherRef.current.contains(e.target as Node)) {
        setSwitcherOpen(false);
      }
    };

    if (switcherOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [switcherOpen]);

  const fetchUnread = useCallback(async () => {
    if (userRole !== 'store_admin') return;
    const count = await getUnreadChatCount();
    setUnreadChatCount(count);
  }, [userRole]);

  useEffect(() => {
    if (userRole !== 'store_admin') return;
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
  }, [fetchUnread, userRole]);

  // Handle switching active store
  const handleSelectStore = (storeId: string) => {
    setActiveStoreId(storeId);
    const matched = ownedStores.find((s) => s.id === storeId);
    if (matched) {
      setActiveStore(matched);
      window.dispatchEvent(new CustomEvent('laundryku_active_store_change', { detail: storeId }));
      window.dispatchEvent(new Event('laundryku_store_changed'));
    }
    setSwitcherOpen(false);
  };

  // Handle operational status toggle (Active <-> Inactive)
  const handleToggleStatus = async () => {
    if (!activeStore || isUpdatingStatus) return;
    const currentActive = activeStore.operational_status === 'active';
    const nextActive = !currentActive;

    setIsUpdatingStatus(true);
    try {
      const updated = await toggleStoreOperationalStatus(activeStore.id, nextActive ? 'active' : 'inactive');
      const newStatus = updated.operational_status;
      setActiveStore((prev) => (prev ? { ...prev, operational_status: newStatus } : null));
      setOwnedStores((prev) =>
        prev.map((s) => (s.id === activeStore.id ? { ...s, operational_status: newStatus } : s))
      );
      window.dispatchEvent(new Event('laundryku_store_changed'));
    } catch {
      // Handled silently
    } finally {
      setIsUpdatingStatus(false);
    }
  };

  const handleLogout = useCallback(async () => {
    if (onLogout) {
      onLogout();
      return;
    }

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
  }, [isStorePortal, isCourierPortal, onLogout, router, userRole]);

  const roleNavItems: Record<UserRole, { label: string; href: string; icon: React.ComponentType<{ className?: string; strokeWidth?: number }> }[]> = {
    customer: [
      { label: 'Ringkasan', href: '/profile', icon: LayoutDashboard },
      { label: 'Alamat saya', href: '/profile#alamat', icon: MapPin },
      { label: 'Gabung mitra', href: '/upgrade', icon: ShieldCheck },
    ],
    courier: [
      { label: 'Dashboard kurir', href: '/courier', icon: Truck },
      { label: 'Tugas pengantaran', href: '/courier/tasks', icon: ShoppingBag },
    ],
    store_admin: [
      { label: 'Dashboard', href: '/store', icon: LayoutDashboard },
      { label: 'Pesanan', href: '/store/orders', icon: ShoppingBag },
      { label: 'Layanan', href: '/store/services', icon: Sparkles },
      { label: 'Harga', href: '/store/pricing', icon: Tag },
      { label: 'Pembayaran', href: '/store/payment', icon: CreditCard },
      { label: 'Kurir', href: '/store/couriers', icon: Truck },
      { label: 'Laporan', href: '/store/reports', icon: BarChart3 },
      { label: 'Pengaturan', href: '/store/settings', icon: Settings },
    ],
    super_admin: [
      { label: 'Ringkasan admin', href: '/admin', icon: LayoutDashboard },
      { label: 'Kelola toko', href: '/admin/stores', icon: Store },
      { label: 'Kelola user', href: '/admin/users', icon: User },
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
    : 'Pelanggan';

  const serverFallbackEmail = isStorePortal
    ? 'admin@toko.id'
    : isCourierPortal
    ? 'kurir@laundryku.id'
    : 'customer@laundryku.id';

  const displayUser = isStorePortal || isCourierPortal ? portalUser : user;

  const resolvedUserName = mounted && displayUser?.name
    ? displayUser.name
    : mounted && userName !== 'Pelanggan LaundryKu' && userName !== 'Admin Toko' && userName !== 'Kurir LaundryKu'
    ? userName
    : serverFallbackName;

  const resolvedAvatar = mounted
    ? (displayUser?.avatar_url || activeAvatar || userAvatar)
    : (userAvatar || null);

  const getRoleBadgeLabel = () => {
    if (userRole === 'store_admin') return 'Mitra toko';
    if (userRole === 'courier') return 'Kurir';
    if (userRole === 'super_admin') return 'Admin platform';
    return 'Pelanggan';
  };

  return (
    <div className="min-h-screen bg-slate-50 flex font-sans text-slate-900">
      {/* Mobile Backdrop */}
      {mobileOpen && (
        <div
          className="fixed inset-0 bg-slate-950/60 backdrop-blur-xs z-40 md:hidden"
          onClick={() => setMobileOpen(false)}
        />
      )}

      {/* Sidebar (Desktop & Mobile Drawer) - Command Workspace Theme */}
      <aside
        className={cn(
          'fixed md:static top-0 bottom-0 left-0 z-50 flex flex-col bg-[#0b1728] text-white transition-all duration-300 shadow-xl md:shadow-none border-r border-white/5 select-none',
          collapsed ? 'w-20' : 'w-64',
          mobileOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'
        )}
      >
        {/* Brand Header */}
        <div className="h-16 flex items-center justify-between px-4 border-b border-white/10">
          <Link href="/" className="flex items-center gap-3 overflow-hidden">
            <div className="w-8 h-8 rounded-xl bg-navy-800 border border-sky-400/30 flex items-center justify-center shrink-0 text-white font-bold text-xs tracking-wider shadow-2xs">
              LK
            </div>
            {!collapsed && (
              <div className="flex flex-col truncate">
                <span className="font-heading font-extrabold text-base text-white tracking-tight">
                  Laundry<span className="text-sky-300">Ku</span>
                </span>
                <span className="inline-flex items-center text-[10px] font-semibold text-slate-300 bg-white/10 px-2 py-0.2 rounded-md border border-white/10 mt-0.5 self-start">
                  {getRoleBadgeLabel()}
                </span>
              </div>
            )}
          </Link>
          <button
            onClick={() => setMobileOpen(false)}
            className="md:hidden text-slate-400 hover:text-white p-2 rounded-lg min-h-[44px] min-w-[44px] flex items-center justify-center cursor-pointer"
            aria-label="Tutup menu navigasi"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Navigation Items */}
        <nav className="flex-1 py-4 px-3 space-y-1 overflow-y-auto [scrollbar-width:thin] [scrollbar-color:#334155_transparent]">
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
                  'flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs transition-all min-h-[40px]',
                  isActive
                    ? 'bg-white text-navy-950 font-bold shadow-2xs'
                    : 'text-slate-300 hover:bg-white/10 hover:text-white font-medium'
                )}
                title={collapsed ? badgeAriaLabel : undefined}
              >
                <Icon
                  className={cn('w-4 h-4 shrink-0', isActive ? 'text-navy-900' : 'text-slate-300')}
                  strokeWidth={isActive ? 2 : 1.75}
                />
                {!collapsed && <span className="truncate flex-1">{item.label}</span>}
                {!collapsed && badgeCount > 0 && (
                  <span
                    className={cn(
                      'ml-auto px-2 py-0.5 text-[10px] font-bold rounded-lg shrink-0 tabular-nums font-mono',
                      isActive ? 'bg-navy-100 text-navy-950' : 'bg-navy-800 text-sky-200 border border-navy-700/80'
                    )}
                  >
                    {badgeCount}
                  </span>
                )}
                {collapsed && badgeCount > 0 && (
                  <span className="w-2 h-2 rounded-full bg-sky-400 shrink-0" />
                )}
              </Link>
            );
          })}
        </nav>

        {/* Sidebar Footer & Collapse Toggle */}
        <div className="p-3 border-t border-white/10 space-y-1">
          <button
            onClick={() => setCollapsed(!collapsed)}
            className="hidden md:flex items-center justify-center w-full py-2 px-3 rounded-xl text-slate-300 hover:bg-white/10 hover:text-white transition-all text-xs font-semibold min-h-[38px] cursor-pointer"
            aria-label={collapsed ? 'Perluas sidebar' : 'Ciutkan sidebar'}
          >
            {collapsed ? <ChevronRight className="w-4 h-4" /> : <ChevronLeft className="w-4 h-4 mr-1.5" />}
            {!collapsed && 'Ciutkan menu'}
          </button>

          <button
            onClick={handleLogout}
            className={cn(
              'flex items-center gap-2.5 w-full px-3 py-2 rounded-xl text-rose-300 hover:bg-rose-950/40 hover:text-rose-200 transition-all text-xs font-semibold min-h-[38px] cursor-pointer',
              collapsed && 'justify-center'
            )}
          >
            <LogOut className="w-4 h-4 shrink-0" strokeWidth={1.75} />
            {!collapsed && <span>Keluar</span>}
          </button>
        </div>
      </aside>

      {/* Main Content Area */}
      <div className="flex-1 flex flex-col min-w-0">
        {/* Top Header Bar - Command Workspace Sticky Bar */}
        <header className="h-16 bg-white/95 backdrop-blur-md border-b border-slate-200/90 sticky top-0 z-30 px-4 sm:px-6 flex items-center justify-between shadow-2xs select-none">
          <div className="flex items-center gap-3 min-w-0">
            <button
              onClick={() => setMobileOpen(true)}
              className="md:hidden p-2 rounded-xl text-slate-700 hover:bg-slate-100 border border-slate-200 min-h-[44px] min-w-[44px] flex items-center justify-center cursor-pointer shadow-2xs"
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
                    className="flex items-center gap-2.5 px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 transition-all text-left cursor-pointer shadow-2xs"
                    aria-label="Pilih Toko Aktif"
                    aria-expanded={switcherOpen}
                  >
                    <Store className="w-4 h-4 text-navy-700 shrink-0" strokeWidth={1.75} />
                    <div className="min-w-0 max-w-[180px] sm:max-w-[240px]">
                      <div className="text-xs font-bold text-navy-900 truncate leading-tight">
                        {activeStore.name}
                      </div>
                      <div className="text-[10px] text-slate-500 truncate">
                        {ownedStores.length} toko terdaftar · Ganti toko
                      </div>
                    </div>
                    <ChevronDown className="w-3.5 h-3.5 text-slate-400 shrink-0 ml-1" />
                  </button>
                ) : (
                  <div className="flex items-center gap-2.5">
                    <div className="w-8 h-8 rounded-xl bg-navy-50 border border-navy-100 flex items-center justify-center text-navy-700 shrink-0">
                      <Store className="w-4 h-4" strokeWidth={1.75} />
                    </div>
                    <div className="min-w-0">
                      <div className="text-xs font-bold text-navy-900 truncate leading-tight">
                        {activeStore.name}
                      </div>
                      <div className="text-[10px] text-slate-500">Mitra toko</div>
                    </div>
                  </div>
                )}

                {/* Dropdown Menu for Switching Store */}
                {switcherOpen && ownedStores.length > 1 && (
                  <div className="absolute left-0 mt-1.5 w-64 bg-white rounded-2xl border border-slate-200/90 shadow-lg py-1.5 z-50 animate-fadeIn text-xs">
                    <div className="px-3.5 py-1.5 text-[10px] font-bold text-slate-400 uppercase tracking-wider border-b border-slate-100">
                      Pilih toko aktif
                    </div>
                    <div className="py-1 max-h-60 overflow-y-auto">
                      {ownedStores.map((s) => {
                        const isCurrent = s.id === activeStore.id;
                        return (
                          <button
                            key={s.id}
                            type="button"
                            onClick={() => handleSelectStore(s.id)}
                            className={cn(
                              'w-full flex items-center justify-between px-3.5 py-2 text-left transition-colors cursor-pointer hover:bg-slate-50',
                              isCurrent && 'bg-navy-50/70 font-bold text-navy-900'
                            )}
                          >
                            <div className="min-w-0 flex-1 pr-2">
                              <div className="truncate text-slate-900">{s.name}</div>
                              <div className="text-[10px] text-slate-500 capitalize">
                                Status: {s.operational_status}
                              </div>
                            </div>
                            {isCurrent && <Check className="w-4 h-4 text-navy-700 shrink-0" strokeWidth={2} />}
                          </button>
                        );
                      })}
                    </div>
                  </div>
                )}
              </div>
            ) : userRole === 'courier' ? (
              <div className="flex items-center gap-2.5">
                <div className="w-8 h-8 rounded-xl bg-navy-50 text-navy-800 border border-navy-100 flex items-center justify-center shrink-0">
                  <Truck className="w-4 h-4 text-navy-700" strokeWidth={1.75} />
                </div>
                <div>
                  <h1 className="font-heading font-bold text-sm sm:text-base text-navy-900 leading-tight">
                    Dashboard kurir
                  </h1>
                </div>
              </div>
            ) : userRole === 'super_admin' ? (
              <div className="flex items-center gap-2.5">
                <div className="w-8 h-8 rounded-xl bg-navy-50 text-navy-800 border border-navy-100 flex items-center justify-center shrink-0">
                  <ShieldCheck className="w-4 h-4 text-navy-700" strokeWidth={1.75} />
                </div>
                <div>
                  <h1 className="font-heading font-bold text-sm sm:text-base text-navy-900 leading-tight">
                    Admin platform
                  </h1>
                </div>
              </div>
            ) : (
              <div className="flex items-center gap-3">
                <h1 className="font-heading font-bold text-base text-navy-900 hidden sm:block">
                  Portal LaundryKu
                </h1>
              </div>
            )}
          </div>

          {/* Right Header Actions */}
          <div className="flex items-center gap-3">
            {/* Operational Status Toggle for Store Admin */}
            {userRole === 'store_admin' && activeStore && (
              <div className="flex items-center gap-2">
                {isSuspended ? (
                  <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-rose-50 text-rose-800 border border-rose-200 shadow-2xs">
                    <AlertTriangle className="w-3.5 h-3.5 text-rose-600" strokeWidth={2} />
                    <span>Ditangguhkan</span>
                  </span>
                ) : (
                  <button
                    type="button"
                    onClick={handleToggleStatus}
                    disabled={isUpdatingStatus}
                    className={cn(
                      'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold border transition-all cursor-pointer disabled:opacity-50 shadow-2xs active:scale-95',
                      isOpen
                        ? 'bg-emerald-50 text-emerald-800 border-emerald-200 hover:bg-emerald-100'
                        : 'bg-slate-100 text-slate-700 border-slate-300 hover:bg-slate-200'
                    )}
                    aria-label={`Ubah status toko, saat ini ${isOpen ? 'Buka' : 'Tutup'}`}
                    title="Klik untuk mengubah status operasional toko"
                  >
                    <Power className={cn('w-3.5 h-3.5', isOpen ? 'text-emerald-600' : 'text-slate-500')} strokeWidth={2} />
                    <span>{isOpen ? 'Toko buka' : 'Toko tutup'}</span>
                  </button>
                )}
              </div>
            )}

            {/* Chat Icon Button (Only for store_admin) */}
            {userRole === 'store_admin' && (
              <Link
                href="/store/chat"
                className={cn(
                  'relative p-2 rounded-xl transition-all border text-slate-600 hover:text-navy-900 hover:bg-slate-50 border-slate-200 bg-white shadow-2xs active:scale-95 flex items-center justify-center min-h-[36px] min-w-[36px]',
                  (pathname === '/store/chat' || pathname.startsWith('/store/chat/'))
                    ? 'bg-navy-50 text-navy-900 border-navy-200 font-semibold'
                    : ''
                )}
                aria-label="Pesan chat"
                title="Pesan chat"
              >
                <MessageSquare className="w-4 h-4" strokeWidth={1.75} />
                {unreadChatCount > 0 && (
                  <span className="absolute -top-1 -right-1 min-w-[16px] h-[16px] px-1 bg-rose-600 text-white text-[9px] font-bold rounded-full flex items-center justify-center shadow-xs">
                    {unreadChatCount > 9 ? '9+' : unreadChatCount}
                  </span>
                )}
              </Link>
            )}

            {/* Profile Dropdown */}
            <Dropdown
              trigger={
                <div className="flex items-center gap-2 pl-2 pr-2.5 py-1 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 transition-all cursor-pointer text-xs shadow-2xs">
                  <Avatar src={resolvedAvatar} name={resolvedUserName} size="sm" className="w-6 h-6 rounded-lg" />
                  <span className="font-semibold text-slate-800 max-w-[110px] truncate hidden sm:inline-block">
                    {resolvedUserName}
                  </span>
                  <ChevronDown className="w-3.5 h-3.5 text-slate-400" />
                </div>
              }
              items={[
                ...(isStorePortal
                  ? [{ id: 'profile', label: 'Pengaturan toko', onClick: () => router.push('/store/settings') }]
                  : userRole !== 'courier'
                  ? [{ id: 'profile', label: 'Profil saya', onClick: () => router.push('/profile') }]
                  : []),
                { id: 'div-1', label: '', divider: true },
                { id: 'logout', label: 'Keluar', danger: true, onClick: handleLogout },
              ]}
            />
          </div>
        </header>

        {/* Suspended Alert Banner */}
        {userRole === 'store_admin' && isSuspended && (
          <div className="mx-4 sm:mx-6 lg:mx-8 mt-4 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-xs text-rose-900 shadow-2xs flex items-start gap-3">
            <AlertTriangle className="w-4 h-4 text-rose-600 shrink-0 mt-0.5" strokeWidth={2} />
            <div className="space-y-0.5 flex-1">
              <div className="font-bold">Toko Anda sedang ditangguhkan</div>
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
