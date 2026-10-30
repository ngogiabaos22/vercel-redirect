// Gọi API máy chủ cục bộ
async function req(method, url, body, headers = {}) {
  const opt = { method, headers: { ...headers } };
  if (body instanceof Blob || body instanceof ArrayBuffer) opt.body = body;
  else if (body !== undefined) { opt.body = JSON.stringify(body); opt.headers['Content-Type'] = 'application/json'; }
  const r = await fetch(url, opt);
  const ct = r.headers.get('Content-Type') || '';
  const data = ct.includes('json') ? await r.json() : await r.text();
  if (!r.ok) throw new Error((data && data.error) || `Lỗi ${r.status}`);
  return data;
}
export const api = {
  get: (u) => req('GET', '/api' + u),
  post: (u, b = {}) => req('POST', '/api' + u, b),
  put: (u, b) => req('PUT', '/api' + u, b),
  del: (u) => req('DELETE', '/api' + u),
  upload: (u, file, name, extra = {}) => req('POST', '/api' + u, file, { 'X-Filename': encodeURIComponent(name || file.name || 'file'), ...extra }),
};

// Theo dõi công việc nền đến khi xong
export function watchJob(job, onUpdate) {
  return new Promise((resolve, reject) => {
    const tick = async () => {
      try {
        const j = await api.get('/jobs/' + job.id);
        onUpdate && onUpdate(j);
        if (j.state === 'done') return resolve(j);
        if (j.state === 'error') return reject(new Error(j.error || 'Lỗi không rõ'));
        setTimeout(tick, 700);
      } catch (e) { reject(e); }
    };
    tick();
  });
}

export const fileUrl = (pid, rel, v) => `/files/${pid}/${rel}${v ? `?v=${v}` : ''}`;
