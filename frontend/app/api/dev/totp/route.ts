import { NextResponse } from 'next/server';
import { execSync } from 'child_process';

export async function GET() {
  if (process.env.NODE_ENV === 'production') {
    return NextResponse.json({ error: 'Disabled in production' }, { status: 403 });
  }
  try {
    const otp = execSync(
      `C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe -r "require 'c:/laragon/www/laundryku/vendor/autoload.php'; echo (new PragmaRX\\Google2FA\\Google2FA())->getCurrentOtp('QFZWZVRC4SNRXBSP');"`
    ).toString().trim();
    return NextResponse.json({ otp });
  } catch (err: any) {
    return NextResponse.json({ error: err.message }, { status: 500 });
  }
}
