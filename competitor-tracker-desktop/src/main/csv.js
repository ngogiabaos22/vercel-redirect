'use strict';
// Đọc file CSV danh sách Page. Cột: Link/ID Page, Nhóm (nhiều nhóm cách nhau bởi "|"), Ngày bắt đầu (YYYY-MM-DD, tuỳ chọn).

function parseCsv(text) {
  const s = String(text).replace(/^﻿/, '');
  const firstLine = s.split(/\r?\n/, 1)[0] || '';
  const delim = (firstLine.match(/;/g) || []).length > (firstLine.match(/,/g) || []).length ? ';' : ',';
  const rows = [];
  let row = []; let field = ''; let q = false;
  for (let i = 0; i < s.length; i++) {
    const c = s[i];
    if (q) {
      if (c === '"') { if (s[i + 1] === '"') { field += '"'; i++; } else q = false; } else field += c;
    } else if (c === '"') q = true;
    else if (c === delim) { row.push(field); field = ''; }
    else if (c === '\n' || c === '\r') {
      if (c === '\r' && s[i + 1] === '\n') i++;
      row.push(field); rows.push(row); row = []; field = '';
    } else field += c;
  }
  if (field !== '' || row.length) { row.push(field); rows.push(row); }
  return rows.map((r) => r.map((x) => x.trim())).filter((r) => r.some(Boolean));
}

/** @returns {{input:string, groups:string[], startDate:string|null}[]} */
function pagesFromCsv(text) {
  const rows = parseCsv(text);
  if (rows.length && /link|page|url|id/i.test(rows[0][0]) && !/facebook\.com|^\d+$/i.test(rows[0][0])) rows.shift();
  return rows.map((r) => ({
    input: r[0],
    groups: (r[1] || '').split('|').map((x) => x.trim()).filter(Boolean),
    startDate: /^\d{4}-\d{2}-\d{2}$/.test(r[2] || '') ? r[2] : null
  })).filter((r) => r.input);
}

const TEMPLATE = '﻿Link hoặc Page ID,Nhóm (cách nhau bởi |),Lấy bài từ ngày (YYYY-MM-DD)\nhttps://facebook.com/exampleA,Nhóm 1|Nhóm 3,2026-09-01\n1029384756,Nhóm 2,\n';

module.exports = { parseCsv, pagesFromCsv, TEMPLATE };
