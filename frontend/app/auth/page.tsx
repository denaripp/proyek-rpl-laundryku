'use client';

import React, { useState, useEffect, Suspense } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { MessageSquare, Mail, ArrowRight, LogOut, KeyRound, User, Phone } from 'lucide-react';
import { AuthLayout } from '@/components/layout/AuthLayout';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { PasswordInput } from '@/components/ui/PasswordInput';
import { PasswordStrengthMeter } from '@/components/ui/PasswordStrengthMeter';
import { OTPInput } from '@/components/ui/OTPInput';
import { Badge } from '@/components/ui/Badge';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { useToast } from '@/components/ui/Toast';
import {
  register,
  verifyOtp,
  sendOtp,
  login,
  logout,
  logoutAndRedirectHome,
  requestPasswordSetup,
  setupPassword,
  getStoredUser,
  AuthUser,
  AuthApiError,
  getSafeRedirectPath,
  getHomePathForRole,
  getUserDisplayContact,
} from '@/lib/auth';
import { useAuth } from '@/lib/AuthContext';

type AuthTab = 'login' | 'register' | 'otp' | 'setup_password';

function AuthContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const redirectParam = searchParams.get('redirect');
  const safeRedirect = getSafeRedirectPath(redirectParam, '/');
  const { success, error: toastError } = useToast();
  const { user: authUser, setUserSession, logoutUser } = useAuth();
  const [currentUser, setCurrentUser] = useState<AuthUser | null>(null);
  const [activeTab, setActiveTab] = useState<AuthTab>('login');
  const [channel, setChannel] = useState<'email' | 'whatsapp'>('whatsapp');

  // Form Fields
  const [name, setName] = useState('');
  const [destination, setDestination] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [loginPassword, setLoginPassword] = useState('');
  const [otpCode, setOtpCode] = useState('');

  // Password Setup for Legacy Accounts
  const [setupStep, setSetupStep] = useState<'request' | 'verify'>('request');
  const [setupOtp, setSetupOtp] = useState('');
  const [setupNewPassword, setSetupNewPassword] = useState('');
  const [setupNewPasswordConfirmation, setSetupNewPasswordConfirmation] = useState('');

  // OTP Context
  const [otpUserId, setOtpUserId] = useState<string | null>(null);
  const [otpDestination, setOtpDestination] = useState('');
  const [otpChannel, setOtpChannel] = useState<'email' | 'whatsapp'>('whatsapp');
  const [debugOtp, setDebugOtp] = useState<string | null>(null);
  const [resendCooldown, setResendCooldown] = useState(0);

  // Status
  const [loading, setLoading] = useState(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [otpSent, setOtpSent] = useState<boolean>(false);
  const [deliveryStatus, setDeliveryStatus] = useState<'sent' | 'simulated' | 'failed'>('simulated');

  useEffect(() => {
    setCurrentUser(authUser || getStoredUser());
  }, [authUser]);

  useEffect(() => {
    if (resendCooldown <= 0) return;
    const interval = setInterval(() => {
      setResendCooldown((prev) => prev - 1);
    }, 1000);
    return () => clearInterval(interval);
  }, [resendCooldown]);

  // Handle Registration
  async function handleRegister(e: React.FormEvent) {
    e.preventDefault();
    setErrorMsg(null);
    setFieldErrors({});

    // Client-side quick check
    if (password.length < 8) {
      setErrorMsg('Password minimal terdiri dari 8 karakter.');
      setFieldErrors({ password: ['Password minimal terdiri dari 8 karakter.'] });
      return;
    }
    if (password !== passwordConfirmation) {
      setErrorMsg('Konfirmasi password tidak cocok.');
      setFieldErrors({ password_confirmation: ['Konfirmasi password tidak cocok.'] });
      return;
    }

    setLoading(true);

    try {
      const res = await register({
        name,
        channel,
        destination,
        password,
        password_confirmation: passwordConfirmation,
      });

      setOtpUserId(res.user_id);
      setOtpChannel(res.channel);
      setOtpDestination(res.destination);
      setOtpSent(Boolean(res.otp_sent));
      setDeliveryStatus(res.delivery_status || (res.otp_sent ? 'sent' : 'simulated'));
      setDebugOtp(res.debug_otp || null);
      setResendCooldown(60);

      // Clear plain passwords from form state
      setPassword('');
      setPasswordConfirmation('');

      if (res.otp_sent && res.delivery_status === 'sent') {
        success('Pendaftaran Berhasil', 'Silakan masukkan kode OTP yang telah dikirimkan untuk verifikasi.');
      } else if (res.debug_otp) {
        success('Pendaftaran Berhasil', 'Mode pengujian: OTP tidak dikirim ke email/WhatsApp.');
      } else {
        success('Pendaftaran Berhasil', 'Silakan periksa kode OTP yang telah dikirimkan.');
      }
      setActiveTab('otp');
    } catch (err: unknown) {
      if (err instanceof AuthApiError) {
        setErrorMsg(err.message);
        setFieldErrors(err.fieldErrors || {});
        toastError('Registrasi Gagal', err.message);
      } else {
        const msg = err instanceof Error ? err.message : 'Registrasi gagal.';
        setErrorMsg(msg);
        toastError('Registrasi Gagal', msg);
      }
    } finally {
      setLoading(false);
    }
  }

  // Handle Direct Login with Password (No OTP)
  async function handleLogin(e: React.FormEvent) {
    e.preventDefault();
    setErrorMsg(null);
    setFieldErrors({});

    if (!loginPassword) {
      setErrorMsg('Password wajib diisi.');
      setFieldErrors({ password: ['Password wajib diisi.'] });
      return;
    }

    setLoading(true);

    try {
      const res = await login({
        channel,
        destination,
        password: loginPassword,
      });

      // Clear password from state immediately
      setLoginPassword('');

      setUserSession(res.token, res.user);
      setCurrentUser(res.user);
      success('Login Berhasil', `Selamat datang kembali, ${res.user.name}!`);
      setTimeout(() => {
        const destination = redirectParam
          ? getSafeRedirectPath(redirectParam, getHomePathForRole(res.user))
          : getHomePathForRole(res.user);
        router.push(destination);
      }, 500);
    } catch (err: unknown) {
      if (err instanceof AuthApiError) {
        if (err.code === 'ACCOUNT_PENDING_VERIFICATION') {
          setErrorMsg('Akun belum diverifikasi. Silakan lakukan verifikasi OTP registrasi terlebih dahulu.');
          setOtpUserId((err.fieldErrors as unknown as { user_id?: string })?.user_id || null);
          setOtpChannel(channel);
          setOtpDestination(destination);
          setActiveTab('otp');
          toastError('Verifikasi Diperlukan', 'Akun Anda belum aktif. Masukkan kode OTP pendaftaran.');
        } else if (err.code === 'PASSWORD_NOT_SET') {
          setErrorMsg('Akun Anda belum memiliki kata sandi. Silakan hubungi administrator atau buat akun baru.');
          toastError('Password Belum Diatur', 'Akun lama belum memiliki kata sandi.');
        } else if (err.status === 401 || err.code === 'INVALID_CREDENTIALS') {
          setErrorMsg('Kontak atau password salah.');
          toastError('Login Gagal', 'Kontak atau password salah.');
        } else {
          setErrorMsg(err.message);
          setFieldErrors(err.fieldErrors || {});
          toastError('Login Gagal', err.message);
        }
      } else {
        const msg = err instanceof Error ? err.message : 'Login gagal.';
        setErrorMsg(msg);
        toastError('Login Gagal', msg);
      }
    } finally {
      setLoading(false);
    }
  }

  // Handle OTP Verification
  async function handleVerifyOtp(otpToVerify?: string) {
    const code = otpToVerify || otpCode;
    if (code.length !== 6) return;

    setErrorMsg(null);
    setFieldErrors({});
    setLoading(true);

    try {
      const res = await verifyOtp({
        user_id: otpUserId || undefined,
        channel: otpChannel,
        destination: otpDestination || destination,
        otp: code,
      });

      setUserSession(res.token, res.user);
      setCurrentUser(res.user);
      success('Verifikasi Berhasil', `Selamat datang kembali, ${res.user.name}!`);
      setTimeout(() => {
        const destination = redirectParam
          ? getSafeRedirectPath(redirectParam, getHomePathForRole(res.user))
          : getHomePathForRole(res.user);
        router.push(destination);
      }, 800);
    } catch (err: unknown) {
      if (err instanceof AuthApiError) {
        setErrorMsg(err.message);
        setFieldErrors(err.fieldErrors || {});
        toastError('Verifikasi Gagal', err.message);
      } else {
        const msg = err instanceof Error ? err.message : 'Verifikasi OTP gagal.';
        setErrorMsg(msg);
        toastError('Verifikasi Gagal', msg);
      }
    } finally {
      setLoading(false);
    }
  }

  // Handle Resend OTP
  async function handleResendOtp() {
    if (resendCooldown > 0) return;
    setErrorMsg(null);
    setFieldErrors({});
    setLoading(true);

    try {
      const res = await sendOtp({
        user_id: otpUserId || undefined,
        channel: otpChannel,
        destination: otpDestination || destination,
      });

      setOtpSent(Boolean(res.otp_sent));
      setDeliveryStatus(
        (res.delivery_status as 'sent' | 'simulated' | 'failed') ||
          (res.otp_sent ? 'sent' : 'simulated')
      );
      if (res.debug_otp) {
        setDebugOtp(res.debug_otp);
      }
      setResendCooldown(60);

      if (res.otp_sent && res.delivery_status === 'sent') {
        success('OTP Dikirim Ulang', 'Kode OTP baru telah dikirimkan ke kontak Anda.');
      } else if (res.debug_otp) {
        success('OTP Dibuat Ulang', 'Mode pengujian: OTP tidak dikirim ke email/WhatsApp.');
      } else {
        success('OTP Dikirim Ulang', 'Silakan periksa kode OTP yang telah dikirimkan.');
      }
    } catch (err: unknown) {
      if (err instanceof AuthApiError) {
        setErrorMsg(err.message);
        setFieldErrors(err.fieldErrors || {});
        toastError('Pengiriman Ulang Gagal', err.message);
      } else {
        const msg = err instanceof Error ? err.message : 'Gagal mengirim ulang OTP.';
        setErrorMsg(msg);
        toastError('Pengiriman Ulang Gagal', msg);
      }
    } finally {
      setLoading(false);
    }
  }

  // Handle Request Password Setup for Legacy Account
  async function handleRequestPasswordSetup(e: React.FormEvent) {
    e.preventDefault();
    setErrorMsg(null);
    setFieldErrors({});
    setLoading(true);

    try {
      const res = await requestPasswordSetup({
        channel,
        destination,
      });

      if (res.debug_otp) {
        setDebugOtp(res.debug_otp);
      }
      setSetupStep('verify');
      setResendCooldown(60);
      success('Permintaan Terkirim', 'Silakan masukkan kode OTP yang telah dikirimkan.');
    } catch (err: unknown) {
      if (err instanceof AuthApiError) {
        setErrorMsg(err.message);
        setFieldErrors(err.fieldErrors || {});
        toastError('Gagal Meminta OTP', err.message);
      } else {
        const msg = err instanceof Error ? err.message : 'Permintaan OTP gagal.';
        setErrorMsg(msg);
        toastError('Gagal Meminta OTP', msg);
      }
    } finally {
      setLoading(false);
    }
  }

  // Handle Complete Password Setup for Legacy Account
  async function handleCompletePasswordSetup(e: React.FormEvent) {
    e.preventDefault();
    setErrorMsg(null);
    setFieldErrors({});

    if (!setupOtp || setupOtp.length !== 6) {
      setErrorMsg('Kode OTP 6 digit wajib diisi.');
      return;
    }

    if (!setupNewPassword) {
      setErrorMsg('Password baru wajib diisi.');
      return;
    }

    setLoading(true);

    try {
      const res = await setupPassword({
        channel,
        destination,
        otp: setupOtp,
        password: setupNewPassword,
        password_confirmation: setupNewPasswordConfirmation,
      });

      // Clear sensitive state
      setSetupNewPassword('');
      setSetupNewPasswordConfirmation('');
      setSetupOtp('');

      setUserSession(res.token, res.user);
      setCurrentUser(res.user);
      success('Kata Sandi Berhasil Dibuat', `Selamat datang kembali, ${res.user.name}!`);
      setTimeout(() => {
        const destination = redirectParam
          ? getSafeRedirectPath(redirectParam, getHomePathForRole(res.user))
          : getHomePathForRole(res.user);
        router.push(destination);
      }, 500);
    } catch (err: unknown) {
      if (err instanceof AuthApiError) {
        setErrorMsg(err.message);
        setFieldErrors(err.fieldErrors || {});
        toastError('Gagal Mengatur Password', err.message);
      } else {
        const msg = err instanceof Error ? err.message : 'Pengaturan password gagal.';
        setErrorMsg(msg);
        toastError('Gagal Mengatur Password', msg);
      }
    } finally {
      setLoading(false);
    }
  }

  async function handleLogout() {
    await logoutAndRedirectHome('customer');
  }

  // Render Logged In User State
  if (currentUser) {
    return (
      <AuthLayout
        title="Sesi Aktif"
        subtitle="Anda sedang masuk di akun LaundryKu"
      >
        <div className="space-y-6">
          <div className="flex items-center gap-4 p-4 rounded-2xl bg-navy-50 border border-navy-200">
            <div className="w-14 h-14 rounded-2xl bg-navy-700 text-white font-heading font-extrabold text-xl flex items-center justify-center shadow-xs">
              {currentUser.name.charAt(0).toUpperCase()}
            </div>
            <div className="space-y-1">
              <h4 className="font-heading font-bold text-base text-navy-900">{currentUser.name}</h4>
              <p className="text-xs text-slate-500">{getUserDisplayContact(currentUser)}</p>
              <StatusBadge status={currentUser.status} />
            </div>
          </div>

          <div className="space-y-2.5 text-xs text-slate-600 bg-slate-50 p-4 rounded-2xl border border-slate-200">
            <div className="flex justify-between">
              <span className="text-slate-400">ID Pengguna:</span>
              <span className="font-mono text-slate-700">{currentUser.id}</span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-400">Kanal Verifikasi:</span>
              <span className="font-semibold text-navy-900 capitalize">
                {currentUser.has_real_email && currentUser.email ? 'Email' : 'WhatsApp'}
              </span>
            </div>
            <div className="flex justify-between">
              <span className="text-slate-400">Role:</span>
              <Badge variant="sky" size="sm">Customer</Badge>
            </div>
          </div>

          <div className="flex flex-col sm:flex-row gap-3">
            <Link href="/" className="flex-1">
              <Button variant="primary" className="w-full">
                Ke Beranda
              </Button>
            </Link>
            <Link href="/profile" className="flex-1">
              <Button variant="secondary" className="w-full">
                Lihat Profil
              </Button>
            </Link>
          </div>

          <Button
            variant="danger"
            onClick={handleLogout}
            className="w-full"
          >
            <LogOut className="w-4 h-4 mr-2" />
            Keluar dari Akun
          </Button>
        </div>
      </AuthLayout>
    );
  }

    return (
    <AuthLayout
      title={
        activeTab === 'otp'
          ? 'Verifikasi Kode OTP'
          : activeTab === 'register'
          ? 'Daftar Akun Baru'
          : activeTab === 'setup_password'
          ? 'Buat Kata Sandi Baru'
          : 'Masuk ke Akun Anda'
      }
      subtitle={
        activeTab === 'otp'
          ? `Ketik 6 angka kode yang kami kirim ke ${otpDestination || destination}`
          : activeTab === 'register'
          ? 'Mulai pesan laundry antar-jemput dengan mudah.'
          : activeTab === 'setup_password'
          ? 'Buat kata sandi minimal 8 karakter agar akun tetap aman.'
          : 'Masukkan WhatsApp atau email dan kata sandi Anda.'
      }
    >
      <div className="space-y-6">
        {/* Error Alert Box */}
        {errorMsg && (
          <div className="p-3.5 rounded-xl border border-rose-200 bg-rose-50 text-rose-800 text-xs font-medium leading-relaxed">
            {errorMsg}
          </div>
        )}

        {/* Tab Toggle (Login / Register) */}
        {activeTab !== 'otp' && activeTab !== 'setup_password' && (
          <div className="grid grid-cols-2 p-1 rounded-xl bg-slate-100 border border-slate-200 text-xs font-semibold">
            <button
              type="button"
              onClick={() => {
                setActiveTab('login');
                setErrorMsg(null);
                setFieldErrors({});
              }}
              className={`py-2.5 rounded-lg transition-all min-h-[40px] ${
                activeTab === 'login'
                  ? 'bg-white text-navy-900 shadow-sm font-bold'
                  : 'text-slate-500 hover:text-navy-900'
              }`}
            >
              Masuk
            </button>
            <button
              type="button"
              onClick={() => {
                setActiveTab('register');
                setErrorMsg(null);
                setFieldErrors({});
              }}
              className={`py-2.5 rounded-lg transition-all min-h-[40px] ${
                activeTab === 'register'
                  ? 'bg-white text-navy-900 shadow-sm font-bold'
                  : 'text-slate-500 hover:text-navy-900'
              }`}
            >
              Daftar
            </button>
          </div>
        )}

        {/* 1. LOGIN FORM */}
        {activeTab === 'login' && (
          <form onSubmit={handleLogin} className="space-y-4">
            {/* Channel Switcher */}
            <div className="space-y-1.5">
              <label className="block text-xs font-bold text-navy-900">Masuk lewat</label>
              <div className="grid grid-cols-2 gap-2">
                <button
                  type="button"
                  onClick={() => {
                    setChannel('whatsapp');
                    setFieldErrors({});
                  }}
                  className={`p-3 rounded-xl border text-xs font-semibold flex items-center justify-center gap-2 transition-all min-h-[44px] ${
                    channel === 'whatsapp'
                      ? 'border-navy-700 bg-navy-50 text-navy-900 ring-1 ring-navy-700/50'
                      : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-navy-900'
                  }`}
                >
                  <MessageSquare className="w-4 h-4 text-navy-700" />
                  <span>WhatsApp</span>
                </button>
                <button
                  type="button"
                  onClick={() => {
                    setChannel('email');
                    setFieldErrors({});
                  }}
                  className={`p-3 rounded-xl border text-xs font-semibold flex items-center justify-center gap-2 transition-all min-h-[44px] ${
                    channel === 'email'
                      ? 'border-navy-700 bg-navy-50 text-navy-900 ring-1 ring-navy-700/50'
                      : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-navy-900'
                  }`}
                >
                  <Mail className="w-4 h-4 text-navy-700" />
                  <span>Email</span>
                </button>
              </div>
            </div>

            <Input
              label={channel === 'whatsapp' ? 'Nomor WhatsApp' : 'Alamat Email'}
              type={channel === 'whatsapp' ? 'tel' : 'email'}
              autoComplete="username"
              required
              value={destination}
              onChange={(e) => {
                setDestination(e.target.value);
                if (fieldErrors['destination'] || fieldErrors['phone_number'] || fieldErrors['email']) {
                  setFieldErrors((prev) => {
                    const next = { ...prev };
                    delete next['destination'];
                    delete next['phone_number'];
                    delete next['email'];
                    return next;
                  });
                }
              }}
              error={fieldErrors['destination']?.[0] || fieldErrors['phone_number']?.[0] || fieldErrors['email']?.[0]}
              placeholder={channel === 'whatsapp' ? '081234567890' : 'nama@domain.com'}
              leftIcon={channel === 'whatsapp' ? <Phone className="w-4 h-4" /> : <Mail className="w-4 h-4" />}
            />

            <PasswordInput
              label="Kata sandi"
              required
              autoComplete="current-password"
              value={loginPassword}
              onChange={(e) => {
                setLoginPassword(e.target.value);
                if (fieldErrors['password']) {
                  setFieldErrors((prev) => {
                    const next = { ...prev };
                    delete next['password'];
                    return next;
                  });
                }
              }}
              error={fieldErrors['password']?.[0]}
              placeholder="Masukkan kata sandi Anda"
            />

            <Button
              type="submit"
              variant="primary"
              className="w-full min-h-[46px]"
              isLoading={loading}
            >
              Masuk
              <ArrowRight className="w-4 h-4 ml-1.5" />
            </Button>

            <div className="text-center pt-1">
              <button
                type="button"
                onClick={() => {
                  setActiveTab('setup_password');
                  setSetupStep('request');
                  setErrorMsg(null);
                  setFieldErrors({});
                }}
                className="text-xs text-slate-500 hover:text-navy-700 font-medium transition-colors"
              >
                Belum punya kata sandi? <strong className="text-navy-700 hover:text-navy-800 underline">Atur di sini</strong>
              </button>
            </div>

            <div className="pt-2 text-center border-t border-slate-100">
              <Link
                href="/mitra/login"
                className="inline-flex items-center gap-1 text-xs text-slate-600 hover:text-navy-700 font-medium transition-colors"
              >
                <span>Punya usaha laundry?</span>
                <span className="text-navy-700 font-semibold underline">Masuk sebagai mitra &rarr;</span>
              </Link>
            </div>
          </form>
        )}

        {/* 2. REGISTER FORM */}
        {activeTab === 'register' && (
          <form onSubmit={handleRegister} className="space-y-4">
            {/* Channel Switcher */}
            <div className="space-y-1.5">
              <label className="block text-xs font-bold text-navy-900">Daftar lewat</label>
              <div className="grid grid-cols-2 gap-2">
                <button
                  type="button"
                  onClick={() => {
                    setChannel('whatsapp');
                    setFieldErrors({});
                  }}
                  className={`p-3 rounded-xl border text-xs font-semibold flex items-center justify-center gap-2 transition-all min-h-[44px] ${
                    channel === 'whatsapp'
                      ? 'border-navy-700 bg-navy-50 text-navy-900 ring-1 ring-navy-700/50'
                      : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-navy-900'
                  }`}
                >
                  <MessageSquare className="w-4 h-4 text-navy-700" />
                  <span>WhatsApp</span>
                </button>
                <button
                  type="button"
                  onClick={() => {
                    setChannel('email');
                    setFieldErrors({});
                  }}
                  className={`p-3 rounded-xl border text-xs font-semibold flex items-center justify-center gap-2 transition-all min-h-[44px] ${
                    channel === 'email'
                      ? 'border-navy-700 bg-navy-50 text-navy-900 ring-1 ring-navy-700/50'
                      : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-navy-900'
                  }`}
                >
                  <Mail className="w-4 h-4 text-navy-700" />
                  <span>Email</span>
                </button>
              </div>
            </div>

            <Input
              label="Nama Lengkap"
              type="text"
              autoComplete="name"
              required
              value={name}
              onChange={(e) => {
                setName(e.target.value);
                if (fieldErrors['name'] || fieldErrors['full_name']) {
                  setFieldErrors((prev) => {
                    const next = { ...prev };
                    delete next['name'];
                    delete next['full_name'];
                    return next;
                  });
                }
              }}
              error={fieldErrors['name']?.[0] || fieldErrors['full_name']?.[0]}
              placeholder="Contoh: Budi Santoso"
              leftIcon={<User className="w-4 h-4" />}
            />

            <Input
              label={channel === 'whatsapp' ? 'Nomor WhatsApp' : 'Alamat Email'}
              type={channel === 'whatsapp' ? 'tel' : 'email'}
              autoComplete={channel === 'whatsapp' ? 'tel' : 'email'}
              required
              value={destination}
              onChange={(e) => {
                setDestination(e.target.value);
                if (fieldErrors['destination'] || fieldErrors['phone_number'] || fieldErrors['email']) {
                  setFieldErrors((prev) => {
                    const next = { ...prev };
                    delete next['destination'];
                    delete next['phone_number'];
                    delete next['email'];
                    return next;
                  });
                }
              }}
              error={fieldErrors['destination']?.[0] || fieldErrors['phone_number']?.[0] || fieldErrors['email']?.[0]}
              placeholder={channel === 'whatsapp' ? '081234567890' : 'nama@domain.com'}
              leftIcon={channel === 'whatsapp' ? <Phone className="w-4 h-4" /> : <Mail className="w-4 h-4" />}
            />

            <div>
              <PasswordInput
                label="Kata sandi"
                required
                autoComplete="new-password"
                value={password}
                onChange={(e) => {
                  setPassword(e.target.value);
                  if (fieldErrors['password']) {
                    setFieldErrors((prev) => {
                      const next = { ...prev };
                      delete next['password'];
                      return next;
                    });
                  }
                }}
                error={fieldErrors['password']?.[0]}
                placeholder="Buat kata sandi minimal 8 karakter"
              />
              <PasswordStrengthMeter
                password={password}
                name={name}
                destination={destination}
              />
            </div>

            <PasswordInput
              label="Ulangi kata sandi"
              required
              autoComplete="new-password"
              value={passwordConfirmation}
              onChange={(e) => {
                setPasswordConfirmation(e.target.value);
                if (fieldErrors['password_confirmation']) {
                  setFieldErrors((prev) => {
                    const next = { ...prev };
                    delete next['password_confirmation'];
                    return next;
                  });
                }
              }}
              error={fieldErrors['password_confirmation']?.[0]}
              placeholder="Ulangi kata sandi"
            />

            <Button
              type="submit"
              variant="primary"
              className="w-full min-h-[46px]"
              isLoading={loading}
            >
              Lanjut &amp; kirim kode
              <ArrowRight className="w-4 h-4 ml-1.5" />
            </Button>
          </form>
        )}

        {/* 3. OTP VERIFICATION FORM (FOR REGISTRATION) */}
        {activeTab === 'otp' && (
          <div className="space-y-5">
            {/* Mode Dummy Notice */}
            {Boolean(debugOtp) && (!otpSent || deliveryStatus === 'simulated') && (
              <div className="p-3 rounded-xl border border-amber-200 bg-amber-50 text-amber-900 text-xs font-medium leading-relaxed">
                Mode pengujian: OTP tidak dikirim ke email/WhatsApp.
              </div>
            )}

            {/* Dev Mode Dummy OTP Hint Alert */}
            {debugOtp && (
              <div className="p-3.5 rounded-2xl border border-navy-200 bg-navy-50 text-navy-900 text-xs flex items-center justify-between">
                <span className="flex items-center gap-2">
                  <KeyRound className="w-4 h-4 text-navy-700" />
                  <span>OTP Dev: <strong className="font-mono text-sm tracking-widest">{debugOtp}</strong></span>
                </span>
                <button
                  type="button"
                  onClick={() => {
                    setOtpCode(debugOtp);
                    handleVerifyOtp(debugOtp);
                  }}
                  className="px-2.5 py-1 rounded-lg bg-navy-700 text-white font-semibold text-[11px] hover:bg-navy-800 transition-colors"
                >
                  Isi &amp; Verifikasi
                </button>
              </div>
            )}

            <div className="space-y-2">
              <label className="block text-xs font-bold text-navy-900 text-center">
                Ketik 6 angka kode OTP
              </label>
              <OTPInput
                length={6}
                value={otpCode}
                onChange={setOtpCode}
                onComplete={(code) => handleVerifyOtp(code)}
                disabled={loading}
              />
            </div>

            <Button
              variant="primary"
              className="w-full min-h-[46px]"
              isLoading={loading}
              disabled={otpCode.length !== 6}
              onClick={() => handleVerifyOtp()}
            >
              Verifikasi &amp; masuk
            </Button>

            <div className="flex items-center justify-between pt-2 text-xs">
              <button
                type="button"
                onClick={() => {
                  setActiveTab('login');
                  setOtpCode('');
                }}
                className="text-slate-500 hover:text-navy-900 font-semibold p-1"
              >
                ← Ganti kontak / masuk
              </button>

              <button
                type="button"
                disabled={resendCooldown > 0 || loading}
                onClick={handleResendOtp}
                className="text-navy-700 hover:text-navy-800 font-semibold disabled:text-slate-400 p-1"
              >
                {resendCooldown > 0 ? `Kirim ulang (${resendCooldown}s)` : 'Kirim ulang kode'}
              </button>
            </div>
          </div>
        )}

        {/* 4. SETUP PASSWORD FORM (FOR LEGACY ACTIVE ACCOUNTS WITHOUT PASSWORD) */}
        {activeTab === 'setup_password' && (
          <div className="space-y-5">
            <div className="p-3.5 bg-navy-50/70 border border-navy-100 rounded-xl text-xs text-navy-900">
              <h4 className="font-bold mb-1">Atur kata sandi akun</h4>
              <p className="text-[11px] text-navy-700 leading-relaxed">
                Khusus akun yang belum memiliki kata sandi untuk masuk.
              </p>
            </div>

            {setupStep === 'request' ? (
              <form onSubmit={handleRequestPasswordSetup} className="space-y-4">
                {/* Channel Switcher */}
                <div className="space-y-1.5">
                  <label className="block text-xs font-bold text-navy-900">Kontak akun terdaftar</label>
                  <div className="grid grid-cols-2 gap-2">
                    <button
                      type="button"
                      onClick={() => setChannel('whatsapp')}
                      className={`p-3 rounded-xl border text-xs font-semibold flex items-center justify-center gap-2 transition-all min-h-[44px] ${
                        channel === 'whatsapp'
                          ? 'border-navy-700 bg-navy-50 text-navy-900 ring-1 ring-navy-700/50'
                          : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-navy-900'
                      }`}
                    >
                      <MessageSquare className="w-4 h-4 text-navy-700" />
                      <span>WhatsApp</span>
                    </button>
                    <button
                      type="button"
                      onClick={() => setChannel('email')}
                      className={`p-3 rounded-xl border text-xs font-semibold flex items-center justify-center gap-2 transition-all min-h-[44px] ${
                        channel === 'email'
                          ? 'border-navy-700 bg-navy-50 text-navy-900 ring-1 ring-navy-700/50'
                          : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-navy-900'
                      }`}
                    >
                      <Mail className="w-4 h-4 text-navy-700" />
                      <span>Email</span>
                    </button>
                  </div>
                </div>

                <Input
                  label={channel === 'whatsapp' ? 'Nomor WhatsApp terdaftar' : 'Alamat email terdaftar'}
                  type={channel === 'whatsapp' ? 'tel' : 'email'}
                  required
                  value={destination}
                  onChange={(e) => setDestination(e.target.value)}
                  placeholder={channel === 'whatsapp' ? '081234567890' : 'nama@domain.com'}
                  leftIcon={channel === 'whatsapp' ? <Phone className="w-4 h-4" /> : <Mail className="w-4 h-4" />}
                />

                <Button
                  type="submit"
                  variant="primary"
                  className="w-full min-h-[46px]"
                  isLoading={loading}
                >
                  Minta Kode OTP
                  <ArrowRight className="w-4 h-4 ml-1.5" />
                </Button>

                <div className="text-center pt-2">
                  <button
                    type="button"
                    onClick={() => {
                      setActiveTab('login');
                      setErrorMsg(null);
                    }}
                    className="text-xs text-slate-500 hover:text-navy-900 font-semibold p-1"
                  >
                    ← Kembali ke halaman masuk
                  </button>
                </div>
              </form>
            ) : (
              <form onSubmit={handleCompletePasswordSetup} className="space-y-4">
                {debugOtp && (
                  <div className="p-3.5 rounded-2xl border border-navy-200 bg-navy-50 text-navy-900 text-xs flex items-center justify-between">
                    <span className="flex items-center gap-2">
                      <KeyRound className="w-4 h-4 text-navy-700" />
                      <span>OTP Dev: <strong className="font-mono text-sm tracking-widest">{debugOtp}</strong></span>
                    </span>
                    <button
                      type="button"
                      onClick={() => setSetupOtp(debugOtp)}
                      className="px-2.5 py-1 rounded-lg bg-navy-700 text-white font-semibold text-[11px] hover:bg-navy-800 transition-colors"
                    >
                      Isi Kode
                    </button>
                  </div>
                )}

                <div className="space-y-2">
                  <label className="block text-xs font-bold text-navy-900 text-center">
                    Ketik 6 angka kode OTP
                  </label>
                  <OTPInput
                    length={6}
                    value={setupOtp}
                    onChange={setSetupOtp}
                    disabled={loading}
                  />
                </div>

                <div>
                  <PasswordInput
                    label="Kata sandi baru"
                    required
                    autoComplete="new-password"
                    value={setupNewPassword}
                    onChange={(e) => setSetupNewPassword(e.target.value)}
                    error={fieldErrors['password']?.[0]}
                    placeholder="Buat kata sandi minimal 8 karakter"
                  />
                  <PasswordStrengthMeter
                    password={setupNewPassword}
                    destination={destination}
                  />
                </div>

                <PasswordInput
                  label="Ulangi kata sandi baru"
                  required
                  autoComplete="new-password"
                  value={setupNewPasswordConfirmation}
                  onChange={(e) => setSetupNewPasswordConfirmation(e.target.value)}
                  error={fieldErrors['password_confirmation']?.[0]}
                  placeholder="Ulangi kata sandi baru"
                />

                <Button
                  type="submit"
                  variant="primary"
                  className="w-full min-h-[46px]"
                  isLoading={loading}
                >
                  Simpan kata sandi &amp; masuk
                  <ArrowRight className="w-4 h-4 ml-1.5" />
                </Button>

                <div className="flex items-center justify-between pt-2 text-xs">
                  <button
                    type="button"
                    onClick={() => {
                      setSetupStep('request');
                      setErrorMsg(null);
                    }}
                    className="text-slate-500 hover:text-navy-900 font-semibold p-1"
                  >
                    ← Ganti kontak
                  </button>

                  <button
                    type="button"
                    disabled={resendCooldown > 0 || loading}
                    onClick={handleRequestPasswordSetup}
                    className="text-navy-700 hover:text-navy-800 font-semibold disabled:text-slate-400 p-1"
                  >
                    {resendCooldown > 0 ? `Kirim ulang (${resendCooldown}s)` : 'Kirim ulang kode'}
                  </button>
                </div>
              </form>
            )}
          </div>
        )}
      </div>
    </AuthLayout>
  );
}

export default function AuthPage() {
  return (
    <Suspense
      fallback={
        <div className="min-h-screen bg-slate-50 flex items-center justify-center p-4">
          <div className="flex flex-col items-center gap-3">
            <div className="w-10 h-10 border-4 border-navy-700 border-t-transparent rounded-full animate-spin" />
            <p className="text-slate-600 font-medium">Memuat halaman autentikasi...</p>
          </div>
        </div>
      }
    >
      <AuthContent />
    </Suspense>
  );
}
