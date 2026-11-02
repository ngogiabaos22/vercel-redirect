'use strict';
// Lưu bí mật (Meta token, Google key, refresh token) đã mã hoá bằng cơ chế của hệ điều hành
// (Windows DPAPI / macOS Keychain) thông qua Electron safeStorage. Không bao giờ gửi giá trị thật ra giao diện.

const NAMES = ['metaToken', 'googleServiceAccount', 'googleOAuthSecret', 'googleRefreshToken', 'appsScriptKey'];

class Secrets {
  /** @param {import('./db').Store} store @param {{encrypt:(s:string)=>Buffer, decrypt:(b:Buffer)=>string, available:boolean}} crypto */
  constructor(store, crypto) {
    this.store = store;
    this.crypto = crypto;
  }
  set(name, value) {
    if (!NAMES.includes(name)) throw new Error('Tên bí mật không hợp lệ');
    if (value === null || value === undefined || value === '') { this.store.run('DELETE FROM settings WHERE key = ?', `secret:${name}`); return; }
    const enc = this.crypto.available ? this.crypto.encrypt(String(value)).toString('base64') : Buffer.from(String(value)).toString('base64');
    this.store.setSetting(`secret:${name}`, { enc, os: this.crypto.available });
  }
  get(name) {
    const v = this.store.getSetting(`secret:${name}`, null);
    if (!v) return '';
    const buf = Buffer.from(v.enc, 'base64');
    return v.os ? this.crypto.decrypt(buf) : buf.toString('utf8');
  }
  has(name) { return !!this.store.getSetting(`secret:${name}`, null); }
  // Trạng thái hiển thị cho giao diện — chỉ có/không, không lộ giá trị
  status() { return Object.fromEntries(NAMES.map((n) => [n, this.has(n)])); }
}

module.exports = { Secrets, NAMES };
