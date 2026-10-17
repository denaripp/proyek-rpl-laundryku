import fs from 'fs';
import path from 'path';

// Read env configurations
function loadEnv(filepath) {
  try {
    const content = fs.readFileSync(filepath, 'utf8');
    const env = {};
    content.split('\n').forEach((line) => {
      const trimmed = line.trim();
      if (trimmed && !trimmed.startsWith('#')) {
        const idx = trimmed.indexOf('=');
        if (idx !== -1) {
          const key = trimmed.slice(0, idx).trim();
          let val = trimmed.slice(idx + 1).trim();
          if ((val.startsWith('"') && val.endsWith('"')) || (val.startsWith("'") && val.endsWith("'"))) {
            val = val.slice(1, -1);
          }
          env[key] = val;
        }
      }
    });
    return env;
  } catch {
    return {};
  }
}

const frontendEnv = loadEnv(path.resolve(process.cwd(), '.env.local'));
const backendEnv = loadEnv(path.resolve(process.cwd(), '../backend/.env'));

const apiKey = frontendEnv.AI_API_KEY || backendEnv.AI_API_KEY || '';
const rawBaseUrl = frontendEnv.AI_BASE_URL || backendEnv.AI_BASE_URL || 'https://openrouter.ai/api/v1';
const model = frontendEnv.AI_MODEL || backendEnv.AI_MODEL || 'liquid/lfm-2.5-2.6b:free';

console.log('====================================================');
console.log('   PENGUJIAN KONEKSI & DIAGNOSIS CHATBOT LAUNDRYKU   ');
console.log('====================================================');
console.log('1. STATUS ENVIRONMENT:');
console.log('   - API Key Status      :', apiKey ? 'TERISI' : 'KOSONG');
console.log('   - API Key Length      :', apiKey.length, 'karakter');
console.log('   - Mengandung ellipsis :', apiKey.includes('...') ? 'YA (Terpotong dummy: sk-or-v1-5b2...40e)' : 'TIDAK');
console.log('   - Base URL            :', rawBaseUrl);
console.log('   - Model Aktif         :', model);
console.log('   - Backend AI_MOCK     :', backendEnv.AI_MOCK_IN_LOCAL || 'false');
console.log('');

// Test 1: Direct Provider Connection
async function testDirectProvider() {
  console.log('2. UJI KONEKSI LANGSUNG KE PROVIDER AI (OpenRouter):');
  let baseUrl = rawBaseUrl.replace('openrouter.io', 'openrouter.ai').replace(/\/+$/, '');
  const url = `${baseUrl}/chat/completions`;
  console.log('   - Target URL:', url);

  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${apiKey}`,
        'Content-Type': 'application/json',
        'HTTP-Referer': 'http://localhost:3000',
        'X-Title': 'LaundryKu',
      },
      body: JSON.stringify({
        model,
        messages: [
          { role: 'system', content: 'Anda adalah asisten panduan platform LaundryKu.' },
          { role: 'user', content: 'Bagaimana cara pesan laundry?' },
        ],
        temperature: 0.4,
        max_tokens: 300,
      }),
    });

    console.log('   - Status HTTP:', res.status, res.statusText);
    const text = await res.text();
    let json;
    try {
      json = JSON.parse(text);
    } catch {
      json = null;
    }

    if (res.ok) {
      console.log('   - Hasil:', 'BERHASIL TERHUBUNG KE AI LIVE');
      console.log('   - Jawaban AI:', json?.choices?.[0]?.message?.content);
    } else {
      console.log('   - Hasil: GAGAL (Sesuai diagnosa kunci terpotong/invalid)');
      console.log('   - Error Body:', json ? JSON.stringify(json, null, 2) : text);
      if (res.status === 401) {
        console.log('   - CATATAN AKAR MASALAH: OpenRouter mengembalikan 401 Unauthorized ("User not found")');
        console.log('     karena API key di .env terpotong dengan tanda elipsis literal "..." (hanya 18 char).');
      }
    }
  } catch (err) {
    console.error('   - Fetch Error:', err.message);
  }
}

// Test 2: Next.js API Route (/api/chat)
async function testNextJsRoute() {
  console.log('\n3. UJI ENDPOINT NEXT.JS (/api/chat):');
  const url = 'http://localhost:3000/api/chat';
  console.log('   - Mengirim request ke:', url);

  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: 'Bagaimana cara pesan laundry?' }),
    });

    console.log('   - Status HTTP:', res.status, res.statusText);
    const json = await res.json();
    console.log('   - Response JSON:');
    console.log(JSON.stringify(json, null, 2));

    if (json.success && json.data?.answer && !json.data.answer.includes('sedang tidak tersedia')) {
      console.log('   - KESIMPULAN: Endpoint /api/chat BERHASIL MENJAWAB! (Bukan fallback "sedang tidak tersedia")');
    } else {
      console.log('   - KESIMPULAN: Endpoint masih menghasilkan fallback.');
    }
  } catch (err) {
    console.error('   - Fetch Error:', err.message);
  }
}

// Test 3: Backend API Route (/api/v1/chatbot/guest/ask)
async function testBackendRoute() {
  console.log('\n4. UJI ENDPOINT LARAVEL BACKEND (/api/v1/chatbot/guest/ask):');
  const url = 'http://localhost:8000/api/v1/chatbot/guest/ask';
  console.log('   - Mengirim request ke:', url);

  try {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ message: 'Bagaimana cara pesan laundry?' }),
    });

    console.log('   - Status HTTP:', res.status, res.statusText);
    const json = await res.json();
    console.log('   - Response JSON:');
    console.log(JSON.stringify(json, null, 2));

    if (json.success && json.data?.answer && !json.data.answer.includes('sedang tidak tersedia')) {
      console.log('   - KESIMPULAN: Endpoint Backend BERHASIL MENJAWAB! (Bukan fallback "sedang tidak tersedia")');
    } else {
      console.log('   - KESIMPULAN: Endpoint backend masih menghasilkan fallback.');
    }
  } catch (err) {
    console.error('   - Fetch Error:', err.message);
  }
}

async function main() {
  await testDirectProvider();
  await testNextJsRoute();
  await testBackendRoute();
  console.log('\n====================================================\n');
}

main();
