'use strict';
const { app, BrowserWindow, ipcMain, dialog, shell, Tray, Menu, nativeImage, safeStorage } = require('electron');
const path = require('node:path');
const fs = require('node:fs');
const { Store } = require('./db');
const { Secrets } = require('./secrets');
const { Engine } = require('./engine');
const { Scheduler, nextRunText } = require('./scheduler');
const { loadConfig } = require('./config');
const { registerIpc } = require('./ipc');

const ICON = path.join(__dirname, '..', 'renderer', 'assets', 'icon.png');
let win = null;
let tray = null;
let quitting = false;

// Cho phép chọn thư mục dữ liệu khác (chạy bản portable / kiểm thử): CT_DATA_DIR=/duong/dan
if (process.env.CT_DATA_DIR) app.setPath('userData', path.resolve(process.env.CT_DATA_DIR));

if (!app.requestSingleInstanceLock()) {
  app.quit();
} else {
  app.on('second-instance', () => showWindow());
  app.whenReady().then(boot);
}

function boot() {
  const dataDir = app.getPath('userData');
  fs.mkdirSync(dataDir, { recursive: true });
  const store = new Store(path.join(dataDir, 'competitor-tracker.db'));
  store.markInterruptedJobs();
  const secrets = new Secrets(store, {
    available: safeStorage.isEncryptionAvailable(),
    encrypt: (s) => safeStorage.encryptString(s),
    decrypt: (b) => safeStorage.decryptString(b)
  });
  const emit = (type, payload) => {
    if (win && !win.isDestroyed()) win.webContents.send('event', { type, ...payload });
  };
  const engine = new Engine({ store, secrets, dataDir, emit });
  const scheduler = new Scheduler({ engine, store });

  registerIpc({ ipcMain, dialog, shell, app, store, secrets, engine, getWin: () => win, applyAppSettings: () => applyAppSettings(store) });
  applyAppSettings(store);
  createWindow();
  createTray(engine, store);
  scheduler.start();

  app.on('activate', () => showWindow());
  app.on('before-quit', () => { quitting = true; scheduler.stop(); });
  app.on('will-quit', () => { try { store.close(); } catch { /* đã đóng */ } });
  app.on('window-all-closed', () => {
    if (!loadConfig(store).app.minimizeToTray) app.quit();
  });
  setInterval(() => { if (tray) tray.setToolTip(`Competitor Tracker · Lần chạy tiếp: ${nextRunText(loadConfig(store))}`); }, 60000);
}

function createWindow() {
  win = new BrowserWindow({
    width: 1360, height: 860, minWidth: 980, minHeight: 640,
    title: 'Competitor Tracker',
    icon: ICON,
    backgroundColor: '#F8FAFC',
    show: false,
    autoHideMenuBar: true,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true
    }
  });
  win.loadFile(path.join(__dirname, '..', 'renderer', 'index.html'));
  const startHidden = process.argv.includes('--hidden') || (app.getLoginItemSettings && app.getLoginItemSettings().wasOpenedAsHidden);
  win.once('ready-to-show', () => { if (!startHidden || !tray) win.show(); });
  // Link ngoài mở bằng trình duyệt, không mở trong app
  win.webContents.setWindowOpenHandler(({ url }) => {
    if (/^https?:\/\//i.test(url)) shell.openExternal(url);
    return { action: 'deny' };
  });
  win.webContents.on('will-navigate', (e, url) => { if (!url.startsWith('file://')) { e.preventDefault(); if (/^https?:\/\//i.test(url)) shell.openExternal(url); } });
  win.on('close', (e) => {
    if (!quitting && tray && process.platform !== 'linux') {
      e.preventDefault();
      win.hide();
    }
  });
}

function showWindow() {
  if (!win || win.isDestroyed()) createWindow();
  else { win.show(); win.focus(); }
}

function createTray(engine, store) {
  try {
    const img = nativeImage.createFromPath(ICON).resize({ width: 16, height: 16 });
    tray = new Tray(img);
    tray.setToolTip(`Competitor Tracker · Lần chạy tiếp: ${nextRunText(loadConfig(store))}`);
    tray.setContextMenu(Menu.buildFromTemplate([
      { label: 'Mở Competitor Tracker', click: showWindow },
      { label: 'Thu thập ngay (tất cả Page)', click: () => engine.runCrawl('manual', null, 'tray').catch((e) => store.log('error', 'tray', e.message)) },
      { type: 'separator' },
      { label: 'Thoát', click: () => { quitting = true; app.quit(); } }
    ]));
    tray.on('click', showWindow);
  } catch (e) {
    store.log('warn', 'tray', `Không tạo được biểu tượng khay hệ thống: ${e.message}`);
  }
}

function applyAppSettings(store) {
  const cfg = loadConfig(store);
  if (process.platform === 'win32' || process.platform === 'darwin') {
    app.setLoginItemSettings({ openAtLogin: !!cfg.app.launchAtLogin, openAsHidden: true, args: ['--hidden'] });
  }
}
