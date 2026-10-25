<?php

return [
    'guide_admin_approvals' => [
        'id' => 'guide_admin_approvals',
        'role' => 'super_admin',
        'title' => 'Panduan Verifikasi & Persetujuan Mitra Toko / Kurir (Platform Admin)',
        'keywords' => [
            'approval toko', 'approval kurir', 'setujui toko', 'verifikasi kurir',
            'persetujuan mitra', 'review toko', 'verifikasi mitra', 'terima toko',
        ],
        'steps' => [
            'Masuk ke "Portal Platform Admin" (/admin).',
            'Buka menu "Persetujuan Mitra & Kurir" (/admin/approvals).',
            'Pilih pendaftaran yang berstatus "Menunggu Verifikasi" (submitted).',
            'Periksa kelengkapan berkas: identitas pemilik, legalitas NIB/SIUP, lokasi GPS outlet toko, atau dokumen SIM & KTP kurir.',
            'Klik tombol "Setujui (Approve)" untuk mengaktifkan toko/kurir di ekosistem marketplace, atau klik "Tolak (Reject)" dengan menyertakan alasan penolakan yang jelas.',
        ],
        'note' => 'Setelah disetujui, toko atau kurir akan langsung aktif dan dapat melayani transaksi pengguna.',
    ],

    'guide_admin_overview' => [
        'id' => 'guide_admin_overview',
        'role' => 'super_admin',
        'title' => 'Panduan Monitoring Performa & Ekosistem Platform LaundryKu',
        'keywords' => [
            'overview platform', 'ringkasan platform', 'monitoring admin',
            'performa sistem', 'statistik platform', 'laporan admin',
        ],
        'steps' => [
            'Masuk ke halaman utama "Dashboard Admin" (/admin).',
            'Di layar utama, pantau metrik kunci: Total Toko Aktif, Total Pesanan Berjalan, Volume Transaksi Harian, dan Tingkat Komplain.',
            'Gunakan menu "Laporan Keuangan" untuk memantau perputaran dana transaksi, potongan komisi platform, dan pencairan saldo mitra (settlement).',
            'Gunakan menu "Monitoring Pengantaran" untuk memantau armada kurir aktif dan kecepatan penyelesaian tugas di lapangan.',
        ],
        'note' => 'Platform admin memiliki wewenang penuh menjaga stabilitas, keamanan, dan keadilan transaksi di seluruh ekosistem.',
    ],
];
