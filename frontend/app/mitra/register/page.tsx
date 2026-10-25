import { cookies } from 'next/headers';
import { redirect } from 'next/navigation';
import { PORTAL_COOKIE_NAMES } from '@/lib/bff';
import MitraRegisterClient from './MitraRegisterClient';

export default async function MitraRegisterPage() {
  const cookieStore = await cookies();
  const customerToken =
    cookieStore.get(PORTAL_COOKIE_NAMES.customer)?.value ||
    cookieStore.get('auth_token')?.value;
  const entryFlag = cookieStore.get('mitra_entry_flag')?.value;

  // 1. Belum login customer: arahkan ke login customer
  if (!customerToken) {
    redirect('/auth?redirect=/upgrade');
  }

  // 2. Tanpa flag valid berumur pendek: redirect ke beranda /
  if (!entryFlag) {
    redirect('/');
  }

  return <MitraRegisterClient />;
}
