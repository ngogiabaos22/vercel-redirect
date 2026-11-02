'use strict';
// Điều kiện tải ảnh theo tương tác. Ô để trống = 0 (không giới hạn).

const toMin = (v) => {
  if (v === null || v === undefined || v === '') return 0;
  const n = parseInt(v, 10);
  return Number.isFinite(n) && n > 0 ? n : 0;
};

/** Ngưỡng áp dụng cho một nhóm (null = không tải ảnh, 'all' = tải tất cả). */
function thresholdsFor(group, globalCfg) {
  const mode = (group && group.image_mode) || 'default';
  if (mode === 'none') return null;
  if (mode === 'all') return { reactions: 0, comments: 0, shares: 0 };
  const src = mode === 'custom'
    ? { reactions: group.min_reactions, comments: group.min_comments, shares: group.min_shares }
    : { reactions: globalCfg.minReactions, comments: globalCfg.minComments, shares: globalCfg.minShares };
  return { reactions: toMin(src.reactions), comments: toMin(src.comments), shares: toMin(src.shares) };
}

/**
 * @param {{reactions:number|null, comments:number|null, shares:number|null}} post
 * @param {{reactions:number, comments:number, shares:number}} th
 * @param {{mode:'and'|'or', naPolicy:'ignore'|'fail'}} cfg
 */
function passes(post, th, cfg) {
  let conds = ['reactions', 'comments', 'shares'].filter((k) => th[k] > 0);
  if (!conds.length) return true;
  if (cfg.naPolicy !== 'fail') {
    conds = conds.filter((k) => post[k] !== null && post[k] !== undefined);
    if (!conds.length) return true; // mọi chỉ số bị thiếu đều được bỏ qua
  }
  const ok = (k) => post[k] !== null && post[k] !== undefined && post[k] >= th[k];
  return cfg.mode === 'or' ? conds.some(ok) : conds.every(ok);
}

/** Page thuộc nhiều nhóm: tải nếu đạt điều kiện của ít nhất một nhóm. Page chưa có nhóm: dùng điều kiện mặc định. */
function shouldDownload(post, groups, globalCfg) {
  const list = groups && groups.length ? groups : [{ image_mode: 'default' }];
  return list.some((g) => {
    const th = thresholdsFor(g, globalCfg);
    return th !== null && passes(post, th, globalCfg);
  });
}

function describe(globalCfg) {
  const th = thresholdsFor({ image_mode: 'default' }, globalCfg);
  const parts = [];
  if (th.reactions) parts.push(`reaction ≥ ${th.reactions.toLocaleString('vi-VN')}`);
  if (th.comments) parts.push(`comment ≥ ${th.comments.toLocaleString('vi-VN')}`);
  if (th.shares) parts.push(`share ≥ ${th.shares.toLocaleString('vi-VN')}`);
  if (!parts.length) return 'Không đặt ngưỡng: tải ảnh của tất cả bài viết.';
  return `Chỉ tải ảnh của bài có ${parts.join(globalCfg.mode === 'or' ? ' HOẶC ' : ' VÀ ')}.`;
}

module.exports = { thresholdsFor, passes, shouldDownload, describe, toMin };
