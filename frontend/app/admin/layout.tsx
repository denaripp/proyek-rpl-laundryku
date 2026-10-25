'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { hasRole, getSafeRedirectPath } from '@/lib/auth';
import { adminApi, ADMIN_LOGIN_PATH } from '@/lib/admin';
import {
  LayoutDashboard,
  Package,
  CreditCard,
  Store,
  Users,
  Tag,
  Sliders,
  ShieldCheck,
  LogOut,
  Menu,
  X,
  Shield,
} from 'lucide-react';
import { Avatar } from '@/components/ui/Avatar';
import { AuthUser } from '@/lib/auth';

interface AdminLayoutProps {
  children: React.ReactNode;
}

const navItems = [
  { href: '/admin', label: 'Dashboard', icon: LayoutDashboard },
  { href: '/admin/orders', label: 'Pesanan', icon: Package },
  { href: '/admin/payments', label: 'Transaksi & Bayar', icon: CreditCard },
  { href: '/admin/stores', label: 'Persetujuan Toko', icon: Store },
  { href: '/admin/users', label: 'Manajemen Pengguna', icon: Users },
  { href: '/admin/categories', label: 'Kategori Layanan', icon: Tag },
  { href: '/admin/settings', label: 'Komisi Platform', icon: Sliders },
  { href: '/admin/audit-logs', label: 'Audit Log', icon: ShieldCheck },
];

interface SidebarNavProps {
  pathname: string;
  userName: string;
  userAvatar?: string | null;
  onClose?: () => void;
  onLogout: () => void;
}

function SidebarNav({ pathname, userName, userAvatar, onClose, onLogout }: SidebarNavProps) {
  return (
    <div className="flex flex-col h-full bg-[#0F172A] text-slate-300">
      {/* Brand Header */}
      <div className="p-4 border-b border-slate-800/80 flex items-center justify-between">
        <Link href="/admin" className="flex items-center gap-2.5">
          <div className="w-7 h-7 rounded bg-slate-800 border border-slate-700 flex items-center justify-center text-slate-200">
            <Shield className="w-4 h-4 text-slate-200" strokeWidth={1.5} />
          </div>
          <div>
            <div className="text-sm font-semibold text-white tracking-tight leading-tight">LaundryKu</div>
            <div className="text-[10px] text-slate-400 font-mono tracking-wider">SUPER ADMIN</div>
          </div>
        </Link>
        {onClose && (
          <button
            onClick={onClose}
            className="md:hidden p-1 text-slate-400 hover:text-white"
            aria-label="Tutup menu"
          >
            <X className="w-5 h-5" strokeWidth={1.5} />
          </button>
        )}
      </div>

      {/* Navigation List */}
      <nav className="flex-1 p-3 space-y-1 overflow-y-auto">
        {navItems.map((item) => {
          const Icon = item.icon;
          const isActive = pathname === item.href;
          return (
            <Link
              key={item.href}
              href={item.href}
              className={`flex items-center gap-2.5 px-3 py-2 rounded-md text-xs font-medium transition-colors ${
                isActive
                  ? 'bg-slate-800/90 text-white border-l-2 border-slate-400 pl-2.5'
                  : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/40'
              }`}
            >
              <Icon className={`w-4 h-4 shrink-0 ${isActive ? 'text-slate-200' : 'text-slate-400'}`} strokeWidth={1.5} />
              <span>{item.label}</span>
            </Link>
          );
        })}
      </nav>

      {/* User Footer */}
      <div className="p-3 border-t border-slate-800/80 bg-slate-950/40">
        <div className="flex items-center gap-2.5 px-2 py-1.5 mb-2">
          <Avatar src={userAvatar} name={userName || 'Super Admin'} size="sm" className="shrink-0" />
          <div className="min-w-0 flex-1">
            <div className="text-[11px] font-medium text-slate-300 truncate">{userName || 'Super Admin'}</div>
            <div className="text-[10px] text-slate-500 font-mono">super_admin</div>
          </div>
        </div>
        <button
          onClick={onLogout}
          className="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-md text-xs font-medium text-slate-400 hover:text-rose-300 hover:bg-rose-950/30 transition-colors cursor-pointer"
        >
          <LogOut className="w-3.5 h-3.5 shrink-0" strokeWidth={1.5} />
          <span>Keluar</span>
        </button>
      </div>
    </div>
  );
}

export default function AdminLayout({ children }: AdminLayoutProps) {
  const pathname = usePathname();
  const router = useRouter();
  const [authorized, setAuthorized] = useState<boolean | null>(null);
  const [userName, setUserName] = useState<string>('');
  const [userAvatar, setUserAvatar] = useState<string | null>(null);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  // If on admin login page, bypass layout shell
  const isLoginPage = pathname === ADMIN_LOGIN_PATH;

  useEffect(() => {
    if (isLoginPage) {
      setAuthorized(true);
      return;
    }

    let isMounted = true;

    async function checkAdminSession() {
      try {
        const res = await fetch('/api/auth/admin/session', {
          headers: { Accept: 'application/json' },
          cache: 'no-store',
        });

        if (!isMounted) return;

        if (!res.ok) {
          const safeRedirect = getSafeRedirectPath(pathname, '/admin');
          router.push(`${ADMIN_LOGIN_PATH}?redirect=${encodeURIComponent(safeRedirect)}`);
          return;
        }

        const json = await res.json().catch(() => ({}));
        const user: AuthUser | null = json.user || null;

        if (!user || !hasRole(user, 'super_admin')) {
          setAuthorized(false);
          return;
        }

        setAuthorized(true);
        setUserName(user.name);
        setUserAvatar(user.avatar_url || null);
      } catch {
        if (isMounted) {
          setAuthorized(false);
        }
      }
    }

    checkAdminSession();

    const handleUserUpdate = (e: Event) => {
      const customEvent = e as CustomEvent<AuthUser | null>;
      if (customEvent.detail && hasRole(customEvent.detail, 'super_admin')) {
        setUserAvatar(customEvent.detail.avatar_url || null);
        setUserName(customEvent.detail.name);
      }
    };

    window.addEventListener('auth:user-updated', handleUserUpdate);
    return () => {
      isMounted = false;
      window.removeEventListener('auth:user-updated', handleUserUpdate);
    };
  }, [pathname, isLoginPage, router]);

  // Close mobile drawer on route change
  useEffect(() => {
    setMobileMenuOpen(false);
  }, [pathname]);

  if (isLoginPage) {
    return <>{children}</>;
  }

  if (authorized === null) {
    return (
      <div className="min-h-screen bg-slate-50 flex items-center justify-center">
        <div className="flex flex-col items-center gap-2">
          <div className="w-6 h-6 border-2 border-slate-700 border-t-transparent rounded-full animate-spin" />
          <p className="text-xs text-slate-500 font-medium">Memverifikasi otorisasi admin...</p>
        </div>
      </div>
    );
  }

  if (authorized === false) {
    return (
      <div className="min-h-screen bg-slate-100 flex items-center justify-center p-4">
        <div className="max-w-sm w-full bg-white rounded-lg p-6 border border-slate-200 shadow-xs text-center">
          <div className="w-10 h-10 bg-slate-100 text-slate-700 rounded-md flex items-center justify-center mx-auto mb-3">
            <Shield className="w-5 h-5" strokeWidth={1.5} />
          </div>
          <h1 className="text-base font-semibold text-slate-900 mb-1">Akses Ditolak (403)</h1>
          <p className="text-xs text-slate-500 mb-5">
            Halaman ini khusus untuk peran Super Administrator platform.
          </p>
          <div className="flex flex-col gap-2">
            <button
              onClick={async () => {
                await adminApi.logout();
                router.push(ADMIN_LOGIN_PATH);
              }}
              className="w-full py-2 px-3 text-xs font-medium rounded-md bg-slate-900 text-white hover:bg-slate-800 transition-colors cursor-pointer"
            >
              Masuk sebagai Admin Lain
            </button>
            <Link
              href="/"
              className="w-full py-2 px-3 text-xs font-medium rounded-md border border-slate-200 text-slate-700 hover:bg-slate-50 transition-colors block text-center"
            >
              Kembali ke Beranda
            </Link>
          </div>
        </div>
      </div>
    );
  }

  const handleLogout = async () => {
    await adminApi.logout();
    router.push(ADMIN_LOGIN_PATH);
  };

  return (
    <div className="min-h-screen bg-slate-50 flex flex-col md:flex-row text-slate-900 font-sans antialiased">
      {/* Desktop Sidebar */}
      <aside className="hidden md:flex w-60 shrink-0 border-r border-slate-800 shadow-xs flex-col min-h-screen sticky top-0 h-screen">
        <SidebarNav
          pathname={pathname}
          userName={userName}
          userAvatar={userAvatar}
          onLogout={handleLogout}
        />
      </aside>

      {/* Mobile Header Bar */}
      <div className="md:hidden bg-[#0F172A] border-b border-slate-800 px-4 py-3 flex items-center justify-between text-white sticky top-0 z-30">
        <div className="flex items-center gap-2">
          <Shield className="w-4 h-4 text-slate-300" strokeWidth={1.5} />
          <span className="text-xs font-semibold tracking-tight">LaundryKu Admin</span>
        </div>
        <div className="flex items-center gap-2">
          <Avatar src={userAvatar} name={userName} size="sm" className="w-6 h-6" />
          <button
            onClick={() => setMobileMenuOpen(true)}
            className="p-1.5 rounded text-slate-400 hover:text-white"
            aria-label="Buka menu navigasi"
          >
            <Menu className="w-5 h-5" strokeWidth={1.5} />
          </button>
        </div>
      </div>

      {/* Mobile Drawer Backdrop */}
      {mobileMenuOpen && (
        <div
          className="fixed inset-0 bg-slate-950/60 backdrop-blur-xs z-40 md:hidden"
          onClick={() => setMobileMenuOpen(false)}
        />
      )}

      {/* Mobile Drawer Menu */}
      <div
        className={`fixed inset-y-0 left-0 w-64 z-50 transform transition-transform duration-200 ease-in-out md:hidden ${
          mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <SidebarNav
          pathname={pathname}
          userName={userName}
          userAvatar={userAvatar}
          onClose={() => setMobileMenuOpen(false)}
          onLogout={handleLogout}
        />
      </div>

      {/* Main Content Viewport */}
      <main className="flex-1 flex flex-col min-w-0 overflow-y-auto">
        <div className="p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto">
          {children}
        </div>
      </main>
    </div>
  );
}
