'use client';

import React, { useState, useEffect } from 'react';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { Button, Input, PasswordInput } from '@/components/ui';
import { getStoredUser, fetchPortalSession, AuthUser } from '@/lib/auth';
import { formatFriendlyErrorMessage } from '@/lib/api';
import { User, Lock, Mail, Phone, CheckCircle2, AlertCircle, ShieldCheck } from 'lucide-react';

export default function StoreProfilePage() {
  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState(true);

  // Profile Form State
  const [name, setName] = useState('');
  const [phone, setPhone] = useState('');
  const [savingProfile, setSavingProfile] = useState(false);
  const [profileSuccess, setProfileSuccess] = useState<string | null>(null);
  const [profileError, setProfileError] = useState<string | null>(null);

  // Password Form State
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [savingPassword, setSavingPassword] = useState(false);
  const [passwordSuccess, setPasswordSuccess] = useState<string | null>(null);
  const [passwordError, setPasswordError] = useState<string | null>(null);

  useEffect(() => {
    async function loadUser() {
      setLoading(true);
      const stored = getStoredUser('mitra');
      if (stored) {
        setCurrentUser(stored);
        setName(stored.name || '');
        setPhone(stored.phone || '');
      }

      const fresh = await fetchPortalSession('mitra');
      if (fresh) {
        setCurrentUser(fresh);
        setName(fresh.name || '');
        setPhone(fresh.phone || '');
      }
      setLoading(false);
    }

    loadUser();
  }, []);

  const handleUpdateProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    setSavingProfile(true);
    setProfileSuccess(null);
    setProfileError(null);

    try {
      const res = await fetch('/api/proxy/profile', {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'x-portal': 'mitra',
        },
        credentials: 'include',
        body: JSON.stringify({
          name: name.trim(),
          phone: phone.trim(),
        }),
      });

      const json = await res.json().catch(() => null);

      if (!res.ok || json?.success === false) {
        const errorMsg = json?.message || json?.error?.message || 'Gagal memperbarui profil akun.';
        throw new Error(errorMsg);
      }

      setProfileSuccess('Profil akun berhasil disimpan.');
      await fetchPortalSession('mitra');
    } catch (err: unknown) {
      setProfileError(formatFriendlyErrorMessage(err, 'Terjadi kesalahan sistem.'));
    } finally {
      setSavingProfile(false);
    }
  };

  const handleUpdatePassword = async (e: React.FormEvent) => {
    e.preventDefault();
    setSavingPassword(true);
    setPasswordSuccess(null);
    setPasswordError(null);

    if (newPassword.length < 8) {
      setPasswordError('Kata sandi baru minimal terdiri dari 8 karakter.');
      setSavingPassword(false);
      return;
    }

    if (newPassword !== confirmPassword) {
      setPasswordError('Konfirmasi kata sandi baru tidak cocok.');
      setSavingPassword(false);
      return;
    }

    try {
      const res = await fetch('/api/proxy/mitra/password', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'x-portal': 'mitra',
        },
        credentials: 'include',
        body: JSON.stringify({
          current_password: currentPassword,
          password: newPassword,
          password_confirmation: confirmPassword,
        }),
      });

      const json = await res.json().catch(() => null);

      if (!res.ok || json?.success === false) {
        const errorMsg =
          json?.message ||
          json?.errors?.current_password?.[0] ||
          json?.error?.message ||
          'Gagal mengubah kata sandi.';
        throw new Error(errorMsg);
      }

      setPasswordSuccess('Kata sandi akun berhasil diperbarui. Sesi perangkat lain telah dinonaktifkan.');
      setCurrentPassword('');
      setNewPassword('');
      setConfirmPassword('');
    } catch (err: unknown) {
      setPasswordError(formatFriendlyErrorMessage(err, 'Terjadi kesalahan saat mengubah kata sandi.'));
    } finally {
      setSavingPassword(false);
    }
  };

  return (
    <DashboardLayout
      userRole="store_admin"
      userName={currentUser?.name || 'Admin Toko'}
      userEmail={currentUser?.email || currentUser?.phone || 'admin@toko.id'}
    >
      <div className="space-y-6 max-w-4xl">
        {/* Hero Banner Section (Navy-900 with Wave & Bubbles SVG) */}
        <div className="bg-navy-900 text-white rounded-3xl relative overflow-hidden p-6 sm:p-7 select-none shadow-sm">
          {/* Soap Bubbles Decorative Shapes (aria-hidden="true") */}
          <div className="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
            <div className="absolute top-2 right-8 w-24 h-24 rounded-full border border-sky-300/25 bg-sky-400/10 backdrop-blur-[1px]">
              <div className="w-2 h-2 rounded-full bg-white/60 ml-3 mt-3" />
            </div>
            <div className="absolute -bottom-6 right-36 w-20 h-20 rounded-full border border-sky-200/20 bg-sky-300/10">
              <div className="w-1.5 h-1.5 rounded-full bg-white/40 ml-2 mt-2" />
            </div>
          </div>

          <div className="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div className="space-y-1.5">
              <div className="flex items-center gap-3">
                <div className="w-11 h-11 rounded-2xl bg-white/10 border border-white/20 backdrop-blur-xs flex items-center justify-center text-sky-300 shadow-2xs">
                  <User className="w-5 h-5" strokeWidth={1.75} />
                </div>
                <h1 className="text-xl sm:text-2xl font-extrabold tracking-tight text-white leading-tight">
                  Profil akun mitra
                </h1>
              </div>
              <p className="text-xs text-navy-200 leading-relaxed">
                Kelola data diri pengelola gerai, kontak login, dan keamanan kata sandi akun portal Anda.
              </p>
            </div>
          </div>

          {/* Mini Wave SVG Accent at Bottom */}
          <div className="absolute -bottom-1 left-0 right-0 pointer-events-none overflow-hidden leading-none opacity-20" aria-hidden="true">
            <svg
              viewBox="0 0 1440 24"
              fill="none"
              xmlns="http://www.w3.org/2000/svg"
              className="w-full h-4 text-sky-300 preserve-3d"
            >
              <path
                d="M0 10C240 22 480 2 720 12C960 22 1200 5 1440 10V24H0V10Z"
                fill="currentColor"
              />
            </svg>
          </div>
        </div>

        {/* Section 1: Informasi Personal */}
        <div className="rounded-3xl bg-white border border-slate-200/90 shadow-2xs p-5 sm:p-6 space-y-5">
          <div className="flex items-center gap-3 border-b border-slate-100 pb-4">
            <div className="w-10 h-10 rounded-2xl bg-navy-50 text-navy-800 flex items-center justify-center border border-navy-100 shrink-0">
              <User className="w-5 h-5 text-navy-700" strokeWidth={1.75} />
            </div>
            <div>
              <h2 className="font-heading font-bold text-base text-navy-900 leading-tight">
                Informasi pribadi &amp; kontak
              </h2>
              <p className="text-xs text-slate-500 mt-0.5">
                Nama dan nomor kontak aktif untuk koordinasi gerai
              </p>
            </div>
          </div>

          {profileSuccess && (
            <div className="p-3.5 bg-emerald-50 border border-emerald-200/90 rounded-2xl text-emerald-800 text-xs flex items-center gap-2.5 shadow-2xs">
              <CheckCircle2 className="w-4 h-4 shrink-0 text-emerald-600" strokeWidth={2} />
              <span>{profileSuccess}</span>
            </div>
          )}

          {profileError && (
            <div className="p-3.5 bg-rose-50 border border-rose-200/90 rounded-2xl text-rose-800 text-xs flex items-center gap-2.5 shadow-2xs">
              <AlertCircle className="w-4 h-4 shrink-0 text-rose-600" strokeWidth={2} />
              <span>{profileError}</span>
            </div>
          )}

          <form onSubmit={handleUpdateProfile} className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Nama lengkap
                </label>
                <Input
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="Nama pengelola"
                  required
                  disabled={loading || savingProfile}
                  className="rounded-xl border-slate-200 focus:border-navy-700 text-xs sm:text-sm"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Nomor kontak / WhatsApp
                </label>
                <Input
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="08123456789"
                  disabled={loading || savingProfile}
                  className="rounded-xl border-slate-200 focus:border-navy-700 text-xs sm:text-sm"
                />
              </div>
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                Alamat email (login)
              </label>
              <Input
                value={currentUser?.display_email || currentUser?.email || '-'}
                disabled
                className="rounded-xl bg-slate-50 border-slate-200 text-slate-500 cursor-not-allowed text-xs sm:text-sm"
              />
              <p className="text-[11px] text-slate-400 mt-1.5 leading-relaxed">
                Email akun mitra dilindungi sistem dan tidak dapat diubah secara langsung demi keamanan akses portal.
              </p>
            </div>

            <div className="pt-2 flex justify-end">
              <Button
                type="submit"
                variant="primary"
                size="sm"
                className="bg-navy-700 hover:bg-navy-800 active:scale-[0.98] text-white rounded-xl text-xs sm:text-sm font-bold px-5 py-2.5 shadow-2xs cursor-pointer"
                disabled={savingProfile || loading}
              >
                {savingProfile ? 'Menyimpan...' : 'Simpan profil'}
              </Button>
            </div>
          </form>
        </div>

        {/* Section 2: Keamanan & Ganti Password */}
        <div className="rounded-3xl bg-white border border-slate-200/90 shadow-2xs p-5 sm:p-6 space-y-5">
          <div className="flex items-center gap-3 border-b border-slate-100 pb-4">
            <div className="w-10 h-10 rounded-2xl bg-navy-50 text-navy-800 flex items-center justify-center border border-navy-100 shrink-0">
              <Lock className="w-5 h-5 text-navy-700" strokeWidth={1.75} />
            </div>
            <div>
              <h2 className="font-heading font-bold text-base text-navy-900 leading-tight">
                Keamanan &amp; ganti kata sandi
              </h2>
              <p className="text-xs text-slate-500 mt-0.5">
                Perbarui kata sandi secara berkala untuk menjaga keamanan akun toko Anda
              </p>
            </div>
          </div>

          {passwordSuccess && (
            <div className="p-3.5 bg-emerald-50 border border-emerald-200/90 rounded-2xl text-emerald-800 text-xs flex items-center gap-2.5 shadow-2xs">
              <CheckCircle2 className="w-4 h-4 shrink-0 text-emerald-600" strokeWidth={2} />
              <span>{passwordSuccess}</span>
            </div>
          )}

          {passwordError && (
            <div className="p-3.5 bg-rose-50 border border-rose-200/90 rounded-2xl text-rose-800 text-xs flex items-center gap-2.5 shadow-2xs">
              <AlertCircle className="w-4 h-4 shrink-0 text-rose-600" strokeWidth={2} />
              <span>{passwordError}</span>
            </div>
          )}

          <form onSubmit={handleUpdatePassword} className="space-y-4">
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                Kata sandi saat ini
              </label>
              <PasswordInput
                value={currentPassword}
                onChange={(e) => setCurrentPassword(e.target.value)}
                placeholder="Masukkan kata sandi saat ini"
                required
                disabled={savingPassword}
                className="rounded-xl border-slate-200 focus:border-navy-700 text-xs sm:text-sm"
              />
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Kata sandi baru
                </label>
                <PasswordInput
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                  placeholder="Minimal 8 karakter"
                  required
                  disabled={savingPassword}
                  className="rounded-xl border-slate-200 focus:border-navy-700 text-xs sm:text-sm"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1.5">
                  Konfirmasi kata sandi baru
                </label>
                <PasswordInput
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  placeholder="Ulangi kata sandi baru"
                  required
                  disabled={savingPassword}
                  className="rounded-xl border-slate-200 focus:border-navy-700 text-xs sm:text-sm"
                />
              </div>
            </div>

            <div className="pt-2 flex justify-end">
              <Button
                type="submit"
                variant="primary"
                size="sm"
                className="bg-navy-700 hover:bg-navy-800 active:scale-[0.98] text-white rounded-xl text-xs sm:text-sm font-bold px-5 py-2.5 shadow-2xs cursor-pointer"
                disabled={savingPassword}
              >
                {savingPassword ? 'Mengubah...' : 'Perbarui kata sandi'}
              </Button>
            </div>
          </form>
        </div>
      </div>
    </DashboardLayout>
  );
}
