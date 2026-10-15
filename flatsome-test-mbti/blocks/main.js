(function () {
  "use strict";

  function init() {
    if (window.__mbtiReady || !document.querySelector("[data-mbti]")) return;
    window.__mbtiReady = true;

    const icon = (name, classes = "") => `<svg class="icon ${classes}" aria-hidden="true"><use href="#i-${name}"></use></svg>`;

    /* ---------- Bộ icon & hình minh họa (SVG sprite) ---------- */
    document.body.insertAdjacentHTML("afterbegin", `<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="position:absolute;overflow:hidden" aria-hidden="true">
    <defs>
      <symbol id="brand-mark" viewBox="0 0 34 34">
        <path d="M2 8a6 6 0 0 1 6-6h7v13H8a6 6 0 0 1-6-6Z" fill="#8664b1"/>
        <path d="M19 2h7a6 6 0 0 1 6 6v7H19Z" fill="#b39acb"/>
        <path d="M2 19h13v13H8a6 6 0 0 1-6-6Z" fill="#b39acb"/>
        <path d="M19 19h13v7a6 6 0 0 1-6 6h-7Z" fill="#8664b1"/>
      </symbol>
      <symbol id="i-arrow-right" viewBox="0 0 24 24"><path d="M4 12h15m-6-6 6 6-6 6"/></symbol>
      <symbol id="i-arrow-up" viewBox="0 0 24 24"><path d="M6 18 18 6M6 6h12v12"/></symbol>
      <symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3 2"/></symbol>
      <symbol id="i-list" viewBox="0 0 24 24"><rect x="5" y="3.5" width="14" height="17" rx="2"/><path d="M9 8h6m-6 4h6m-6 4h4"/></symbol>
      <symbol id="i-shield" viewBox="0 0 24 24"><path d="m12 3 8 3v5c0 5-4 8-8 10-4-2-8-5-8-10V6l8-3Z"/><path d="m8.5 11.5 2.5 2.5 4.5-5"/></symbol>
      <symbol id="i-sparkles" viewBox="0 0 24 24"><path d="m12 3 2.4 6.6L21 12l-6.6 2.4L12 21l-2.4-6.6L3 12l6.6-2.4L12 3Z"/><path d="M20 2v4m-2-2h4"/></symbol>
      <symbol id="i-fingerprint" viewBox="0 0 24 24"><path d="M5 11a7 7 0 0 1 14 0m-11 0a4 4 0 0 1 8 0v3c0 3 1 5 2 6M11 20c1-3 1-6 1-9M5 14c0 3-1 4-1 4m4-4c0 3-.5 5-1.5 7M19 14v1.5"/></symbol>
      <symbol id="i-chat" viewBox="0 0 24 24"><path d="M20 11a8 8 0 0 1-8 8H5l-3 3V11a9 9 0 0 1 18 0Z"/><path d="M7 11h8m-8 4h5"/></symbol>
      <symbol id="i-compass" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m16 8-2.5 5.5L8 16l2.5-5.5L16 8Z"/></symbol>
      <symbol id="i-bolt" viewBox="0 0 24 24"><path d="m13 2-8 12h6l-1 8 9-13h-7l1-7Z"/></symbol>
      <symbol id="i-eye" viewBox="0 0 24 24"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></symbol>
      <symbol id="i-heart" viewBox="0 0 24 24"><path d="M20 5c-3-3-7-1-8 2-1-3-5-5-8-2-5 5 3 12 8 16 5-4 13-11 8-16Z"/></symbol>
      <symbol id="i-layout" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M3 9h18M9 9v12"/></symbol>
      <symbol id="i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v.2"/></symbol>
      <symbol id="i-close" viewBox="0 0 24 24"><path d="m6 6 12 12M6 18 18 6"/></symbol>
      <symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></symbol>
      <symbol id="i-copy" viewBox="0 0 24 24"><rect x="8" y="8" width="12" height="13" rx="2"/><path d="M15 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/></symbol>
      <symbol id="i-reset" viewBox="0 0 24 24"><path d="M4 10a8 8 0 1 1 .5 6M4 4v6h6"/></symbol>
      <symbol id="i-lock" viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/></symbol>
      <symbol id="person-0" viewBox="0 0 240 220">
        <g stroke-linecap="round" stroke-linejoin="round">
          <path d="m41 62 13-7 7 13-13 7Z" fill="#fff" opacity=".65"/>
          <path d="m190 32 3 9 9 3-9 3-3 9-3-9-9-3 9-3Z" fill="#fff" opacity=".85"/>
          <ellipse cx="120" cy="210" rx="82" ry="8" fill="#42334b" opacity=".07"/>
          <path d="M79 89V59c0-39 78-41 82-1l-5 71H84Z" fill="var(--hair,#3e2d46)"/>
          <path d="M103 105h31v38h-31z" fill="var(--skin,#eac09f)"/>
          <path d="M104 112c11 7 24 7 30-1v14c-12 10-27 3-30-3Z" fill="#633724" opacity=".1"/>
          <path d="M49 220 66 151q9-22 36-23l16 12 17-12q29 5 39 27l19 65Z" fill="var(--shirt,#9677bb)"/>
          <path d="m102 127 16 13 17-13-9 52h-17Z" fill="#f8f0df"/>
          <path d="m96 127 22 13-18 25-16-20Zm42 1-20 12 12 23 20-19Z" fill="var(--shirt-dark,#70518f)"/>
          <ellipse cx="88" cy="87" rx="8" ry="12" fill="var(--skin,#eac09f)"/>
          <rect x="88" y="45" width="66" height="76" rx="30" fill="var(--skin,#eac09f)"/>
          <path d="M83 79V57q1-27 30-27 43-6 48 30l-8 26-10-25q-23 10-48 3l-3 26Z" fill="var(--hair,#3e2d46)"/>
          <path d="M92 50q25-19 55 0" fill="none" stroke="#fff" stroke-opacity=".1" stroke-width="3"/>
          <circle cx="108" cy="85" r="12" fill="#ffffff15" stroke="#4b3c4b" stroke-width="2.5"/>
          <circle cx="137" cy="85" r="12" fill="#ffffff15" stroke="#4b3c4b" stroke-width="2.5"/>
          <path d="M120 85h5m-29-3-8-3" stroke="#4b3c4b" stroke-width="2.5"/>
          <circle cx="109" cy="85" r="2.4" fill="#3c3035"/>
          <circle cx="136" cy="85" r="2.4" fill="#3c3035"/>
          <path d="m124 91-2 7h4m-10 8q8 5 14-1" fill="none" stroke="#9a6250" stroke-width="1.8"/>
          <path d="M171 154q21 20 24 41l-26 12-16-38" fill="var(--shirt-dark,#70518f)"/>
          <g transform="rotate(-13 163 184)">
            <path d="M126 158h36q9 0 9 6 2-6 10-6h30v53h-32q-6 0-8 4-4-4-12-4h-33Z" fill="#e9d4a4"/>
            <path d="M132 158h29q10 0 10 6v46q-5-6-16-6h-23Zm39 6q3-6 12-6h23v46h-26q-5 0-9 6Z" fill="#fff7df"/>
            <path d="M139 171h20m-20 8h20m-20 8h16m23-16h20m-20 8h20m-20 8h13" fill="none" stroke="#c7b48c" stroke-width="2"/>
          </g>
          <path d="M66 159q-12 32 12 43 20 12 53-5l-10-18-34 6-6-29" fill="var(--shirt,#9677bb)"/>
          <path d="m117 181 17-8q9-4 12 4 2 4-3 8l-15 12Z" fill="var(--skin,#eac09f)"/>
          <path d="M76 187q9 9 32 1" fill="none" stroke="var(--shirt-dark,#70518f)" stroke-width="2" opacity=".65"/>
          <ellipse cx="206" cy="179" rx="7" ry="12" transform="rotate(18 206 179)" fill="var(--skin,#eac09f)"/>
        </g>
      </symbol>
      <symbol id="person-1" viewBox="0 0 240 220">
        <g stroke-linecap="round" stroke-linejoin="round">
          <circle cx="49" cy="65" r="7" fill="#fff" opacity=".7"/>
          <path d="M177 32q13 1 15 14-14 0-15-14Z" fill="#fff" opacity=".8"/>
          <ellipse cx="120" cy="211" rx="81" ry="8" fill="#304c31" opacity=".07"/>
          <g fill="var(--hair,#514337)">
            <circle cx="120" cy="70" r="40"/><circle cx="87" cy="88" r="22"/><circle cx="150" cy="89" r="24"/><circle cx="119" cy="109" r="33"/>
          </g>
          <path d="M106 106h28v36h-28z" fill="var(--skin,#e8ba98)"/>
          <path d="M54 220 68 156q7-24 37-28l14 10 16-10q30 4 39 28l19 64Z" fill="var(--shirt,#7e9b73)"/>
          <path d="m102 129 17 10 17-10-3 20h-28Z" fill="#f1e8d6"/>
          <path d="M119 149v69" stroke="var(--shirt-dark,#53794f)" stroke-width="2" opacity=".55"/>
          <circle cx="126" cy="166" r="2" fill="#e7e7c9"/>
          <circle cx="126" cy="183" r="2" fill="#e7e7c9"/>
          <rect x="90" y="48" width="62" height="77" rx="29" fill="var(--skin,#e8ba98)"/>
          <ellipse cx="91" cy="90" rx="7" ry="10" fill="var(--skin,#e8ba98)"/>
          <ellipse cx="151" cy="90" rx="7" ry="10" fill="var(--skin,#e8ba98)"/>
          <g fill="var(--hair,#514337)">
            <circle cx="93" cy="55" r="17"/><circle cx="112" cy="42" r="18"/><circle cx="135" cy="43" r="16"/><circle cx="151" cy="59" r="14"/>
          </g>
          <path d="M104 86q5-5 10 0m15 0q5-5 10 0" fill="none" stroke="#493b32" stroke-width="2"/>
          <path d="m122 88-3 9h5m-13 10q11 10 21-1" fill="none" stroke="#9f6451" stroke-width="1.8"/>
          <circle cx="104" cy="98" r="5" fill="#d8907f" opacity=".35"/>
          <circle cx="139" cy="98" r="5" fill="#d8907f" opacity=".35"/>
          <circle cx="91" cy="102" r="4" fill="#dbbe77"/>
          <circle cx="151" cy="102" r="4" fill="#dbbe77"/>
          <path d="M165 154q14 23 31-11" fill="none" stroke="var(--shirt-dark,#53794f)" stroke-width="26"/>
          <path d="M196 142q8-10 2-17-5-6-11 2l-6 12Z" fill="var(--skin,#e8ba98)"/>
          <path d="M192 135q-7-31 0-67" fill="none" stroke="#4f714d" stroke-width="2.5"/>
          <path d="M191 109q-27-2-24-21 19-1 24 21Zm0-23q0-24 19-29 6 20-19 29Zm-2 38q17-3 23-20-20-3-23 20Z" fill="#8ea76b"/>
          <path d="M73 157q-16 33 12 43" fill="none" stroke="var(--shirt,#7e9b73)" stroke-width="27"/>
          <rect x="81" y="174" width="61" height="42" rx="8" fill="#e9dbb9"/>
          <path d="M94 175v-8q18-21 34 0v8" fill="none" stroke="#cfba8d" stroke-width="4"/>
          <path d="m110 184 2 7 7 2-7 2-2 7-2-7-7-2 7-2Z" fill="#b59b6b"/>
          <path d="m82 187 13 1q9 1 7 10-1 6-9 6l-9-3Z" fill="var(--skin,#e8ba98)"/>
        </g>
      </symbol>
      <symbol id="person-2" viewBox="0 0 240 220">
        <g stroke-linecap="round" stroke-linejoin="round">
          <path d="m45 56 7 4 7-4v14l-7 5-7-5Z" fill="#fff" opacity=".7"/>
          <circle cx="190" cy="58" r="11" fill="none" stroke="#fff" stroke-width="4" opacity=".7"/>
          <ellipse cx="119" cy="211" rx="83" ry="8" fill="#263849" opacity=".07"/>
          <path d="M104 105h30v39h-30Z" fill="var(--skin,#ca9574)"/>
          <path d="M51 220 65 156q11-29 39-30l16 12 15-12q29 4 40 30l18 64Z" fill="var(--shirt,#7ca3bd)"/>
          <path d="m103 126 17 12 15-12v88h-32Z" fill="#edf0e7"/>
          <path d="m103 126 17 12-16 20-14-24Zm32 0-15 12 16 20 14-24" fill="#f9f6e9"/>
          <path d="M117 146h6l5 52-8 12-8-12Z" fill="var(--shirt-dark,#527b98)"/>
          <ellipse cx="87" cy="87" rx="8" ry="11" fill="var(--skin,#ca9574)"/>
          <rect x="88" y="45" width="66" height="79" rx="28" fill="var(--skin,#ca9574)"/>
          <path d="M84 85 80 64q-1-29 29-31l36-5q22 0 18 24l-10 22-8-15q-27 4-48 7l-5 23Z" fill="var(--hair,#344351)"/>
          <path d="M92 54q25-16 57-10" fill="none" stroke="#ffffff18" stroke-width="3"/>
          <rect x="98" y="77" width="23" height="18" rx="7" fill="#ffffff0e" stroke="#3b4952" stroke-width="2.3"/>
          <rect x="128" y="77" width="23" height="18" rx="7" fill="#ffffff0e" stroke="#3b4952" stroke-width="2.3"/>
          <path d="M121 84h7m-30-2-9-3" stroke="#3b4952" stroke-width="2.3"/>
          <circle cx="110" cy="85" r="2.3" fill="#3d3230"/>
          <circle cx="138" cy="85" r="2.3" fill="#3d3230"/>
          <path d="m125 92-3 8h4m-11 8q9 5 16-1" fill="none" stroke="#875440" stroke-width="1.8"/>
          <path d="M172 151q21 19 23 49l-28 10-12-43" fill="var(--shirt-dark,#527b98)"/>
          <g transform="rotate(9 168 183)">
            <rect x="137" y="150" width="63" height="66" rx="5" fill="#d4c6a8"/>
            <rect x="142" y="155" width="53" height="56" rx="2" fill="#faf3df"/>
            <rect x="157" y="146" width="23" height="11" rx="3" fill="var(--shirt-dark,#527b98)"/>
            <path d="m150 169 3 3 5-6m-8 18 3 3 5-6m-8 18 3 3 5-6" fill="none" stroke="#8a9c71" stroke-width="2"/>
            <path d="M165 170h21m-21 15h21m-21 15h16" stroke="#c7bea7" stroke-width="2"/>
          </g>
          <path d="M69 158q-18 34 10 46 28 11 57-13l-10-17-35 11-6-25" fill="var(--shirt,#7ca3bd)"/>
          <path d="m124 177 12-8q11-6 16 3 3 6-3 10l-15 12Z" fill="var(--skin,#ca9574)"/>
          <path d="M73 188q18 9 36 0" fill="none" stroke="var(--shirt-dark,#527b98)" stroke-width="2" opacity=".7"/>
          <ellipse cx="197" cy="190" rx="7" ry="11" fill="var(--skin,#ca9574)"/>
        </g>
      </symbol>
      <symbol id="person-3" viewBox="0 0 240 220">
        <g stroke-linecap="round" stroke-linejoin="round">
          <path d="m188 45 3 9 9 3-9 3-3 9-3-9-9-3 9-3Z" fill="#fff" opacity=".85"/>
          <path d="m39 161 6 6-6 6-6-6Z" fill="#fff" opacity=".75"/>
          <ellipse cx="120" cy="211" rx="82" ry="8" fill="#735525" opacity=".07"/>
          <path d="M103 106h32v36h-32Z" fill="var(--skin,#dda87d)"/>
          <path d="M66 166q-14 16-10 49h129q5-40-15-54" fill="var(--shirt-dark,#b5813c)"/>
          <path d="M55 220 67 154q9-24 37-26l16 11 15-11q29 3 39 25l20 67Z" fill="var(--shirt,#e1ad57)"/>
          <path d="m103 128 17 11 16-11-8 25h-17Z" fill="#f6e6c6"/>
          <path d="M100 132 89 216m47-84 19 84" stroke="var(--shirt-dark,#b5813c)" stroke-width="7" opacity=".8"/>
          <rect x="89" y="46" width="65" height="80" rx="29" fill="var(--skin,#dda87d)"/>
          <ellipse cx="88" cy="91" rx="8" ry="11" fill="var(--skin,#dda87d)"/>
          <path d="m83 70 1 19 9 8 3-30Zm62-6 4 31 9-12-1-19Z" fill="var(--hair,#53402e)"/>
          <path d="M78 69q-1-43 41-45 39 0 45 44Z" fill="var(--shirt,#e1ad57)"/>
          <path d="M119 28v29m18-26 5 27m-42-25-3 25" stroke="var(--shirt-dark,#b5813c)" stroke-opacity=".55" stroke-width="2"/>
          <rect x="78" y="62" width="86" height="18" rx="7" fill="var(--shirt-dark,#b5813c)"/>
          <rect x="125" y="66" width="17" height="10" rx="2" fill="#eddbb6"/>
          <path d="M105 88h9m17 0h8" stroke="#5b4230" stroke-width="2"/>
          <circle cx="110" cy="94" r="2.2" fill="#4b392c"/>
          <circle cx="135" cy="94" r="2.2" fill="#4b392c"/>
          <path d="m124 96-2 7h4" fill="none" stroke="#a36d49" stroke-width="1.7"/>
          <path d="M111 110q12 7 23-1-3 14-13 12-8-1-10-11Z" fill="#fff4df"/>
          <path d="M73 158q-26 5-29-31" fill="none" stroke="var(--shirt,#e1ad57)" stroke-width="27"/>
          <path d="m35 132-7-21q-1-5 3-6 3-1 5 3l3 7-5-20q0-5 4-5 4 0 4 5l4 16-1-22q0-5 4-4 4 0 4 5l1 22 4-14q1-5 5-3 3 1 2 6l-4 23q-2 11-13 13Z" fill="var(--skin,#dda87d)"/>
          <path d="m153 133-64 84" stroke="#687e60" stroke-width="9"/>
          <rect x="95" y="176" width="62" height="42" rx="10" fill="#7e926c"/>
          <path d="M100 184h51" stroke="#566e48" stroke-width="2"/>
          <circle cx="129" cy="197" r="6" fill="#e8ddba"/>
          <path d="M170 157q20 32-8 46" fill="none" stroke="var(--shirt,#e1ad57)" stroke-width="27"/>
          <path d="m165 190-13-3q-9-1-10 6-2 8 8 10l12 2Z" fill="var(--skin,#dda87d)"/>
        </g>
      </symbol>
    </defs>
  </svg>`);

    /* ---------- Dữ liệu ---------- */
    const { groups, types, dimensions, ui } = window.MBTI_DATA;


    Object.entries(groups).forEach(([key, group]) => {
      group.types.forEach((code, index) => {
        types[code].group = key;
        types[code].avatar = index;
      });
    });

    const portraitPalettes = [
      { skin: "#eac09f", hair: "#3e2d46" },
      { skin: "#e8ba98", hair: "#514337" },
      { skin: "#ca9574", hair: "#344351" },
      { skin: "#dda87d", hair: "#53402e" }
    ];

    function portrait(code, className = "character") {
      const type = types[code] || types.INTJ;
      const group = groups[type.group];
      const p = portraitPalettes[type.avatar];
      return `<svg class="${className}" viewBox="0 0 240 220" style="--shirt:${group.shirts[type.avatar]};--shirt-dark:${group.dark};--skin:${p.skin};--hair:${p.hair}" aria-hidden="true"><use href="#person-${type.avatar}"></use></svg>`;
    }


    /* ---------- Gắn các khối giao diện vào placeholder trong UX Builder ---------- */
    /* Link cho các nút trong khối do script dựng: thêm data-href="..." vào placeholder trong UX Builder,
       ví dụ <span data-mbti="cta" data-href="/lam-bai-test-mbti/"></span>. Không có data-href thì dùng "#". */
    const escapeAttr = value => String(value).replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;");
    const hrefOf = name => {
      const holder = document.querySelector(`[data-mbti="${name}"]`);
      return escapeAttr((holder && holder.getAttribute("data-href")) || "#");
    };

    function fill(name, html) {
      const holder = document.querySelector(`[data-mbti="${name}"]`);
      if (!holder) return null;
      const box = document.createElement("div");
      box.className = `mbti mbti-block mbti-block-${name}`;
      box.innerHTML = html;
      /* WordPress (wpautop) có thể bọc placeholder thành <p><span></span><br></p>: thay luôn cả thẻ <p> đó */
      const wrapper = holder.parentElement;
      if (wrapper && wrapper.tagName === "P" && !wrapper.textContent.trim() && wrapper.querySelectorAll("*:not(br)").length === 1) wrapper.replaceWith(box);
      else holder.replaceWith(box);
      return box;
    }

    fill("hero-meta", `<div class="hero-meta" aria-label="Thông tin bài test">
        ${ui.heroMeta.map(([name, text]) => `<span>${icon(name)} ${text}</span>`).join("")}
      </div>`);

    const heroCards = [
      { code: "INTJ", icon: "sparkles", avatar: 0 },
      { code: "ENFP", icon: "heart", avatar: 1 },
      { code: "ISFJ", icon: "shield", avatar: 2 },
      { code: "ESTP", icon: "compass", avatar: 3 }
    ];
    fill("hero-art", `<div class="hero-art" aria-label="Bốn sắc màu trong thế giới 16 nhóm tính cách">
        <div class="art-halo" aria-hidden="true"></div>
        <div class="art-orbit" aria-hidden="true"></div>
        <svg class="art-doodle" viewBox="0 0 90 90" fill="none" aria-hidden="true"><path d="M16 74C2 40 24 13 59 20" stroke="currentColor" stroke-width="1.4" stroke-dasharray="3 4"/><path d="m51 9 11 12-15 5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <div class="floating-note"><span class="note-dot"></span>${ui.floatingNote}</div>
        ${heroCards.map(card => `<button class="trait-card" data-type="${card.code}" aria-label="Khám phá ${card.code} — ${types[card.code].name}">
          <span class="trait-top"><span class="trait-code">${card.code}</span>${icon(card.icon)}</span>
          <svg class="character" viewBox="0 0 240 220" aria-hidden="true"><use href="#person-${card.avatar}"></use></svg>
          <span class="trait-name">${types[card.code].name} ${icon("arrow-up")}</span>
        </button>`).join("")}
        <div class="art-center" aria-hidden="true"><svg viewBox="0 0 34 34"><use href="#brand-mark"></use></svg></div>
        ${icon("sparkles", "art-sparkle")}
        <p class="art-caption">${ui.caption[0]} <span>${ui.caption[1]}</span></p>
      </div>`);

    fill("benefits", `<div class="benefits" aria-label="Những điều bạn có thể khám phá">
        ${ui.benefits.map(([name, title, text]) => `<div class="benefit"><span class="benefit-icon">${icon(name)}</span><div><h3>${title}</h3><p>${text}</p></div></div>`).join("")}
      </div>`);

    const groupKeys = Object.keys(groups);
    fill("personalities", `<div class="group-tabs" role="tablist" aria-label="Bốn nhóm tính cách MBTI">
        ${groupKeys.map((key, index) => `<button class="group-tab" id="mbti-tab-${key}" role="tab" aria-selected="${index === 0}" aria-controls="mbti-personality-grid" ${index ? 'tabindex="-1"' : ""} data-group="${key}" style="--tab-color:${groups[key].tab}"><span class="tab-dot"></span>${groups[key].label} <span class="tab-code">${groups[key].code}</span></button>`).join("")}
      </div>
      <p class="group-description" id="mbti-group-description" aria-live="polite"></p>
      <div class="personality-grid" id="mbti-personality-grid" role="tabpanel"></div>
      <div class="personality-bottom">${ui.personalitiesCta[0]} <a class="text-link" href="${hrefOf("personalities")}">${ui.personalitiesCta[1]} ${icon("arrow-right")}</a></div>`);

    fill("dimensions", `<div class="dimension-grid">
        ${dimensions.map((d, index) => `<article class="dimension-card" data-dimension="${index}" style="--dimension-color:${d.color};--dimension-soft:${d.soft}">
          <div class="dimension-top"><span class="dimension-number">${d.number}</span><span class="dimension-icon">${icon(d.icon)}</span></div>
          <h3>${d.question.replace(/\n/g, "<br>")}</h3>
          <div class="dimension-choices" aria-label="${d.aria}">
            <button class="dimension-choice" data-pole="${d.first}" aria-pressed="true"><b>${d.first}</b> ${d.labels[d.first]}</button>
            <button class="dimension-choice" data-pole="${d.second}" aria-pressed="false"><b>${d.second}</b> ${d.labels[d.second]}</button>
          </div>
          <p class="dimension-description" aria-live="polite">${d.descriptions[d.first]}</p>
        </article>`).join("")}
      </div>
      <p class="dimension-note">${icon("info")} ${ui.dimensionsNote}</p>`);

    fill("before", `<div class="before-panel">
        <div class="before-heading">
          <h2>${ui.before.title}</h2>
          <p>${ui.before.text}</p>
        </div>
        <div class="step-grid">
          ${ui.before.steps.map(([title, text], index) => `<div class="step"><span class="step-number">${String(index + 1).padStart(2, "0")}</span><div><h3>${title}</h3><p>${text}</p></div></div>`).join("")}
        </div>
      </div>`);

    fill("cta", `<div class="cta-panel">
        <svg class="cta-decoration" viewBox="0 0 300 300" fill="none" aria-hidden="true"><circle cx="150" cy="150" r="117" stroke="currentColor"/><circle cx="150" cy="150" r="89" stroke="currentColor"/><path d="M150 6v288M6 150h288M48 48l204 204M48 252 252 48" stroke="currentColor" stroke-width="38"/></svg>
        <div class="cta-copy">
          <span class="eyebrow">${ui.cta.eyebrow}</span>
          <h2>${ui.cta.line1}<br><span>${ui.cta.line2}</span></h2>
        </div>
        <div class="cta-action">
          <a class="btn btn-lime" href="${hrefOf("cta")}">${ui.cta.button} ${icon("arrow-right")}</a>
          <p>${ui.cta.note}</p>
        </div>
      </div>`);

    /* Popup luôn được gắn vào cuối <body> */
    const modalRoot = document.createElement("div");
    modalRoot.className = "mbti mbti-modals";
    modalRoot.innerHTML = `      <dialog class="personality-dialog" id="mbti-personality-dialog" aria-labelledby="mbti-detail-title">
        <div id="mbti-personality-content"></div>
      </dialog>`;
    document.body.appendChild(modalRoot);

    /* ---------- Tab 16 nhóm tính cách ---------- */
    const grid = document.getElementById("mbti-personality-grid");
    const groupTabs = [...document.querySelectorAll(".mbti .group-tab")];

    function renderGroup(key) {
      const group = groups[key];
      if (!group || !grid) return;
      groupTabs.forEach(tab => {
        const selected = tab.dataset.group === key;
        tab.setAttribute("aria-selected", String(selected));
        tab.tabIndex = selected ? 0 : -1;
      });
      document.getElementById("mbti-group-description").textContent = group.note;
      grid.setAttribute("aria-labelledby", `mbti-tab-${key}`);
      grid.innerHTML = group.types.map((code, index) => {
        const type = types[code];
        return `<button class="personality-card" data-type="${code}" style="--card-accent:${group.accent};--card-soft:${group.soft};animation-delay:${index * 45}ms" aria-label="Tìm hiểu ${code} — ${type.name}">
          <div class="personality-visual">${icon("sparkles", "visual-sparkle")}${portrait(code)}</div>
          <div class="personality-info">
            <div class="type-code-row"><span class="type-code">${code}</span><span class="card-arrow">${icon("arrow-up")}</span></div>
            <h3 class="type-name">${type.name}</h3>
            <p class="type-short">${type.short}</p>
          </div>
        </button>`;
      }).join("");
    }

    groupTabs.forEach((tab, index) => {
      tab.addEventListener("click", () => renderGroup(tab.dataset.group));
      tab.addEventListener("keydown", event => {
        let target;
        if (event.key === "ArrowRight") target = (index + 1) % groupTabs.length;
        if (event.key === "ArrowLeft") target = (index - 1 + groupTabs.length) % groupTabs.length;
        if (event.key === "Home") target = 0;
        if (event.key === "End") target = groupTabs.length - 1;
        if (target !== undefined) {
          event.preventDefault();
          groupTabs[target].focus();
          renderGroup(groupTabs[target].dataset.group);
          groupTabs[target].scrollIntoView({ block: "nearest", inline: "nearest" });
        }
      });
    });

    /* ---------- 4 khía cạnh ---------- */
    document.querySelectorAll(".mbti .dimension-choice").forEach(choiceButton => {
      choiceButton.addEventListener("click", () => {
        const card = choiceButton.closest(".dimension-card");
        const dimension = dimensions[Number(card.dataset.dimension)];
        card.querySelectorAll(".dimension-choice").forEach(choice => choice.setAttribute("aria-pressed", String(choice === choiceButton)));
        card.querySelector(".dimension-description").textContent = dimension.descriptions[choiceButton.dataset.pole];
      });
    });

    /* ---------- Popup ---------- */
    const detailDialog = document.getElementById("mbti-personality-dialog");
    const dialogs = [detailDialog];

    function syncScrollLock() {
      document.documentElement.classList.toggle("mbti-lock", dialogs.some(dialog => dialog.open));
    }

    function openPersonality(code) {
      const type = types[code];
      if (!type) return;
      const group = groups[type.group];
      document.getElementById("mbti-personality-content").innerHTML = `
        <div class="detail-hero" style="--detail-soft:${group.soft};--detail-accent:${group.accent}">
          <button class="icon-button" data-close="mbti-personality-dialog" aria-label="Đóng thông tin tính cách">${icon("close")}</button>
          <div class="detail-hero-copy">
            <p class="detail-kicker">${group.label} · ${group.code}</p>
            <p class="detail-code">${code}</p>
            <h2 id="mbti-detail-title">${type.name}</h2>
          </div>
          ${portrait(code)}
        </div>
        <div class="detail-content">
          <p class="detail-description">${type.description}</p>
          <h3>${ui.popup.strengths}</h3>
          <div class="tag-list">${type.strengths.map(value => `<span class="tag">${value}</span>`).join("")}</div>
          <h3>${ui.popup.growth}</h3>
          <p class="detail-growth">${type.growth}</p>
          <h3>${ui.popup.careers}</h3>
          <div class="tag-list">${type.careers.map(value => `<span class="tag">${value}</span>`).join("")}</div>
          <p class="detail-note">${ui.popup.note}</p>
        </div>`;
      if (!detailDialog.open) detailDialog.showModal();
      detailDialog.scrollTop = 0;
      syncScrollLock();
    }

    dialogs.forEach(dialog => {
      dialog.addEventListener("close", syncScrollLock);
      dialog.addEventListener("click", event => {
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (bounds.left > event.clientX || event.clientX > bounds.right || bounds.top > event.clientY || event.clientY > bounds.bottom) dialog.close();
      });
    });

    document.addEventListener("click", event => {
      const typeTrigger = event.target.closest(".mbti [data-type]");
      const close = event.target.closest(".mbti [data-close]");
      if (typeTrigger) {
        openPersonality(typeTrigger.dataset.type);
      } else if (close) {
        const dialog = document.getElementById(close.dataset.close);
        if (dialog && dialog.open) dialog.close();
      }
    });

    renderGroup(groupKeys[0]);
  }

  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", init);
  else init();
})();
