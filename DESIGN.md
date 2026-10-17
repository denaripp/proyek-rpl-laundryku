---
version: alpha
name: Laundryku Fresh
description: Clean, fresh, trustworthy marketplace look for a laundry platform.
colors:
  primary: "#0E7490"
  secondary: "#155E75"
  accent: "#06B6D4"
  neutral: "#F8FAFC"
  ink: "#0F172A"
  success: "#16A34A"
  warning: "#D97706"
  danger: "#DC2626"
typography:
  h1:
    fontFamily: Plus Jakarta Sans
    fontSize: 2.25rem
    fontWeight: 700
    lineHeight: 1.15
    letterSpacing: "-0.02em"
  h2:
    fontFamily: Plus Jakarta Sans
    fontSize: 1.5rem
    fontWeight: 600
    lineHeight: 1.2
  body-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 1rem
    fontWeight: 400
    lineHeight: 1.6
  label-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 0.875rem
    fontWeight: 500
    lineHeight: 1.4
rounded:
  sm: 6px
  md: 10px
  lg: 16px
  full: 9999px
spacing:
  sm: 8px
  md: 16px
  lg: 24px
  xl: 40px
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "#FFFFFF"
    rounded: "{rounded.md}"
    padding: 12px 20px
  button-primary-hover:
    backgroundColor: "{colors.secondary}"
    textColor: "#FFFFFF"
    rounded: "{rounded.md}"
    padding: 12px 20px
  button-secondary:
    backgroundColor: "#FFFFFF"
    textColor: "{colors.primary}"
    rounded: "{rounded.md}"
    padding: 12px 20px
  card:
    backgroundColor: "#FFFFFF"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
    padding: 24px
  badge-status:
    backgroundColor: "#E0F2FE"
    textColor: "{colors.secondary}"
    rounded: "{rounded.full}"
    padding: 4px 12px
  input-field:
    backgroundColor: "#FFFFFF"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: 12px 16px
---

## Overview

Laundryku adalah marketplace laundry: bersih, segar, bisa dipercaya.
Nuansa: seperti laundry yang baru selesai — putih dominan, aksen biru-cyan,
banyak white space. Mobile-first karena mayoritas customer dan courier
pakai HP. Satu halaman = satu tujuan; hindari dekorasi berlebihan.

## Colors

- **Primary (#0E7490):** cyan-700 — semua CTA utama, link aktif, fokus.
- **Accent (#06B6D4):** highlight ringan, ikon, elemen ilustratif.
- **Neutral (#F8FAFC):** background halaman; card selalu putih di atasnya.
- **Ink (#0F172A):** teks utama. Jangan pakai hitam murni #000.
- Status: success hijau, warning oranye, danger merah — hanya untuk status,
  bukan dekorasi.

## Typography

Plus Jakarta Sans untuk semua (ramah, modern, dukungan Latin bagus).
Hierarki ketat: satu h1 per halaman, body 16px minimum di mobile.

## Layout

- Container max-width 1200px, padding horizontal 16px mobile / 24px desktop.
- Grid card store: 1 kolom mobile, 2 tablet, 3-4 desktop.
- Navbar sticky; bottom navigation untuk customer mobile.
- Spacing konsisten kelipatan 8px.

## Components

- `button-primary` adalah satu-satunya CTA kuat per view.
- `badge-status` untuk status order (pending, picked_up, washing, delivery, done).
- Card punya radius besar (16px) + border tipis, bukan shadow berat.
- Form: label di atas input, error merah di bawah field.

## Do's and Don'ts

Do: foto store asli di card, harga tampil jelas, status order visual
(timeline/stepper), kontras teks minimal WCAG AA.
Don't: gradient ramai, lebih dari 2 font, warna primer untuk elemen
dekoratif, tabel lebar tanpa scroll horizontal di mobile.