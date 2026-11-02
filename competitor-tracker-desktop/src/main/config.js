'use strict';
// Cấu hình mặc định. Người dùng chỉnh trong màn hình Cài đặt; bí mật (token, key) lưu riêng và được mã hoá.

function isoDaysAgo(days) {
  const d = new Date(Date.now() - days * 86400000);
  return d.toISOString().slice(0, 10);
}

const DEFAULTS = {
  meta: { appId: '', version: 'v21.0', baseUrl: 'https://graph.facebook.com' },
  crawl: {
    enabled: true,
    time: '02:00',
    oldPostsLimit: 5,
    workers: 3,
    retries: 3,
    defaultStartDate: isoDaysAgo(30),
    catchUp: true,
    initialOnAdd: true
  },
  images: {
    enabled: true,
    time: '03:00',
    withinDays: 3,
    maxPerDay: 2000,
    maxPerPost: 10,
    minReactions: null,
    minComments: null,
    minShares: null,
    mode: 'and',
    naPolicy: 'ignore',
    // local | local_drive | drive
    storage: 'local_drive',
    folder: '',
    retentionMonths: 0
  },
  drive: {
    // service_account | oauth
    authType: 'service_account',
    rootFolderId: '',
    oauthClientId: '',
    accountEmail: ''
  },
  sheets: {
    enabled: false,
    spreadsheetId: '',
    // after_images | time | manual
    schedule: 'after_images',
    time: '04:00',
    // update | append
    writeMode: 'update',
    summaryTab: true,
    // drive | local | both
    linkType: 'drive',
    onlyMatched: false
  },
  app: { minimizeToTray: true, launchAtLogin: false }
};

function merge(base, over) {
  const out = Array.isArray(base) ? [...base] : { ...base };
  for (const [k, v] of Object.entries(over || {})) {
    if (v && typeof v === 'object' && !Array.isArray(v) && base[k] && typeof base[k] === 'object') out[k] = merge(base[k], v);
    else out[k] = v;
  }
  return out;
}

function loadConfig(store) {
  return merge(DEFAULTS, store.getSetting('config', {}));
}

function saveConfig(store, patch) {
  const cur = loadConfig(store);
  const next = merge(cur, patch);
  validate(next);
  store.setSetting('config', next);
  return next;
}

const HHMM = /^([01]\d|2[0-3]):[0-5]\d$/;
function validate(c) {
  for (const [label, t] of [['Giờ thu thập', c.crawl.time], ['Giờ tải ảnh', c.images.time], ['Giờ đồng bộ Sheet', c.sheets.time]]) {
    if (!HHMM.test(t)) throw new Error(`${label} phải có dạng HH:MM.`);
  }
  const ints = [
    ['Số bài cũ cập nhật', c.crawl.oldPostsLimit, 0, 100], ['Số luồng chạy song song', c.crawl.workers, 1, 20],
    ['Số lần thử lại', c.crawl.retries, 0, 10], ['Số ngày xét bài', c.images.withinDays, 1, 365],
    ['Tối đa ảnh / ngày', c.images.maxPerDay, 1, 100000], ['Tối đa ảnh / bài', c.images.maxPerPost, 1, 100]
  ];
  for (const [label, v, min, max] of ints) {
    if (!Number.isInteger(Number(v)) || Number(v) < min || Number(v) > max) throw new Error(`${label} phải là số nguyên từ ${min} đến ${max}.`);
  }
  if (c.crawl.defaultStartDate && !/^\d{4}-\d{2}-\d{2}$/.test(c.crawl.defaultStartDate)) throw new Error('Ngày bắt đầu lấy bài không hợp lệ.');
}

module.exports = { DEFAULTS, loadConfig, saveConfig, merge };
