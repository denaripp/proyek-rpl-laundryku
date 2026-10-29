import { execSync } from 'child_process';
import path from 'path';

const ROOT_DIR = path.resolve(__dirname, '../../');
const PHP_BIN = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';

export function runArtisan(command: string): string {
  try {
    const fullCmd = `"${PHP_BIN}" artisan ${command}`;
    return execSync(fullCmd, { cwd: ROOT_DIR, encoding: 'utf8', timeout: 30000 });
  } catch (err: any) {
    console.error(`Artisan command failed: ${command}`, err.stdout || err.message);
    throw err;
  }
}

export function seedE2E(): void {
  runArtisan('db:seed --class=E2ESeeder');
}

export function processQueues(): void {
  try {
    runArtisan('queue:work --queue=notifications,default --stop-when-empty');
  } catch {
    // ignore timeout or empty
  }
}

export function getWhatsAppLogCount(): number {
  const output = runArtisan('tinker --execute="echo App\\Models\\WhatsAppLog::count();"');
  const lines = output.trim().split('\n');
  const lastLine = lines[lines.length - 1].trim();
  const count = parseInt(lastLine, 10);
  return isNaN(count) ? 0 : count;
}

export function getE2EStoreId(): string {
  const output = runArtisan('tinker --execute="echo App\\Models\\Store::where(\'slug\', \'laundry-berkah-e2e-cirebon\')->value(\'id\');"');
  const lines = output.trim().split('\n');
  return lines[lines.length - 1].trim();
}

export function getOrderDbStatus(orderId: string): string {
  const output = runArtisan(`tinker --execute="echo (string) App\\Models\\Order::find(${orderId})->getRawOriginal('status');"`);
  const lines = output.trim().split('\n');
  return lines[lines.length - 1].trim();
}

export function getLatestCourierTaskCourierId(orderId: string): number {
  const output = runArtisan(`tinker --execute="echo App\\Models\\CourierTask::where('order_id', ${orderId})->latest('id')->value('courier_id');"`);
  const lines = output.trim().split('\n');
  return parseInt(lines[lines.length - 1].trim(), 10);
}
