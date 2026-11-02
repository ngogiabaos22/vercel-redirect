'use strict';
// Truy vấn số liệu cho Dashboard. Số liệu không có (NULL) được giữ NULL để giao diện ghi "Không khả dụng".

function rangeStart(days) {
  return new Date(Date.now() - days * 86400000).toISOString();
}

function dashboard(store, { days = 7, groupId = null, pageId = null } = {}) {
  const since = rangeStart(days);
  const where = ['p.created_time >= ?'];
  const params = [since];
  if (groupId) { where.push('p.page_id IN (SELECT page_id FROM page_group_members WHERE group_id = ?)'); params.push(groupId); }
  if (pageId) { where.push('p.page_id = ?'); params.push(pageId); }
  const W = where.join(' AND ');

  const pageWhere = [];
  const pageParams = [];
  if (groupId) { pageWhere.push('id IN (SELECT page_id FROM page_group_members WHERE group_id = ?)'); pageParams.push(groupId); }
  if (pageId) { pageWhere.push('id = ?'); pageParams.push(pageId); }
  const PW = pageWhere.length ? 'WHERE ' + pageWhere.join(' AND ') : '';

  const pages = store.get(`SELECT COUNT(*) AS total, SUM(enabled) AS enabled FROM facebook_pages ${PW}`, ...pageParams);
  const posts = store.get(`SELECT COUNT(*) AS n FROM facebook_posts p WHERE ${W}`, ...params).n;
  const images = store.get(`SELECT COUNT(*) AS n, SUM(CASE WHEN a.drive_file_id IS NOT NULL THEN 1 ELSE 0 END) AS drive
    FROM content_assets a JOIN facebook_posts p ON p.id = a.post_id WHERE a.status IN ('downloaded','uploaded')
    ${groupId ? 'AND p.page_id IN (SELECT page_id FROM page_group_members WHERE group_id = ?)' : ''} ${pageId ? 'AND p.page_id = ?' : ''}`,
  ...[groupId, pageId].filter(Boolean));
  const todayStart = new Date(); todayStart.setHours(0, 0, 0, 0);
  const today = store.get(`SELECT COUNT(*) AS n FROM facebook_posts p WHERE p.first_crawled_at >= ? ${groupId ? 'AND p.page_id IN (SELECT page_id FROM page_group_members WHERE group_id = ?)' : ''} ${pageId ? 'AND p.page_id = ?' : ''}`,
    todayStart.toISOString().replace('T', ' ').slice(0, 19), ...[groupId, pageId].filter(Boolean)).n;

  // Số bài theo ngày của 5 Page đăng nhiều nhất
  const topPages = store.all(`SELECT pg.id, COALESCE(pg.name, pg.input) AS name, COUNT(*) AS n FROM facebook_posts p JOIN facebook_pages pg ON pg.id = p.page_id
    WHERE ${W} GROUP BY pg.id ORDER BY n DESC LIMIT 5`, ...params);
  const perDayRows = topPages.length
    ? store.all(`SELECT p.page_id, date(p.created_time, '+7 hours') AS d, COUNT(*) AS n FROM facebook_posts p WHERE ${W} AND p.page_id IN (${topPages.map(() => '?').join(',')})
      GROUP BY p.page_id, d`, ...params, ...topPages.map((t) => t.id))
    : [];
  const daysList = [];
  for (let i = days - 1; i >= 0; i--) {
    const d = new Date(Date.now() + 7 * 3600000 - i * 86400000);
    daysList.push(d.toISOString().slice(0, 10));
  }
  const perDay = topPages.map((t) => ({
    name: t.name,
    values: daysList.map((d) => (perDayRows.find((r) => r.page_id === t.id && r.d === d) || { n: 0 }).n)
  }));

  // Tương tác theo nhóm (NULL được bỏ qua khi cộng; nếu toàn bộ NULL → null)
  const byGroup = store.all(`SELECT g.id, g.name, g.color,
      SUM(p.reactions) AS reactions, SUM(p.comments) AS comments, SUM(p.shares) AS shares, COUNT(p.id) AS posts
    FROM page_groups g
    LEFT JOIN page_group_members m ON m.group_id = g.id
    LEFT JOIN facebook_posts p ON p.page_id = m.page_id AND p.created_time >= ? ${pageId ? 'AND p.page_id = ?' : ''}
    ${groupId ? 'WHERE g.id = ?' : ''}
    GROUP BY g.id ORDER BY g.id`, since, ...[pageId, groupId].filter(Boolean));

  // Xu hướng tương tác theo ngày đăng
  const trendRows = store.all(`SELECT date(p.created_time, '+7 hours') AS d,
      SUM(COALESCE(p.reactions,0) + COALESCE(p.comments,0) + COALESCE(p.shares,0)) AS total,
      SUM(CASE WHEN p.reactions IS NULL AND p.comments IS NULL AND p.shares IS NULL THEN 0 ELSE 1 END) AS known
    FROM facebook_posts p WHERE ${W} GROUP BY d`, ...params);
  const trend = daysList.map((d) => {
    const r = trendRows.find((x) => x.d === d);
    return { d, total: r && r.known ? r.total : null };
  });

  const topPosts = store.all(`SELECT p.id, p.message, p.permalink_url, p.created_time, p.reactions, p.comments, p.shares, COALESCE(pg.name, pg.input) AS page_name
    FROM facebook_posts p JOIN facebook_pages pg ON pg.id = p.page_id WHERE ${W}
    ORDER BY (COALESCE(p.reactions,0) + COALESCE(p.comments,0) + COALESCE(p.shares,0)) DESC LIMIT 10`, ...params);

  const types = store.all(`SELECT COALESCE(p.post_type, 'status') AS t, COUNT(*) AS n FROM facebook_posts p WHERE ${W} GROUP BY t`, ...params);

  const lastJob = store.get(`SELECT * FROM crawl_jobs WHERE job_type IN ('daily','manual','initial','retry') ORDER BY id DESC LIMIT 1`);

  return {
    days: daysList,
    kpi: { pages: pages.total || 0, enabledPages: pages.enabled || 0, posts, images: images.n || 0, imagesOnDrive: images.drive || 0, today },
    perDay, byGroup, trend, topPosts, topPages, types, lastJob
  };
}

module.exports = { dashboard };
