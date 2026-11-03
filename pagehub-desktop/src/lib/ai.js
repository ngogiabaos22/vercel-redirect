// Caption writer. Free-tier providers (Gemini, Groq, OpenRouter) plus OpenAI and Claude.
const Anthropic = require('@anthropic-ai/sdk');
const store = require('./store');
const { similarity } = require('./similarity');

const PROVIDERS = {
  none: { label: 'Không dùng AI', model: '' },
  gemini: { label: 'Google Gemini', model: 'gemini-2.5-flash' },
  groq: { label: 'Groq', model: 'llama-3.3-70b-versatile' },
  openrouter: { label: 'OpenRouter', model: 'meta-llama/llama-3.3-70b-instruct:free' },
  openai: { label: 'OpenAI', model: 'gpt-4o-mini' },
  claude: { label: 'Claude (Anthropic)', model: 'claude-opus-5-5' }
};

const OPENAI_COMPAT = {
  groq: 'https://api.groq.com/openai/v1/chat/completions',
  openrouter: 'https://openrouter.ai/api/v1/chat/completions',
  openai: 'https://api.openai.com/v1/chat/completions'
};

function config() {
  const a = store.get().settings.ai;
  const key = store.dec(a.key);
  if (a.provider === 'none') throw new Error('Chưa chọn nhà cung cấp AI (Cài đặt kết nối › AI)');
  if (!key) throw new Error('Chưa nhập API key cho AI');
  return { provider: a.provider, key, model: a.model || PROVIDERS[a.provider].model, prompt: a.prompt || store.DEFAULT_PROMPT };
}

async function complete(system, user) {
  const c = config();
  if (c.provider === 'gemini') {
    const url = `https://generativelanguage.googleapis.com/v1beta/models/${encodeURIComponent(c.model)}:generateContent?key=${encodeURIComponent(c.key)}`;
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ systemInstruction: { parts: [{ text: system }] }, contents: [{ role: 'user', parts: [{ text: user }] }] })
    });
    const j = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error('Gemini: ' + (j.error && j.error.message || `HTTP ${res.status}`));
    const parts = j.candidates && j.candidates[0] && j.candidates[0].content && j.candidates[0].content.parts || [];
    return parts.map(p => p.text || '').join('').trim();
  }
  if (OPENAI_COMPAT[c.provider]) {
    const res = await fetch(OPENAI_COMPAT[c.provider], {
      method: 'POST',
      headers: { 'content-type': 'application/json', authorization: `Bearer ${c.key}` },
      body: JSON.stringify({ model: c.model, messages: [{ role: 'system', content: system }, { role: 'user', content: user }] })
    });
    const j = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(`${PROVIDERS[c.provider].label}: ` + (j.error && (j.error.message || j.error) || `HTTP ${res.status}`));
    return String(j.choices && j.choices[0] && j.choices[0].message && j.choices[0].message.content || '').trim();
  }
  if (c.provider === 'claude') {
    const client = new Anthropic({ apiKey: c.key });
    let msg;
    try {
      msg = await client.beta.messages.create({
        model: c.model,
        max_tokens: 4000,
        betas: ['server-side-fallback-2026-07-01'],
        fallbacks: 'default',
        output_config: { effort: 'low' },
        system,
        messages: [{ role: 'user', content: user }]
      });
    } catch (e) {
      if (e instanceof Anthropic.AuthenticationError) throw new Error('Claude: API key không hợp lệ');
      if (e instanceof Anthropic.RateLimitError) throw new Error('Claude: vượt giới hạn gọi, thử lại sau');
      if (e instanceof Anthropic.APIError) throw new Error(`Claude: lỗi ${e.status} ${e.message}`);
      throw e;
    }
    if (msg.stop_reason === 'refusal') throw new Error('Claude từ chối viết nội dung này');
    return msg.content.filter(b => b.type === 'text').map(b => b.text).join('').trim();
  }
  throw new Error('Nhà cung cấp AI không hợp lệ');
}

async function rewrite({ caption, pageName }) {
  const c = config();
  const simMax = (store.get().settings.ai.simMax || 40) / 100;
  const system = c.prompt.replace(/\{page\}/g, pageName || 'Fanpage');
  let text = await complete(system, `Bài gốc (chỉ dùng ý tưởng, không chép):\n${caption}`);
  let sim = similarity(text, caption);
  if (sim >= simMax) {
    text = await complete(system, `Bài gốc (chỉ dùng ý tưởng):\n${caption}\n\nBản trước còn quá giống bài gốc. Viết lại hoàn toàn bằng câu chữ khác, cấu trúc khác.`);
    sim = similarity(text, caption);
  }
  return { text, sim: Math.round(sim * 100), provider: PROVIDERS[c.provider].label, model: c.model };
}

module.exports = { PROVIDERS, rewrite, complete };
