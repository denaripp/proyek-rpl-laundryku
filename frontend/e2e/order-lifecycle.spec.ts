import { test, expect, Page } from '@playwright/test';
import { seedE2E, processQueues, getWhatsAppLogCount, getE2EStoreId, getOrderDbStatus, getLatestCourierTaskCourierId } from './helpers';

test.describe.serial('E2E Order Lifecycle & Courier Re-assignment', () => {
  let storeId: string;
  let initialLogCount: number;
  let order1Id: string;
  let order2Id: string;

  test.beforeAll(async () => {
    seedE2E();
    storeId = getE2EStoreId();
    initialLogCount = getWhatsAppLogCount();
  });

  // Helper login functions using separate browser contexts for clean session isolation
  async function loginAsCustomer(page: Page) {
    await page.goto('/auth');
    await page.waitForLoadState('networkidle');

    // If already logged in, logout first
    const logoutBtn = page.getByRole('button', { name: /Keluar/i });
    if (await logoutBtn.isVisible()) {
      await logoutBtn.click();
      await page.waitForURL('http://localhost:3000/');
      await page.goto('/auth');
      await page.waitForLoadState('networkidle');
    }

    // Switch to Email channel
    const emailBtn = page.getByRole('button', { name: 'Email' });
    if (await emailBtn.isVisible()) {
      await emailBtn.click();
    }

    // Fill login form
    await page.locator('input[type="email"]').fill('customer@e2e.laundryku.test');
    await page.locator('input[type="password"]').fill('password123');
    await page.locator('form button[type="submit"]').click();

    // Customer lands on /orders or role home
    await page.waitForURL((url) => !url.pathname.includes('/auth'), { timeout: 20000 });
  }

  async function loginAsMitra(page: Page) {
    await page.goto('/mitra/login');
    await page.waitForLoadState('networkidle');
    await page.locator('#email').fill('mitra@e2e.laundryku.test');
    await page.locator('#password').fill('password123');
    await page.locator('form button[type="submit"]').click();
    await page.waitForURL((url) => url.pathname.startsWith('/store'), { timeout: 20000 });
  }

  async function loginAsCourier(page: Page) {
    await page.goto('/courier/login');
    await page.waitForLoadState('networkidle');
    await page.locator('#email').fill('kurir@e2e.laundryku.test');
    await page.locator('#password').fill('password123');
    await page.locator('form button[type="submit"]').click();
    await page.waitForURL((url) => url.pathname.startsWith('/courier') && !url.pathname.includes('/login'), { timeout: 20000 });
  }

  async function loginAsAdmin(page: Page) {
    await page.goto('/admin/login');
    await page.waitForLoadState('networkidle');
    await page.locator('#email').fill('admin@e2e.laundryku.test');
    await page.locator('#password').fill('admin12345');
    await page.locator('form button[type="submit"]').click();

    // 2FA step
    const firstOtpBox = page.locator('input[inputmode="numeric"]').first();
    await expect(firstOtpBox).toBeVisible({ timeout: 15000 });
    await firstOtpBox.focus();
    await page.keyboard.type('123456');
    await page.locator('form button[type="submit"]').click();
    await page.waitForURL((url) => url.pathname.startsWith('/admin') && !url.pathname.includes('/login'), { timeout: 20000 });
  }

  test('1. Customer login dan membuat order', async ({ page }) => {
    await loginAsCustomer(page);

    // Buka halaman pesan layanan toko
    await page.goto(`/stores/${storeId}/order`);
    await page.waitForLoadState('networkidle');

    // Pastikan alamat terisi
    const addressInput = page.locator('textarea').first();
    await expect(addressInput).toBeVisible({ timeout: 10000 });
    const currentAddress = await addressInput.inputValue();
    if (!currentAddress.trim()) {
      await addressInput.fill('Jl. Siliwangi No. 10, Cirebon');
    }

    // Tambah kuantitas layanan pertama
    const addQtyBtn = page.getByRole('button', { name: 'Tambah kuantitas' }).first();
    await expect(addQtyBtn).toBeVisible({ timeout: 10000 });
    await addQtyBtn.click();

    // Konfirmasi & Buat Pesanan
    const submitBtn = page.getByRole('button', { name: 'Konfirmasi & Buat Pesanan' });
    await expect(submitBtn).toBeEnabled({ timeout: 10000 });
    await submitBtn.click();

    // Verifikasi mendarat di halaman detail order customer
    await page.waitForURL(/\/orders\/\d+/, { timeout: 20000 });
    const match = page.url().match(/\/orders\/(\d+)/);
    expect(match).not.toBeNull();
    order1Id = match![1];

    // Proses antrean notifikasi & pastikan terbuat
    processQueues();
    expect(getWhatsAppLogCount()).toBeGreaterThan(initialLogCount);
  });

  test('2. Toko menugaskan kurir jemput', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await loginAsMitra(page);
    await page.goto(`/store/orders/${order1Id}`);
    await page.waitForLoadState('networkidle');

    // Klik tombol tugaskan kurir jemput
    const assignBtn = page.getByRole('button', { name: /Tugaskan kurir jemput/i });
    await expect(assignBtn).toBeVisible({ timeout: 10000 });
    await assignBtn.click();

    // Assert: Tidak ada error toast, pesan sukses muncul
    await expect(page.locator('text=Gagal menugaskan kurir')).not.toBeVisible();
    await expect(page.locator('text=Kurir berhasil ditugaskan').or(page.locator('text=PICKUP_ASSIGNED'))).toBeVisible({ timeout: 10000 });

    processQueues();
    await context.close();
  });

  test('3. Kurir: mulai jemput, ambil, lalu tiba di toko', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    await loginAsCourier(page);
    await page.goto('/courier/tasks');
    await page.waitForLoadState('networkidle');

    // Klik "Cucian sudah dijemput"
    const pickedUpBtn = page.getByRole('button', { name: 'Cucian sudah dijemput' });
    await expect(pickedUpBtn).toBeVisible({ timeout: 10000 });
    await pickedUpBtn.click();

    // Konfirmasi modal
    const confirmBtn1 = page.getByRole('button', { name: 'Konfirmasi status' });
    await expect(confirmBtn1).toBeVisible({ timeout: 5000 });
    await confirmBtn1.click();
    await expect(page.locator('text=berhasil diperbarui')).toBeVisible({ timeout: 10000 });
    processQueues();

    // Klik "Tiba di outlet"
    const atStoreBtn = page.getByRole('button', { name: 'Tiba di outlet' });
    await expect(atStoreBtn).toBeVisible({ timeout: 10000 });
    await atStoreBtn.click();

    // Konfirmasi modal
    const confirmBtn2 = page.getByRole('button', { name: 'Konfirmasi status' });
    await expect(confirmBtn2).toBeVisible({ timeout: 5000 });
    await confirmBtn2.click();
    await expect(page.locator('text=berhasil diperbarui')).toBeVisible({ timeout: 10000 });
    processQueues();

    // Reload & Assert: Badge "Tugas aktif" kurir = 0 setelah tiba di toko
    await page.reload();
    await page.waitForLoadState('networkidle');
    const activeTab = page.locator('button:has-text("Tugas aktif")');
    await expect(activeTab).toContainText('0');

    await context.close();
  });

  test('4. Toko timbang, Customer setujui harga & bayar', async ({ browser }) => {
    // 4a. Toko input timbangan
    const mitraContext = await browser.newContext();
    const mitraPage = await mitraContext.newPage();
    await loginAsMitra(mitraPage);
    await mitraPage.goto(`/store/orders/${order1Id}`);
    await mitraPage.waitForLoadState('networkidle');

    // Input berat aktual
    const weightInput = mitraPage.locator('input[placeholder="contoh: 4.5"]');
    await expect(weightInput).toBeVisible({ timeout: 10000 });
    await weightInput.fill('2.5');

    const saveWeightBtn = mitraPage.getByRole('button', { name: /Simpan penimbangan/i });
    await saveWeightBtn.click();
    await expect(mitraPage.getByText('Menunggu keputusan pelanggan')).toBeVisible({ timeout: 15000 });

    // Assert: panel toko menampilkan kartu Rincian keuangan dengan baris Biaya jasa dan total tagihan skenario
    const financialCard = mitraPage.locator('div', { has: mitraPage.locator('h3', { hasText: 'Rincian keuangan' }) });
    await expect(financialCard).toBeVisible({ timeout: 10000 });

    const serviceFeeRow = financialCard.locator('div.flex.justify-between', { hasText: 'Biaya jasa' });
    await expect(serviceFeeRow).toBeVisible({ timeout: 10000 });
    await expect(serviceFeeRow.locator('text=Rp17.500')).toBeVisible({ timeout: 10000 });

    await expect(financialCard.locator('text=Berat aktual:')).toBeVisible({ timeout: 10000 });
    await expect(financialCard.locator('text=2.5 kg')).toBeVisible({ timeout: 10000 });

    await expect(financialCard.locator('text=Total tagihan customer:')).toBeVisible({ timeout: 10000 });
    await expect(financialCard.locator('text=Rp18.500')).toBeVisible({ timeout: 10000 });

    // Pertahankan assert existing
    await expect(mitraPage.locator('text=Biaya jasa (')).toBeVisible({ timeout: 10000 });
    await expect(mitraPage.locator('text=Rp17.500').first()).toBeVisible({ timeout: 10000 });

    processQueues();
    await mitraContext.close();

    // 4b. Customer setujui harga dan bayar
    const cusContext = await browser.newContext();
    const cusPage = await cusContext.newPage();
    await loginAsCustomer(cusPage);
    await cusPage.goto(`/orders/${order1Id}`);
    await cusPage.waitForLoadState('networkidle');

    // Klik setujui harga
    const approveBtn = cusPage.getByRole('button', { name: 'Setujui Harga' });
    await expect(approveBtn).toBeVisible({ timeout: 15000 });
    await approveBtn.click();
    await expect(cusPage.locator('text=Harga final berhasil disetujui').or(cusPage.locator('text=Menunggu Pembayaran')).first()).toBeVisible({ timeout: 15000 });
    processQueues();

    // Klik Bayar Sekarang
    const payBtn = cusPage.getByRole('button', { name: 'Bayar Sekarang' });
    await expect(payBtn).toBeVisible({ timeout: 15000 });
    await payBtn.click();

    // Di modal pembayaran, pilih opsi QRIS
    const qrisOption = cusPage.locator('button:has-text("QRIS")');
    if (await qrisOption.isVisible({ timeout: 5000 })) {
      await qrisOption.click();
    }

    // Klik Lanjutkan untuk inisiasi pembayaran QRIS
    const lanjutBtn = cusPage.getByRole('button', { name: 'Lanjutkan' });
    if (await lanjutBtn.isVisible({ timeout: 5000 })) {
      await lanjutBtn.click();
    }

    // Klik simulasi berhasil
    const simBtn = cusPage.getByRole('button', { name: 'Simulasikan Pembayaran Berhasil' });
    await expect(simBtn).toBeVisible({ timeout: 15000 });
    await simBtn.click();
    processQueues();

    // Assert: status Dibayar / Lunas / Pembayaran Berhasil
    await expect(cusPage.locator('text=Pembayaran Berhasil').or(cusPage.locator('text=Lunas')).or(cusPage.locator('text=Dibayar')).first()).toBeVisible({ timeout: 15000 });

    await cusContext.close();
  });

  test('5. Toko: proses pencucian, lalu siap antar', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await loginAsMitra(page);
    await page.goto(`/store/orders/${order1Id}`);
    await page.waitForLoadState('networkidle');

    // Mulai proses pencucian
    const startWashingBtn = page.getByRole('button', { name: /Mulai proses pencucian/i });
    await expect(startWashingBtn).toBeVisible({ timeout: 15000 });
    await startWashingBtn.click();
    await expect(page.locator('text=Proses laundry sedang berjalan').or(page.locator('text=Sedang Dicuci & Diproses')).or(page.locator('text=PROCESSING')).first()).toBeVisible({ timeout: 15000 });
    processQueues();

    // Cucian selesai & siap diantar
    const readyBtn = page.getByRole('button', { name: /Cucian selesai & siap diantar/i });
    await expect(readyBtn).toBeVisible({ timeout: 15000 });
    await readyBtn.click();
    await expect(page.locator('text=Tugaskan kurir pengantaran').or(page.locator('text=Siap Diantar')).or(page.locator('text=READY_FOR_DELIVERY')).first()).toBeVisible({ timeout: 15000 });
    processQueues();

    await context.close();
  });

  test('6. Super Admin menugaskan kurir antar dari dashboard admin', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await loginAsAdmin(page);
    await page.goto('/admin/orders');
    await page.waitForLoadState('networkidle');

    // Cari baris order1 dan buka modal detail
    const orderRow = page.locator('tr:has-text("Laundry Berkah E2E Cirebon")').or(page.locator('tr:has-text("Customer E2E Cirebon")')).first();
    await expect(orderRow).toBeVisible({ timeout: 15000 });
    const detailBtn = orderRow.getByRole('button', { name: /Detail/i });
    if (await detailBtn.isVisible()) {
      await detailBtn.click();
    } else {
      await orderRow.click();
    }

    // Klik tombol "Tugaskan kurir antar"
    const assignDeliveryBtn = page.getByRole('button', { name: 'Tugaskan kurir antar' });
    await expect(assignDeliveryBtn).toBeVisible({ timeout: 10000 });
    await assignDeliveryBtn.click();

    // Assert: sukses tanpa error
    await expect(page.locator('text=Kurir pengantaran berhasil ditugaskan.')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('text=Gagal menugaskan kurir')).not.toBeVisible();
    processQueues();

    // Assert nama kurir tampil sebagai kurir pengantar DAN status order di DB = courier_assigned_for_delivery
    await expect(page.locator('text=Kurir E2E Cirebon').first()).toBeVisible({ timeout: 10000 });
    const dbStatus = getOrderDbStatus(order1Id);
    expect(dbStatus).toBe('courier_assigned_for_delivery');

    await context.close();
  });

  test('7. Kurir: antar, lalu selesai', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await loginAsCourier(page);
    await page.goto('/courier/tasks');
    await page.waitForLoadState('networkidle');

    // Mulai pengantaran
    const outBtn = page.getByRole('button', { name: 'Mulai pengantaran' });
    await expect(outBtn).toBeVisible({ timeout: 10000 });
    await outBtn.click();

    const confirmBtn1 = page.getByRole('button', { name: 'Konfirmasi status' });
    await expect(confirmBtn1).toBeVisible({ timeout: 5000 });
    await confirmBtn1.click();
    await expect(page.locator('text=berhasil diperbarui')).toBeVisible({ timeout: 10000 });
    processQueues();

    // Serah terima selesai
    const finishBtn = page.getByRole('button', { name: 'Serah terima selesai' });
    await expect(finishBtn).toBeVisible({ timeout: 10000 });
    await finishBtn.click();

    const confirmBtn2 = page.getByRole('button', { name: 'Konfirmasi status' });
    await expect(confirmBtn2).toBeVisible({ timeout: 5000 });
    await confirmBtn2.click();
    await expect(page.locator('text=berhasil diperbarui')).toBeVisible({ timeout: 10000 });
    processQueues();

    // Assert: badge aktif = 0
    await page.reload();
    await page.waitForLoadState('networkidle');
    const activeTab = page.locator('button:has-text("Tugas aktif saya")');
    await expect(activeTab).toContainText('0');

    await context.close();
  });

  test('8. Buat order kedua dan tugaskan kurir yang sama', async ({ browser }) => {
    // 8a. Customer buat order kedua
    const cusContext = await browser.newContext();
    const cusPage = await cusContext.newPage();
    await loginAsCustomer(cusPage);
    await cusPage.goto(`/stores/${storeId}/order`);
    await cusPage.waitForLoadState('networkidle');

    const addressInput = cusPage.locator('textarea').first();
    await expect(addressInput).toBeVisible({ timeout: 10000 });
    const currentAddress = await addressInput.inputValue();
    if (!currentAddress.trim()) {
      await addressInput.fill('Jl. Siliwangi No. 10, Cirebon');
    }

    const addQtyBtn = cusPage.getByRole('button', { name: 'Tambah kuantitas' }).first();
    await expect(addQtyBtn).toBeVisible({ timeout: 10000 });
    await addQtyBtn.click();

    const submitBtn = cusPage.getByRole('button', { name: 'Konfirmasi & Buat Pesanan' });
    await expect(submitBtn).toBeEnabled({ timeout: 10000 });
    await submitBtn.click();

    await cusPage.waitForURL(/\/orders\/\d+/, { timeout: 20000 });
    const match = cusPage.url().match(/\/orders\/(\d+)/);
    expect(match).not.toBeNull();
    order2Id = match![1];
    processQueues();
    await cusContext.close();

    // 8b. Toko tugaskan kurir yang sama ke order kedua
    const mitraContext = await browser.newContext();
    const mitraPage = await mitraContext.newPage();
    await loginAsMitra(mitraPage);
    await mitraPage.goto(`/store/orders/${order2Id}`);
    await mitraPage.waitForLoadState('networkidle');

    const assignBtn = mitraPage.getByRole('button', { name: /Tugaskan kurir jemput/i });
    await expect(assignBtn).toBeVisible({ timeout: 10000 });
    await assignBtn.click();

    // Assert: berhasil tanpa error
    await expect(mitraPage.locator('text=Gagal menugaskan kurir')).not.toBeVisible();
    await expect(mitraPage.locator('text=Kurir berhasil ditugaskan').or(mitraPage.locator('text=Kurir Ditugaskan Jemput')).or(mitraPage.locator('text=PICKUP_ASSIGNED')).first()).toBeVisible({ timeout: 10000 });
    processQueues();

    // Assert: assign order kedua sukses DAN task baru courier_id = kurir yang sama
    const order1CourierId = getLatestCourierTaskCourierId(order1Id);
    const order2CourierId = getLatestCourierTaskCourierId(order2Id);
    expect(order2CourierId).toBeGreaterThan(0);
    expect(order2CourierId).toBe(order1CourierId);

    await mitraContext.close();
  });

  test('9. Logout tiap role selalu mendarat di "/"', async ({ browser }) => {
    // 9a. Customer logout
    const cusContext = await browser.newContext();
    const cusPage = await cusContext.newPage();
    await loginAsCustomer(cusPage);
    await cusPage.goto('/orders');
    await cusPage.waitForLoadState('networkidle');

    const menuBtn = cusPage.locator('button[aria-label="Menu Akun"]').first();
    if (await menuBtn.isVisible()) {
      await menuBtn.click();
      await cusPage.locator('button[role="menuitem"]:has-text("Keluar")').click();
    } else {
      const avatarBtn = cusPage.locator('button[aria-haspopup="menu"]').first();
      await avatarBtn.click();
      await cusPage.locator('button[role="menuitem"]:has-text("Keluar")').click();
    }
    await cusPage.waitForURL('http://localhost:3000/', { timeout: 15000 });
    expect(cusPage.url()).toBe('http://localhost:3000/');
    await cusContext.close();

    // 9b. Mitra logout
    const mitraContext = await browser.newContext();
    const mitraPage = await mitraContext.newPage();
    await loginAsMitra(mitraPage);
    const mitraLogout = mitraPage.locator('aside button:has-text("Keluar")').first();
    await mitraLogout.click();
    await mitraPage.waitForURL('http://localhost:3000/', { timeout: 15000 });
    expect(mitraPage.url()).toBe('http://localhost:3000/');
    await mitraContext.close();

    // 9c. Kurir logout
    const kurirContext = await browser.newContext();
    const kurirPage = await kurirContext.newPage();
    await loginAsCourier(kurirPage);
    const kurirLogout = kurirPage.locator('aside button:has-text("Keluar")').first();
    await kurirLogout.click();
    await kurirPage.waitForURL('http://localhost:3000/', { timeout: 15000 });
    expect(kurirPage.url()).toBe('http://localhost:3000/');
    await kurirContext.close();

    // 9d. Admin logout
    const adminContext = await browser.newContext();
    const adminPage = await adminContext.newPage();
    await loginAsAdmin(adminPage);
    const adminLogout = adminPage.locator('aside button:has-text("Keluar")').first();
    await adminLogout.click();
    await adminPage.waitForURL('http://localhost:3000/', { timeout: 15000 });
    expect(adminPage.url()).toBe('http://localhost:3000/');
    await adminContext.close();
  });

  test('10. Cari Toko sebagai customer: request dengan koordinat Cirebon & tanpa teks Bandung', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    await loginAsCustomer(page);

    let nearbyRequestCalled = false;
    let nearbyCoords = { lat: '', lng: '' };

    page.on('request', (req) => {
      const url = req.url();
      if (url.includes('/stores/nearby') || url.includes('/stores?')) {
        nearbyRequestCalled = true;
        try {
          const u = new URL(url);
          nearbyCoords.lat = u.searchParams.get('lat') || '';
          nearbyCoords.lng = u.searchParams.get('lng') || '';
        } catch {
          // ignore invalid url
        }
      }
    });

    await page.goto('/stores');
    await page.waitForLoadState('networkidle');

    // Assert: request stores/nearby dipanggil
    expect(nearbyRequestCalled).toBe(true);

    // Assert: koordinat Cirebon (-6.7053, 108.5555)
    expect(nearbyCoords.lat.startsWith('-6.70') || nearbyCoords.lat.startsWith('-6.7')).toBe(true);
    expect(nearbyCoords.lng.startsWith('108.55') || nearbyCoords.lng.startsWith('108.5')).toBe(true);

    // Assert: teks "Bandung" tidak pernah muncul di halaman
    const pageText = await page.textContent('body');
    expect(pageText).not.toContain('Bandung');

    await context.close();
  });

  test('Verifikasi Tambahan: Job notifikasi WhatsApp tersimpan di database', async () => {
    processQueues();
    const finalLogCount = getWhatsAppLogCount();
    expect(finalLogCount).toBeGreaterThan(initialLogCount);
  });
});
