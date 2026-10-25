import { cookies } from 'next/headers';
import { redirect } from 'next/navigation';
import { PORTAL_COOKIE_NAMES } from '@/lib/bff';
import UpgradeClient from './UpgradeClient';

export default async function UpgradePage() {
  const cookieStore = await cookies();
  const customerToken =
    cookieStore.get(PORTAL_COOKIE_NAMES.customer)?.value ||
    cookieStore.get('auth_token')?.value;

  if (!customerToken) {
    redirect('/auth?redirect=/upgrade');
  }

  return <UpgradeClient />;
}
