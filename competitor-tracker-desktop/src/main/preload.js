'use strict';
const { contextBridge, ipcRenderer } = require('electron');

const CHANNELS = new Set([
  'app:info', 'shell:openExternal', 'shell:openPath', 'dialog:pickFolder',
  'groups:list', 'groups:create', 'groups:update', 'groups:delete',
  'pages:list', 'pages:add', 'pages:importCsv', 'pages:saveCsvTemplate', 'pages:setGroups', 'pages:addToGroup', 'pages:removeFromGroup',
  'pages:setEnabled', 'pages:update', 'pages:delete', 'pages:stats',
  'crawl:run', 'crawl:retryFailed', 'crawl:cancel', 'images:run', 'images:preview', 'sheets:run',
  'jobs:list', 'jobs:items', 'jobs:running', 'jobs:errors', 'logs:list',
  'stats:dashboard', 'config:get', 'config:save', 'secrets:set', 'secrets:status',
  'google:pickServiceAccount', 'google:oauthLogin', 'google:test', 'meta:test'
]);

contextBridge.exposeInMainWorld('api', {
  async call(channel, ...args) {
    if (!CHANNELS.has(channel)) throw new Error(`Lệnh không hợp lệ: ${channel}`);
    const r = await ipcRenderer.invoke(channel, ...args);
    if (!r.ok) throw new Error(r.error);
    return r.data;
  },
  onEvent(cb) {
    const fn = (_e, payload) => cb(payload);
    ipcRenderer.on('event', fn);
    return () => ipcRenderer.removeListener('event', fn);
  }
});
