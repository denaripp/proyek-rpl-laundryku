<?php

namespace App\Services\Ai;

class AiKnowledgeBaseService
{
    protected ?array $guides = null;

    /**
     * Get all procedural guides loaded from resources/ai/knowledge/*.php.
     *
     * @return array<string, array>
     */
    public function getAllGuides(): array
    {
        if ($this->guides !== null) {
            return $this->guides;
        }

        $baseDir = resource_path('ai/knowledge');
        $files = [
            'customer' => $baseDir . '/customer_guides.php',
            'courier' => $baseDir . '/courier_guides.php',
            'store_admin' => $baseDir . '/store_guides.php',
            'super_admin' => $baseDir . '/admin_guides.php',
        ];

        $loaded = [];
        foreach ($files as $role => $file) {
            if (file_exists($file)) {
                $content = require $file;
                if (is_array($content)) {
                    foreach ($content as $id => $item) {
                        $item['role'] = $item['role'] ?? $role;
                        $item['id'] = $item['id'] ?? $id;
                        $item['intent'] = $item['intent'] ?? $item['id'] ?? $id;
                        $loaded[$id] = $item;
                    }
                }
            }
        }

        $this->guides = $loaded;
        return $this->guides;
    }

    /**
     * Get single guide by exact ID.
     */
    public function getGuide(string $id): ?array
    {
        $all = $this->getAllGuides();
        return $all[$id] ?? null;
    }

    /**
     * Get formatted response for a guide ID or intent.
     */
    public function getGuideResponse(string $intentOrId, ?string $userRole = null): string
    {
        $guide = $this->getGuide($intentOrId);
        if ($guide) {
            return $this->formatGuideResponse($guide);
        }

        $found = $this->findGuideByQuery($intentOrId, $userRole);
        if ($found) {
            return $this->formatGuideResponse($found);
        }

        return $this->getGuideTopicsMenu($userRole);
    }

    /**
     * Format a guide item into clean, numbered Indonesian text.
     */
    public function formatGuideResponse(array $guide): string
    {
        $title = $guide['title'] ?? 'Panduan Layanan LaundryKu';
        $steps = $guide['steps'] ?? [];
        $note = $guide['note'] ?? null;

        $lines = ["{$title}:"];
        foreach ($steps as $index => $step) {
            if (str_starts_with(trim($step), 'a.') || str_starts_with(trim($step), 'b.')) {
                $lines[] = "   " . trim($step);
            } else {
                $num = $index + 1;
                $lines[] = "{$num}. {$step}";
            }
        }

        if ($note) {
            $lines[] = "\nCatatan: {$note}";
        }

        return implode("\n", $lines);
    }

    /**
     * Find best matching guide for a raw or normalized user query.
     */
    public function findGuideByQuery(string $query, ?string $userRole = null): ?array
    {
        $normalized = mb_strtolower(trim($query));
        $all = $this->getAllGuides();

        $bestGuide = null;
        $highestScore = 0;

        foreach ($all as $guide) {
            $score = 0;

            // Role priority boost if role matches
            $guideRole = $guide['role'] ?? 'customer';
            $isRoleMatch = ($userRole === null || $userRole === $guideRole || ($userRole === 'store_owner' && $guideRole === 'store_admin') || ($userRole === 'platform_owner' && $guideRole === 'super_admin'));

            // Check exact keyword list
            $keywords = $guide['keywords'] ?? [];
            foreach ($keywords as $kw) {
                $kwLower = mb_strtolower(trim($kw));
                if (str_contains($normalized, $kwLower)) {
                    $score += 30;
                    if ($normalized === $kwLower || str_starts_with($normalized, $kwLower)) {
                        $score += 25;
                    }
                }
            }

            // Word overlap check on title
            $titleWords = explode(' ', mb_strtolower($guide['title'] ?? ''));
            foreach ($titleWords as $tw) {
                if (mb_strlen($tw) >= 4 && str_contains($normalized, $tw)) {
                    $score += 8;
                }
            }

            if ($isRoleMatch) {
                $score += 15;
            } else {
                // Minor penalty if query explicitly targets another role's guide
                $score -= 10;
            }

            if ($score > $highestScore) {
                $highestScore = $score;
                $bestGuide = $guide;
            }
        }

        // Threshold to accept match
        if ($highestScore >= 25 && $bestGuide !== null) {
            return $bestGuide;
        }

        return null;
    }

    /**
     * Generate structured menu of available guide topics tailored by role.
     */
    public function getGuideTopicsMenu(?string $userRole = null): string
    {
        $all = $this->getAllGuides();
        $role = $userRole ?? 'customer';

        if ($role === 'courier') {
            return "Berikut topik Panduan yang tersedia untuk Kurir di LaundryKu:\n"
                . "1. Cara Mengambil & Menjalankan Tugas (tanyakan: \"cara ambil tugas\")\n"
                . "2. Penagihan & Penyetoran COD Tunai (tanyakan: \"cara tagih cod\")\n"
                . "3. Mengatur Status Online / Offline (tanyakan: \"cara atur status online\")\n"
                . "Silakan tanyakan salah satu panduan di atas!";
        }

        if (in_array($role, ['store_admin', 'store_owner'], true)) {
            return "Berikut topik Panduan yang tersedia untuk Mitra Toko di LaundryKu:\n"
                . "1. Mengelola Pesanan Masuk (tanyakan: \"cara proses pesanan masuk\")\n"
                . "2. Penimbangan Cucian & Berat Riil (tanyakan: \"cara timbang cucian\")\n"
                . "3. Menambah Layanan & Mengatur Harga (tanyakan: \"cara tambah layanan\")\n"
                . "4. Mengatur Jam Buka & Operasional (tanyakan: \"cara atur jam buka\")\n"
                . "5. Menangani Komplain Pelanggan (tanyakan: \"cara tangani komplain\")\n"
                . "6. Manajemen & Perekrutan Kurir (tanyakan: \"cara kelola kurir\")\n"
                . "Silakan tanyakan salah satu panduan di atas!";
        }

        if (in_array($role, ['super_admin', 'platform_owner', 'platform_admin'], true)) {
            return "Berikut topik Panduan untuk Platform Admin di LaundryKu:\n"
                . "1. Verifikasi & Approval Mitra/Kurir (tanyakan: \"cara setujui toko\")\n"
                . "2. Monitoring Performa & Ekosistem Platform (tanyakan: \"cara lihat overview platform\")\n"
                . "3. Manajemen Pengguna & Toko (tanyakan: \"cara kelola pengguna\")\n"
                . "Silakan tanyakan salah satu panduan di atas!";
        }

        // Default Customer
        return "Berikut topik Panduan yang tersedia di LaundryKu:\n"
            . "1. Cara Memesan Laundry Baru (tanyakan: \"cara pesan\")\n"
            . "2. Cara Pembayaran QRIS / VA / COD (tanyakan: \"cara bayar\")\n"
            . "3. Cara Melacak Status Pesanan (tanyakan: \"cara lacak pesanan\")\n"
            . "4. Cara Membatalkan Pesanan (tanyakan: \"cara batalkan pesanan\")\n"
            . "5. Cara Mengajukan Komplain & Garansi (tanyakan: \"cara komplain\")\n"
            . "6. Cara Memberi Ulasan & Rating (tanyakan: \"cara ulas toko\")\n"
            . "7. Cara Menambah Alamat Baru via GPS (tanyakan: \"cara tambah alamat\")\n"
            . "8. Cara Bergabung Jadi Mitra Toko / Kurir (tanyakan: \"cara gabung mitra\")\n"
            . "Ketik topik yang ingin Anda ketahui!";
    }

    public function getFallbackSnippets(string $query, ?string $userRole = null): string
    {
        return $this->getRelevantKnowledgeSnippets($query, $userRole);
    }

    /**
     * Retrieve concise knowledge snippets to inject into fallback model context.
     */
    public function getRelevantKnowledgeSnippets(string $query, ?string $userRole = null, int $limit = 2): string
    {
        $all = $this->getAllGuides();
        $normalized = mb_strtolower(trim($query));

        $scored = [];
        foreach ($all as $id => $guide) {
            $score = 0;
            $keywords = $guide['keywords'] ?? [];
            foreach ($keywords as $kw) {
                if (str_contains($normalized, mb_strtolower($kw))) {
                    $score += 20;
                }
            }
            if ($score > 0) {
                $scored[$id] = $score;
            }
        }

        arsort($scored);
        $topIds = array_slice(array_keys($scored), 0, $limit);

        if (empty($topIds)) {
            // Default to general customer ordering and payment guides if no specific keyword scored
            $topIds = ['guide_order', 'guide_payment'];
        }

        $snippets = [];
        foreach ($topIds as $id) {
            if (isset($all[$id])) {
                $snippets[] = $this->formatGuideResponse($all[$id]);
            }
        }

        return implode("\n\n---\n\n", $snippets);
    }
}
