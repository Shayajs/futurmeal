const sharp = require('sharp');
const fs = require('fs');
const path = require('path');

const OUT = '/home/shaya/prog/futurmeal/docs/charte/declinaisons-v3';
const GREEN = '#00FF88';
const BG = '#0B0F19';
const SURFACE = '#12182A';
const MUTED = '#8B95A5';
const WHITE = '#FFFFFF';
const FR_BLUE = '#0055A4';
const FR_WHITE = '#FFFFFF';
const FR_RED = '#EF4135';

function r(n) { return Math.round(n * 100) / 100; }

/** Graine épaisse et ronde — rx proche de ry pour un volume "pill" */
function seedBold(cx, cy, size, id, ryScale = 0.19) {
    const ry = size * ryScale;
    const rx = ry * 0.88;
    const creaseW = rx * 0.38;
    const crease = `M ${r(cx)} ${r(cy - ry * 0.68)}
      Q ${r(cx + rx * 0.42)} ${r(cy)} ${r(cx)} ${r(cy + ry * 0.68)}`;
    return {
        cx, cy, rx, ry,
        defs: `<defs>
    <mask id="s-${id}">
      <ellipse cx="${r(cx)}" cy="${r(cy)}" rx="${r(rx)}" ry="${r(ry)}" fill="#fff"/>
      <path d="${crease}" fill="none" stroke="#000" stroke-width="${r(creaseW)}" stroke-linecap="round"/>
    </mask>
  </defs>`,
        mark: `<g transform="rotate(-16 ${r(cx)} ${r(cy)})">
      <ellipse cx="${r(cx)}" cy="${r(cy)}" rx="${r(rx)}" ry="${r(ry)}" fill="${GREEN}" mask="url(#s-${id})"/>
    </g>`,
    };
}

function bg(size) {
    const rad = size * 0.22;
    return `<rect width="${size}" height="${size}" rx="${r(rad)}" fill="${BG}"/>
      <rect x="${size * 0.06}" y="${size * 0.06}" width="${size * 0.88}" height="${size * 0.88}" rx="${r(rad * 0.9)}" fill="${SURFACE}"/>`;
}

const variants = {
    // A — Bol épais en U arrondi qui accueille la graine
    bol: (size, id) => {
        const cx = size / 2, cy = size / 2 + size * 0.04;
        const s = seedBold(cx, cy - size * 0.04, size, id);
        const w = size * 0.44, h = size * 0.22;
        const bowl = `M ${r(cx - w)} ${r(cy + h * 0.3)}
          Q ${r(cx - w)} ${r(cy + h)} ${r(cx)} ${r(cy + h)}
          Q ${r(cx + w)} ${r(cy + h)} ${r(cx + w)} ${r(cy + h * 0.3)}`;
        return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}">
  ${bg(size)} ${s.defs}
  <path d="${bowl}" fill="none" stroke="${MUTED}" stroke-width="${r(size * 0.034)}" stroke-linecap="round" opacity="0.35"/>
  <path d="${bowl}" fill="none" stroke="${GREEN}" stroke-width="${r(size * 0.038)}" stroke-linecap="round"/>
  ${s.mark}
</svg>`;
    },

    // B — 3 pills épaisses (repas) + graine
    pills: (size, id) => {
        const cx = size / 2 + size * 0.06, cy = size / 2;
        const s = seedBold(cx, cy, size, id);
        const pills = [
            { y: 0.30, w: 0.18, c: MUTED },
            { y: 0.44, w: 0.24, c: GREEN },
            { y: 0.58, w: 0.16, c: MUTED },
        ].map(p => {
            const x = size * 0.12, y = size * p.y, w = size * p.w, h = size * 0.055;
            return `<rect x="${r(x)}" y="${r(y)}" width="${r(w)}" height="${r(h)}" rx="${r(h / 2)}" fill="${p.c}" opacity="${p.c === GREEN ? 1 : 0.45}"/>`;
        }).join('');
        return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}">
  ${bg(size)} ${s.defs} ${pills} ${s.mark}
</svg>`;
    },

    // C — Anneau épais type "jauge" — segment vert = progression
    jauge: (size, id) => {
        const cx = size / 2, cy = size / 2;
        const s = seedBold(cx, cy, size, id);
        const R = size * 0.38, sw = size * 0.048;
        const track = `<circle cx="${r(cx)}" cy="${r(cy)}" r="${r(R)}" fill="none" stroke="${MUTED}" stroke-width="${r(sw)}" opacity="0.25"/>`;
        // Arc ~55% du cercle en vert (stroke épais arrondi)
        const arc = `<circle cx="${r(cx)}" cy="${r(cy)}" r="${r(R)}" fill="none" stroke="${GREEN}" stroke-width="${r(sw)}"
          stroke-linecap="round" stroke-dasharray="${r(R * 2 * Math.PI * 0.55)} ${r(R * 2 * Math.PI)}" transform="rotate(-90 ${r(cx)} ${r(cy)})"/>`;
        return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}">
  ${bg(size)} ${s.defs} ${track} ${arc} ${s.mark}
</svg>`;
    },

    // D — Crochets épais arrondis (parenthèses pills) autour de la graine
    crochets: (size, id) => {
        const cx = size / 2, cy = size / 2;
        const s = seedBold(cx, cy, size, id);
        const w = size * 0.36, h = size * 0.26, t = size * 0.042;
        const left = `<path d="M ${r(cx - w)} ${r(cy - h)}
          Q ${r(cx - w * 0.35)} ${r(cy - h)} ${r(cx - w * 0.35)} ${r(cy)}
          Q ${r(cx - w * 0.35)} ${r(cy + h)} ${r(cx - w)} ${r(cy + h)}"
          fill="none" stroke="${GREEN}" stroke-width="${r(t)}" stroke-linecap="round"/>`;
        const right = `<path d="M ${r(cx + w)} ${r(cy - h)}
          Q ${r(cx + w * 0.35)} ${r(cy - h)} ${r(cx + w * 0.35)} ${r(cy)}
          Q ${r(cx + w * 0.35)} ${r(cy + h)} ${r(cx + w)} ${r(cy + h)}"
          fill="none" stroke="${MUTED}" stroke-width="${r(t * 0.75)}" stroke-linecap="round" opacity="0.45"/>`;
        return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}">
  ${bg(size)} ${s.defs} ${left} ${right} ${s.mark}
</svg>`;
    },

    // E — 3 points épais en arc (repas du jour) — tricolore FR
    points: (size, id) => {
        const cx = size / 2;
        const seedCy = size * 0.58;
        const s = seedBold(cx, seedCy, size, id, 0.245);
        const dotCy = size * 0.50;
        const R = size * 0.30;
        const dotColors = [FR_BLUE, FR_WHITE, FR_RED];
        let dots = '';
        for (let i = 0; i < 3; i++) {
            const a = -Math.PI / 2 + (i - 1) * 0.55;
            const x = cx + Math.cos(a) * R;
            const y = dotCy + Math.sin(a) * R;
            const rad = i === 1 ? size * 0.052 : size * 0.040;
            dots += `<circle cx="${r(x)}" cy="${r(y)}" r="${r(rad)}" fill="${dotColors[i]}"/>`;
        }
        return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}">
  ${bg(size)} ${s.defs} ${dots} ${s.mark}
</svg>`;
    },

    // F — Badge arrondi épais (tampon) avec graine dedans
    badge: (size, id) => {
        const cx = size / 2, cy = size / 2;
        const s = seedBold(cx, cy, size, id);
        const bw = size * 0.62, bh = size * 0.52, br = size * 0.16, t = size * 0.034;
        return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}">
  ${bg(size)} ${s.defs}
  <rect x="${r(cx - bw / 2)}" y="${r(cy - bh / 2)}" width="${r(bw)}" height="${r(bh)}" rx="${r(br)}"
    fill="none" stroke="${MUTED}" stroke-width="${r(t * 0.6)}" opacity="0.3"/>
  <rect x="${r(cx - bw / 2)}" y="${r(cy - bh / 2)}" width="${r(bw)}" height="${r(bh)}" rx="${r(br)}"
    fill="none" stroke="${GREEN}" stroke-width="${r(t)}" stroke-dasharray="${r(bw * 0.55)} ${r(bw * 2)}" stroke-linecap="round"/>
  ${s.mark}
</svg>`;
    },

    // G — Double arc épais concentrique (ondes / futur)
    ondes: (size, id) => {
        const cx = size / 2, cy = size / 2;
        const s = seedBold(cx, cy, size, id);
        const arcs = [0.30, 0.42].map((R, i) => {
            const rad = size * R;
            const d = `M ${r(cx - rad)} ${r(cy)} A ${r(rad)} ${r(rad)} 0 0 1 ${r(cx + rad)} ${r(cy)}`;
            const c = i === 0 ? GREEN : MUTED;
            const sw = size * (0.032 - i * 0.006);
            return `<path d="${d}" fill="none" stroke="${c}" stroke-width="${r(sw)}" stroke-linecap="round" opacity="${i === 0 ? 1 : 0.35}"/>`;
        }).join('');
        return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}">
  ${bg(size)} ${s.defs} ${arcs} ${s.mark}
</svg>`;
    },

    // H — fm monogramme épais arrondi, graine = point du l
    fm: (size, id) => {
        const cx = size / 2, cy = size / 2 + size * 0.06;
        const s = seedBold(cx + size * 0.14, cy + size * 0.1, size * 0.85, id);
        const t = size * 0.065;
        const f = `<path d="M ${r(cx - size * 0.22)} ${r(cy - size * 0.18)}
          L ${r(cx - size * 0.22)} ${r(cy + size * 0.18)}
          M ${r(cx - size * 0.22)} ${r(cy - size * 0.02)}
          L ${r(cx - size * 0.02)} ${r(cy - size * 0.02)}" fill="none" stroke="${WHITE}" stroke-width="${r(t)}" stroke-linecap="round"/>`;
        const m = `<path d="M ${r(cx - size * 0.02)} ${r(cy + size * 0.18)}
          L ${r(cx - size * 0.02)} ${r(cy - size * 0.18)}
          L ${r(cx + size * 0.1)} ${r(cy + size * 0.04)}
          L ${r(cx + size * 0.22)} ${r(cy - size * 0.18)}
          L ${r(cx + size * 0.22)} ${r(cy + size * 0.18)}"
          fill="none" stroke="${WHITE}" stroke-width="${r(t)}" stroke-linecap="round" stroke-linejoin="round"/>`;
        return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}">
  ${bg(size)} ${s.defs} ${f} ${m} ${s.mark}
</svg>`;
    },
};

async function render(svg, file, w = 512) {
    await sharp(Buffer.from(svg), { density: 300 }).resize({ width: w }).png().toFile(file);
}

async function board(items, out) {
    const cols = 4, cellW = 420, cellH = 340, pad = 32, titleH = 82;
    const rows = Math.ceil(items.length / cols);
    const W = cols * cellW + pad * 2;
    const H = rows * cellH + pad * 2 + titleH;
    const composites = [];
    for (let i = 0; i < items.length; i++) {
        const col = i % cols, row = Math.floor(i / cols);
        const img = await sharp(items[i].png).resize({ width: 240, height: 240, fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } }).png().toBuffer();
        composites.push({ input: img, left: pad + col * cellW + 90, top: titleH + pad + row * cellH + 10 });
    }
    const labels = items.map((it, i) => {
        const col = i % cols, row = Math.floor(i / cols);
        const x = pad + col * cellW + 90, y = titleH + pad + row * cellH + 258;
        return `<text x="${x}" y="${y}" fill="${MUTED}" font-size="11" font-family="Arial">${it.code}</text>
<text x="${x}" y="${y + 18}" fill="${WHITE}" font-size="14" font-weight="600" font-family="Arial">${it.title}</text>`;
    }).join('');
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}">
  <rect width="100%" height="100%" fill="${BG}"/>
  <text x="${pad}" y="38" fill="${WHITE}" font-size="22" font-weight="700" font-family="Arial">FuturMeal — graine épaisse &amp; ronde</text>
  <text x="${pad}" y="60" fill="${MUTED}" font-size="12" font-family="Arial">Traits pleins · formes pills · volume arrondi</text>
  ${labels}
</svg>`;
    await sharp(Buffer.from(svg)).composite(composites).png().toFile(out);
}

async function main() {
    fs.mkdirSync(OUT, { recursive: true });
    const meta = [
        { key: 'bol', code: 'A · Bol', title: 'Bol épais' },
        { key: 'pills', code: 'B · Pills', title: '3 repas' },
        { key: 'jauge', code: 'C · Jauge', title: 'Anneau progression' },
        { key: 'crochets', code: 'D · Crochets', title: 'Parenthèses pills' },
        { key: 'points', code: 'E · Points', title: '3 points arc' },
        { key: 'badge', code: 'F · Badge', title: 'Tampon arrondi' },
        { key: 'ondes', code: 'G · Ondes', title: 'Arcs concentriques' },
        { key: 'fm', code: 'H · fm', title: 'Monogramme fm' },
    ];
    const items = [];
    for (const m of meta) {
        const svg = variants[m.key](512, m.key);
        const png = `${OUT}/${m.key}.png`;
        fs.writeFileSync(`${OUT}/${m.key}.svg`, svg);
        await render(svg, png);
        items.push({ ...m, png });
    }
    await board(items, `${OUT}/00-planche-v3.png`);
    console.log('OK', OUT);
}

main().catch(e => { console.error(e); process.exit(1); });
