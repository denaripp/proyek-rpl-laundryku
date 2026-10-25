import { cookies } from 'next/headers';
import { redirect } from 'next/navigation';
import { PORTAL_COOKIE_NAMES } from '@/lib/bff';

export default async function OnboardPage() {
  const cookieStore = await cookies();
  const mitraToken = cookieStore.get(PORTAL_COOKIE_NAMES.mitra)?.value;

  if (mitraToken) {
    redirect('/store');
  }

  redirect('/mitra/register');
}
