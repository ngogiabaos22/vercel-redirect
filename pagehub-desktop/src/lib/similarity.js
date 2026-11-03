// Share of word pairs from the shorter text that also appear in the other text (0–1).
function bigrams(s) {
  const w = String(s || '').toLowerCase().normalize('NFC').replace(/[^\p{L}\p{N}\s]/gu, ' ').split(/\s+/).filter(Boolean);
  const out = new Set();
  for (let i = 0; i < w.length - 1; i++) out.add(w[i] + ' ' + w[i + 1]);
  return out;
}

function similarity(a, b) {
  const A = bigrams(a);
  const B = bigrams(b);
  if (!A.size || !B.size) return 0;
  let n = 0;
  A.forEach(x => { if (B.has(x)) n++; });
  return n / Math.min(A.size, B.size);
}

module.exports = { similarity };
