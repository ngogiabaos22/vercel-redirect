const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('ph', {
  call: (name, ...args) => ipcRenderer.invoke(name, ...args),
  onChange: fn => ipcRenderer.on('state-changed', () => fn())
});
