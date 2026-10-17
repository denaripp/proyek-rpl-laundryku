'use client';

import React, { useMemo } from 'react';
import { Check, X, ShieldCheck, ShieldAlert } from 'lucide-react';

interface PasswordStrengthMeterProps {
  password: string;
  name?: string;
  destination?: string;
}

const COMMON_PASSWORDS = [
  'password',
  'password123',
  'password1234',
  'rahasia',
  'rahasia123',
  'admin123',
  'admin1234',
  'administrator',
  'qwerty',
  'qwerty123',
  'qwertyuiop',
  'asdfghjk',
  'asdfghjkl',
  'zxcvbnm',
  '12345678',
  '123456789',
  '1234567890',
  '87654321',
  'indonesia',
  'indonesia123',
  'bismillah',
  'bismillah123',
  'merdeka123',
  'semangat123',
  'sayangku',
  'iloveyou',
  'testing123',
  'laundry123',
  'laundryku',
  'laundryku123',
];

const SEQUENCES = [
  '01234567890123456789',
  '98765432109876543210',
  'abcdefghijklmnopqrstuvwxyz',
  'zyxwvutsrqponmlkjihgfedcba',
  'qwertyuiopasdfghjklzxcvbnm',
  'mnbvcxzlkjhgfdsapoiuytrewq',
];

export function PasswordStrengthMeter({
  password,
  name = '',
  destination = '',
}: PasswordStrengthMeterProps) {
  const analysis = useMemo(() => {
    if (!password) {
      return {
        hasMinLength: false,
        noRepetitionOrSequence: false,
        noUserData: true,
        notCommon: true,
        score: 0,
        level: '',
        color: '',
      };
    }

    const cleanPass = password.toLowerCase().replace(/\s+/g, '');
    const len = password.length;

    // 1. Min 8 length
    const hasMinLength = len >= 8;

    // 2. Repetition & sequences
    let isRepetitiveOrSequence = false;

    // All same character
    const uniqueChars = new Set(Array.from(password));
    if (uniqueChars.size <= 1) {
      isRepetitiveOrSequence = true;
    }

    // Repeating patterns (abababab, 12341234, etc.)
    if (/^(.{2,4})\1+$/i.test(cleanPass)) {
      isRepetitiveOrSequence = true;
    }

    // Common sequences
    for (const seq of SEQUENCES) {
      if (len <= 12 && seq.includes(cleanPass)) {
        isRepetitiveOrSequence = true;
        break;
      }
      for (let i = 0; i <= cleanPass.length - 6; i++) {
        const sub = cleanPass.substring(i, i + 6);
        if (seq.includes(sub) && len <= 10) {
          isRepetitiveOrSequence = true;
          break;
        }
      }
      if (isRepetitiveOrSequence) break;
    }

    const noRepetitionOrSequence = !isRepetitiveOrSequence;

    // 3. User data check
    let containsUserData = false;

    if (name && name.trim()) {
      const nameParts = name
        .toLowerCase()
        .replace(/[^\p{L}\p{N}]/gu, ' ')
        .split(' ')
        .filter((p) => p.length >= 3);

      for (const part of nameParts) {
        if (cleanPass.includes(part)) {
          containsUserData = true;
          break;
        }
      }
    }

    if (!containsUserData && destination && destination.trim()) {
      const destLower = destination.toLowerCase().trim();
      if (destLower.includes('@')) {
        const localPart = destLower.split('@')[0];
        const localTokens = localPart.split(/[._\-+]/).filter((t) => t.length >= 3);
        localTokens.push(localPart.replace(/[^a-z0-9]/g, ''));
        for (const token of localTokens) {
          if (token.length >= 3 && cleanPass.includes(token)) {
            containsUserData = true;
            break;
          }
        }
      } else {
        const digits = destLower.replace(/\D/g, '');
        if (digits.length >= 6) {
          const sig = digits.slice(-8);
          if (cleanPass.includes(sig) || cleanPass.includes(digits)) {
            containsUserData = true;
          }
        }
      }
    }

    const noUserData = !containsUserData;

    // 4. Common words & 'laundryku'
    const containsLaundryku = cleanPass.includes('laundryku');
    const isCommon = COMMON_PASSWORDS.includes(cleanPass) || containsLaundryku;
    const notCommon = !isCommon;

    // Determine strength level
    let score = 0;
    let level = 'Lemah';
    let color = 'bg-rose-500 text-rose-700';

    if (hasMinLength && noRepetitionOrSequence && notCommon && noUserData) {
      if (len >= 14 || (password.includes(' ') && len >= 12)) {
        score = 3;
        level = 'Kuat';
        color = 'bg-teal-500 text-teal-700';
      } else {
        score = 2;
        level = 'Cukup';
        color = 'bg-amber-500 text-amber-700';
      }
    } else if (password.length > 0) {
      score = 1;
      level = 'Lemah';
      color = 'bg-rose-500 text-rose-700';
    }

    return {
      hasMinLength,
      noRepetitionOrSequence,
      noUserData,
      notCommon,
      score,
      level,
      color,
    };
  }, [password, name, destination]);

  if (!password) {
    return null;
  }

  return (
    <div className="mt-2.5 space-y-3 p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs transition-all animate-in fade-in duration-200">
      {/* Strength Bar & Label */}
      <div className="space-y-1.5">
        <div className="flex items-center justify-between font-semibold">
          <span className="flex items-center gap-1.5 text-navy-900">
            {analysis.score === 3 ? (
              <ShieldCheck className="w-4 h-4 text-teal-600" />
            ) : (
              <ShieldAlert className="w-4 h-4 text-amber-600" />
            )}
            <span>Kekuatan Password:</span>
          </span>
          <span
            className={`px-2 py-0.5 rounded-full text-[11px] font-bold ${
              analysis.score === 3
                ? 'bg-teal-100 text-teal-800'
                : analysis.score === 2
                ? 'bg-amber-100 text-amber-800'
                : 'bg-rose-100 text-rose-800'
            }`}
          >
            {analysis.level}
          </span>
        </div>

        {/* 3-segment Progress Bar */}
        <div className="grid grid-cols-3 gap-1.5 h-1.5">
          <div
            className={`rounded-full transition-all duration-300 ${
              analysis.score >= 1 ? (analysis.score === 1 ? 'bg-rose-500' : analysis.score === 2 ? 'bg-amber-500' : 'bg-teal-500') : 'bg-slate-200'
            }`}
          />
          <div
            className={`rounded-full transition-all duration-300 ${
              analysis.score >= 2 ? (analysis.score === 2 ? 'bg-amber-500' : 'bg-teal-500') : 'bg-slate-200'
            }`}
          />
          <div
            className={`rounded-full transition-all duration-300 ${
              analysis.score === 3 ? 'bg-teal-500' : 'bg-slate-200'
            }`}
          />
        </div>
      </div>

      {/* Dynamic Checklist */}
      <div className="space-y-1.5 pt-1.5 border-t border-slate-200">
        <div className="text-[11px] font-bold text-slate-700">Panduan Password:</div>
        <ul className="space-y-1 text-[11px]">
          <li className={`flex items-center gap-2 ${analysis.hasMinLength ? 'text-teal-700 font-medium' : 'text-slate-500'}`}>
            {analysis.hasMinLength ? (
              <Check className="w-3.5 h-3.5 text-teal-600 shrink-0" />
            ) : (
              <X className="w-3.5 h-3.5 text-slate-400 shrink-0" />
            )}
            <span>Minimal 8 karakter (spasi &amp; simbol diperbolehkan)</span>
          </li>
          <li className={`flex items-center gap-2 ${analysis.noRepetitionOrSequence ? 'text-teal-700 font-medium' : 'text-slate-500'}`}>
            {analysis.noRepetitionOrSequence ? (
              <Check className="w-3.5 h-3.5 text-teal-600 shrink-0" />
            ) : (
              <X className="w-3.5 h-3.5 text-slate-400 shrink-0" />
            )}
            <span>Bukan karakter berulang atau urutan sederhana (seperti 12345678, xxxxxxxx)</span>
          </li>
          <li className={`flex items-center gap-2 ${analysis.noUserData ? 'text-teal-700 font-medium' : 'text-slate-500'}`}>
            {analysis.noUserData ? (
              <Check className="w-3.5 h-3.5 text-teal-600 shrink-0" />
            ) : (
              <X className="w-3.5 h-3.5 text-slate-400 shrink-0" />
            )}
            <span>Tidak mengandung nama atau kontak Anda</span>
          </li>
          <li className={`flex items-center gap-2 ${analysis.notCommon ? 'text-teal-700 font-medium' : 'text-slate-500'}`}>
            {analysis.notCommon ? (
              <Check className="w-3.5 h-3.5 text-teal-600 shrink-0" />
            ) : (
              <X className="w-3.5 h-3.5 text-slate-400 shrink-0" />
            )}
            <span>Bukan kata umum dan tidak mengandung kata &ldquo;laundryku&rdquo;</span>
          </li>
        </ul>
      </div>
    </div>
  );
}

