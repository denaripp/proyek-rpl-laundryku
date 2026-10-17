// test-16-scenarios.mjs
const scenarios = [
  { id: 1, query: "Mau batalin pesanan.", expectedTerms: ["batal", "/orders"] },
  { id: 2, query: "Kurir gue belum dateng.", expectedTerms: ["kurir", "/orders", "/chat"] },
  { id: 3, query: "Gue mau komplain.", expectedTerms: ["komplain", "1x24", "/orders"] },
  { id: 4, query: "Cara hubungi CS?", expectedTerms: ["cs", "/chat"] },
  { id: 5, query: "Laundry gue udah lama banget.", expectedTerms: ["reguler", "express", "/orders"] },
  { id: 6, query: "Uang gue balik ga?", expectedTerms: ["refund", "xendit"] },
  { id: 7, query: "Pembayaran gue gagal.", expectedTerms: ["bayar", "/orders"] },
  { id: 8, query: "Baju gue rusak setelah dicuci.", expectedTerms: ["foto", "komplain", "1x24"] },
  { id: 9, query: "Gimana cara daftar mitra?", expectedTerms: ["mitra", "toko", "/upgrade"] },
  { id: 10, query: "Mau jadi kurir.", expectedTerms: ["kurir", "sim c", "/upgrade"] },
  { id: 11, query: "Alamat pesanan salah.", expectedTerms: ["alamat", "/chat"] },
  { id: 12, query: "Pesanan gue belum berubah status.", expectedTerms: ["status", "/chat"] },
  { id: 13, query: "Gimana cara daftar?", expectedTerms: ["daftar", "otp", "/auth"] },
  {
    id: 14,
    query: "Terus syaratnya apa?",
    history: [
      { role: "user", content: "Mau jadi kurir." },
      { role: "assistant", content: "Silakan daftar kurir di /upgrade." }
    ],
    expectedTerms: ["sim c", "ktp", "/upgrade"]
  },
  { id: 15, query: "Kurir blm dateng nih, laundryan gue blom dijemput", expectedTerms: ["kurir", "/orders"] },
  { id: 16, query: "Toko Laundry Berkah buka pas Idul Fitri ga?", expectedTerms: ["toko", "jam operasional", "/stores"] },
];

async function runTests() {
  console.log("=== TESTING 16 NATURAL QUERY SCENARIOS ON NEXT.JS (/api/chat) ===\n");
  
  let passedCount = 0;
  for (const s of scenarios) {
    const messages = s.history ? [...s.history, { role: "user", content: s.query }] : [{ role: "user", content: s.query }];
    
    try {
      const res = await fetch("http://localhost:3000/api/chat", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ messages })
      });
      
      const data = await res.json();
      const rawAnswer = data.data?.answer || data.content || data.reply || "";
      const content = rawAnswer.toLowerCase();
      
      const missingTerms = s.expectedTerms.filter(term => !content.includes(term.toLowerCase()));
      const isClarificationStonewall = content.includes("bisa dijelaskan lebih spesifik") || content.includes("mohon berikan detail pertanyaan");
      
      if (missingTerms.length === 0 && !isClarificationStonewall) {
        console.log(`[PASS] Scenario ${s.id}: "${s.query}"`);
        console.log(`       Preview: ${rawAnswer.slice(0, 110).replace(/\n/g, ' ')}...\n`);
        passedCount++;
      } else {
        console.error(`[FAIL] Scenario ${s.id}: "${s.query}"`);
        console.error(`       Missing terms: ${missingTerms.join(", ")}`);
        console.error(`       Is clarification stonewall: ${isClarificationStonewall}`);
        console.error(`       Full response: ${rawAnswer}\n`);
      }
    } catch (err) {
      console.error(`[ERROR] Scenario ${s.id} failed with fetch error:`, err.message);
    }
  }

  console.log(`\nNext.js API Result: ${passedCount}/${scenarios.length} scenarios passed.\n`);
}

runTests();
