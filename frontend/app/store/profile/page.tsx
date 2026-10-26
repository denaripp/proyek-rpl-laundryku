'use client';

import React, { useState, useEffect } from 'react';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { Button, Input, PasswordInput } from '@/components/ui';
import { getStoredUser, fetchPortalSession, AuthUser } from '@/lib/auth';
import { formatFriendlyErrorMessage } from '@/lib/api';
import { User, Lock, Mail, Phone, CheckCircle2, AlertCircle } from 'lucide-react';

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
      setPasswordError('Password baru minimal terdiri dari 8 karakter.');
      setSavingPassword(false);
      return;
    }

    if (newPassword !== confirmPassword) {
      setPasswordError('Konfirmasi password baru tidak cocok.');
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
        const errorMsg = json?.message || json?.errors?.current_password?.[0] || json?.error?.message || 'Gagal mengubah password.';
        throw new Error(errorMsg);
      }

      setPasswordSuccess('Password akun berhasil diperbarui. Sesi perangkat lain telah dinonaktifkan.');
      setCurrentPassword('');
      setNewPassword('');
      setConfirmPassword('');
    } catch (err: unknown) {
      setPasswordError(formatFriendlyErrorMessage(err, 'Terjadi kesalahan saat mengubah password.'));
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
        <div>
          <h1 className="text-xl font-bold text-slate-900 tracking-tight">
            Profil Akun Mitra
          </h1>
          <p className="text-xs text-slate-500 mt-0.5">
            Kelola data diri, kontak login, dan keamanan password akun toko Anda.
          </p>
        </div>

        {/* Section 1: Informasi Personal */}
        <div className="bg-white border border-slate-200 rounded-lg p-6 space-y-4">
          <div className="flex items-center gap-2 border-b border-slate-100 pb-3">
            <User className="w-5 h-5 text-[#1A3A5C]" />
            <h2 className="text-sm font-bold text-slate-900">Informasi Pribadi & Kontak</h2>
          </div>

          {profileSuccess && (
            <div className="p-3 bg-emerald-50 border border-emerald-200 rounded-md text-emerald-700 text-xs flex items-center gap-2">
              <CheckCircle2 className="w-4 h-4 shrink-0 text-emerald-600" />
              <span>{profileSuccess}</span>
            </div>
          )}

          {profileError && (
            <div className="p-3 bg-rose-50 border border-rose-200 rounded-md text-rose-700 text-xs flex items-center gap-2">
              <AlertCircle className="w-4 h-4 shrink-0 text-rose-600" />
              <span>{profileError}</span>
            </div>
          )}

          <form onSubmit={handleUpdateProfile} className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Nama Lengkap
                </label>
                <Input
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="Nama pengelola"
                  required
                  disabled={loading || savingProfile}
                  className="text-xs"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Nomor Kontak / WhatsApp
                </label>
                <Input
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="08123456789"
                  disabled={loading || savingProfile}
                  className="text-xs"
                />
              </div>
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Alamat Email (Login)
              </label>
              <Input
                value={currentUser?.display_email || currentUser?.email || '-'}
                disabled
                className="text-xs bg-slate-50 text-slate-500 cursor-not-allowed"
              />
              <p className="text-[11px] text-slate-400 mt-1">
                Email akun mitra dilindungi sistem dan tidak dapat diubah secara langsung demi keamanan akses portal.
              </p>
            </div>

            <div className="pt-2 flex justify-end">
              <Button
                type="submit"
                variant="primary"
                size="sm"
                className="bg-[#1A3A5C] text-xs font-medium px-4"
                disabled={savingProfile || loading}
              >
                {savingProfile ? 'Menyimpan...' : 'Simpan Profil'}
              </Button>
            </div>
          </form>
        </div>

        {/* Section 2: Keamanan & Ganti Password */}
        <div className="bg-white border border-slate-200 rounded-lg p-6 space-y-4">
          <div className="flex items-center gap-2 border-b border-slate-100 pb-3">
            <Lock className="w-5 h-5 text-[#1A3A5C]" />
            <h2 className="text-sm font-bold text-slate-900">Keamanan & Ganti Password</h2>
          </div>

          {passwordSuccess && (
            <div className="p-3 bg-emerald-50 border border-emerald-200 rounded-md text-emerald-700 text-xs flex items-center gap-2">
              <CheckCircle2 className="w-4 h-4 shrink-0 text-emerald-600" />
              <span>{passwordSuccess}</span>
            </div>
          )}

          {passwordError && (
            <div className="p-3 bg-rose-50 border border-rose-200 rounded-md text-rose-700 text-xs flex items-center gap-2">
              <AlertCircle className="w-4 h-4 shrink-0 text-rose-600" />
              <span>{passwordError}</span>
            </div>
          )}

          <form onSubmit={handleUpdatePassword} className="space-y-4">
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Password Saat Ini
              </label>
              <PasswordInput
                value={currentPassword}
                onChange={(e) => setCurrentPassword(e.target.value)}
                placeholder="Masukkan password saat ini"
                required
                disabled={savingPassword}
                className="text-xs"
              />
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Password Baru
                </label>
                <PasswordInput
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                  placeholder="Minimal 8 karakter"
                  required
                  disabled={savingPassword}
                  className="text-xs"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Konfirmasi Password Baru
                </label>
                <PasswordInput
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  placeholder="Ulangi password baru"
                  required
                  disabled={savingPassword}
                  className="text-xs"
                />
              </div>
            </div>

            <div className="pt-2 flex justify-end">
              <Button
                type="submit"
                variant="primary"
                size="sm"
                className="bg-[#1A3A5C] text-xs font-medium px-4"
                disabled={savingPassword}
              >
                {savingPassword ? 'Mengubah...' : 'Perbarui Password'}
              </Button>
            </div>
          </form>
        </div>
      </div>
    </DashboardLayout>
  );
}
