import fs from 'fs';
import path from 'path';

async function testGuestScenarios() {
  console.log('===============================================================');
  console.log('      PENGUJIAN FITUR AI CHATBOT UNTUK GUEST & KEAMANAN        ');
  console.log('===============================================================\n');

  // A. Guest question on /api/chat
  console.log('--- TEST A: Guest Mengirim Pertanyaan Pendaftaran Mitra ---');
  try {
    const resA = await fetch('http://localhost:3000/api/chat', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: 'Bagaimana cara daftar menjadi mitra laundry?' }),
    });
    const jsonA = await resA.json();
    console.log('Status HTTP:', resA.status);
    console.log('AI Answer Preview:\n', jsonA.data?.answer?.slice(0, 180).replace(/\n/g, ' '), '...');
    const passA = resA.status === 200 && jsonA.success && jsonA.data?.answer?.includes('Gabung Mitra');
    console.log('Hasil Test A:', passA ? '✅ PASS' : '❌ FAIL');
  } catch (err) {
    console.error('Error Test A:', err.message);
  }

  // B. Multi-turn Follow-up as Guest
  console.log('\n--- TEST B: Percakapan Lanjutan (Multi-Turn) Sebagai Guest ---');
  try {
    const history = [
      { role: 'user', content: 'Bagaimana cara daftar menjadi mitra laundry?' },
      { role: 'assistant', content: 'Untuk mendaftar mitra toko, buka menu /upgrade lalu pilih tab Pendaftaran Toko.' },
    ];
    const resB = await fetch('http://localhost:3000/api/chat', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        message: 'Berapa lama proses verifikasinya?',
        messages: history,
      }),
    });
    const jsonB = await resB.json();
    console.log('Status HTTP:', resB.status);
    console.log('AI Answer Preview:\n', jsonB.data?.answer?.slice(0, 180).replace(/\n/g, ' '), '...');
    const passB = resB.status === 200 && jsonB.success && (jsonB.data?.answer?.includes('1x24 jam') || jsonB.data?.answer?.includes('verifikasi'));
    console.log('Hasil Test B:', passB ? '✅ PASS' : '❌ FAIL');
  } catch (err) {
    console.error('Error Test B:', err.message);
  }

  // C. Personal Order Data Access as Guest
  console.log('\n--- TEST C: Akses Data Pesanan Pribadi Sebagai Guest ---');
  try {
    const resC = await fetch('http://localhost:3000/api/chat', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message: 'Bagaimana status pesanan cucian saya?' }),
    });
    const jsonC = await resC.json();
    console.log('Status HTTP:', resC.status);
    console.log('AI Answer Preview:\n', jsonC.data?.answer?.slice(0, 200).replace(/\n/g, ' '), '...');
    const ans = jsonC.data?.answer || '';
    const instructsLogin = ans.includes('login') || ans.includes('masuk') || ans.includes('/auth');
    const doesNotFakeOrder = !ans.includes('No: ORD-') && !ans.includes('Pesanan Anda #');
    const passC = resC.status === 200 && instructsLogin && doesNotFakeOrder;
    console.log('Hasil Test C (Meminta login & tidak mengarang status):', passC ? '✅ PASS' : '❌ FAIL');
  } catch (err) {
    console.error('Error Test C:', err.message);
  }

  // E. Security Tests: Check Protected Endpoints Reject Unauthenticated Access
  console.log('\n--- TEST E: Proteksi Endpoint Privat (Zero Trust Backend) ---');
  try {
    // 1. /api/v1/chatbot/sessions without token
    const resPrivateSession = await fetch('http://localhost:8000/api/v1/chatbot/sessions', {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
    });
    console.log('GET /api/v1/chatbot/sessions tanpa token -> Status:', resPrivateSession.status);
    const passPrivateSession = resPrivateSession.status === 401;

    // 2. /api/v1/orders without token
    const resPrivateOrders = await fetch('http://localhost:8000/api/v1/orders', {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
    });
    console.log('GET /api/v1/orders tanpa token -> Status:', resPrivateOrders.status);
    const passPrivateOrders = resPrivateOrders.status === 401;

    // 3. /api/v1/chatbot/guest/ask is public
    const resGuestAsk = await fetch('http://localhost:8000/api/v1/chatbot/guest/ask', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ message: 'Cara daftar akun?' }),
    });
    console.log('POST /api/v1/chatbot/guest/ask tanpa token -> Status:', resGuestAsk.status);
    const passGuestAsk = resGuestAsk.status === 200;

    console.log('Hasil Test E (Endpoint privat tetap 401, endpoint publik guest 200):', (passPrivateSession && passPrivateOrders && passGuestAsk) ? '✅ PASS' : '❌ FAIL');
  } catch (err) {
    console.error('Error Test E:', err.message);
  }

  console.log('\n===============================================================\n');
}

testGuestScenarios();
