'use strict';
// Hẹn giờ hằng ngày theo giờ Việt Nam (Asia/Ho_Chi_Minh). Kiểm tra mỗi 30 giây.
// Nếu máy tắt / app đóng đúng giờ chạy và bật "chạy bù", lần mở app sau sẽ chạy bù trong ngày.

function vnNow(now = new Date()) {
  const parts = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Ho_Chi_Minh', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hour12: false })
    .formatToParts(now).reduce((o, p) => { o[p.type] = p.value; return o; }, {});
  const hh = parts.hour === '24' ? '00' : parts.hour;
  return { date: `${parts.year}-${parts.month}-${parts.day}`, time: `${hh}:${parts.minute}` };
}

/**
 * Trả về các tác vụ đến hạn. state = { crawl: 'YYYY-MM-DD', images: ..., sheets: ... } (ngày đã chạy gần nhất).
 */
function dueTasks(cfg, state, now = new Date()) {
  const { date, time } = vnNow(now);
  const due = [];
  const check = (key, enabled, at) => {
    if (!enabled || state[key] === date) return;
    if (time === at || (cfg.crawl.catchUp && time > at)) due.push(key);
  };
  check('crawl', cfg.crawl.enabled, cfg.crawl.time);
  check('images', cfg.images.enabled || (cfg.sheets.enabled && cfg.sheets.schedule === 'after_images'), cfg.images.time);
  check('sheets', cfg.sheets.enabled && cfg.sheets.schedule === 'time', cfg.sheets.time);
  return { due, date };
}

function nextRunText(cfg, now = new Date()) {
  const { time } = vnNow(now);
  if (!cfg.crawl.enabled) return 'Tự động thu thập đang tắt';
  return `${cfg.crawl.time} ${time < cfg.crawl.time ? 'hôm nay' : 'ngày mai'} · Asia/Ho_Chi_Minh`;
}

class Scheduler {
  constructor({ engine, store, intervalMs = 30000 }) {
    this.engine = engine; this.store = store; this.intervalMs = intervalMs; this.timer = null; this.running = false;
  }
  start() { if (!this.timer) { this.timer = setInterval(() => this.tick(), this.intervalMs); setTimeout(() => this.tick(), 5000); } }
  stop() { clearInterval(this.timer); this.timer = null; }
  async tick() {
    if (this.running) return;
    this.running = true;
    try {
      const cfg = this.engine.config();
      let state = this.store.getSetting('scheduleState', null);
      if (!state) {
        // lần đầu mở app: không chạy bù ngay, chờ đến giờ chạy kế tiếp
        state = { crawl: vnNow().date, images: vnNow().date, sheets: vnNow().date };
        this.store.setSetting('scheduleState', state);
      }
      const { due, date } = dueTasks(cfg, state);
      for (const task of due) {
        state[task] = date;
        this.store.setSetting('scheduleState', state); // đánh dấu trước để không chạy lặp
        try {
          if (task === 'crawl') {
            if (!this.engine.secrets.get('metaToken')) { this.store.log('warn', 'scheduler', 'Bỏ qua lịch thu thập: chưa nhập Access Token Meta.'); continue; }
            await this.engine.runDailyCrawl('schedule');
          }
          if (task === 'images') await this.engine.runDailyImages('schedule');
          if (task === 'sheets') await this.engine.runSheets('schedule');
        } catch (e) {
          this.store.log('error', 'scheduler', `${task}: ${e.message}`);
        }
      }
    } finally {
      this.running = false;
    }
  }
}

module.exports = { Scheduler, dueTasks, vnNow, nextRunText };
