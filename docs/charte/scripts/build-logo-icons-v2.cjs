const sharp = require('sharp');
const fs = require('fs');
const path = require('path');

const OUT = '/home/shaya/prog/futurmeal/docs/charte/declinaisons-v2';
const GREEN = '#00FF88';
const BG = '#0B0F19';
const SURFACE = '#12182A';
const MUTED = '#8B95A5';
const WHITE = '#FFFFFF';

function r(n) { return Math.round(n * 100) / 100; }

function seedDef(cx, cy, rx, ry, id) {
    const creaseW = rx * 0.3;
    const crease = `M ${r(cx)} ${r(cy - ry * 0.72)} Q ${r(cx + rx * 0.45)} ${r(cy)} ${r(cx)} ${r(cy + ry * 0.72)}`;
    return {
        defs: `<defs>
    <mask id="seed-${id}">
      <ellipse cx="${r(cx)}" cy="${r(cy)}" rx="${r(rx)}" ry="${r(ry)}" fill="#fff"/>
      <path d="${crease}" fill="none" stroke="#000" stroke-width="${r(creaseW)}" stroke-linecap="round"/>
    </mask>
  </defs>`,
        mark: `<g transform="rotate(-18 ${r(cx)} ${r(cy)})">
      <ellipse cx="${r(cx)}" cy="${r(cy)}" rx="${r(rx)}" ry="${r(ry)}" fill="${GREEN}" mask="url(#seed-${id})"/>
    </g>`,
    };
}

/** Seed slightly smaller to leave room for surrounding marks */
function seedAt(size, id, scale = 0.36) {
    const cx = size / 2;
    const cy = size / 2 + size * 0.02;
    const ry = size * scale;
    const rx = ry * 0.72;
    return { cx, cy, rx, ry, ...seedDef(cx, cy, rx, ry, id) };
}

function bgSquare(size, radius = 0.22) {
    return `<rect width="${size}" height="${size}" rx="${r(size * radius)}" fill="${BG}"/>
    <rect x="${size * 0.06}" y="${size * 0.06}" width="${size * 0.88}" height="${size * 0.88}" rx="${r(size * radius * 0.9)}" fill="${SURFACE}"/>`;
}

const variants = {
    // A — Arc assiette ouverte (repas) autour de la graine
    assiette: (size, id) => {
        const s = seedAt(size, id, 0.34);
        const cx = s.cx, cy = s.cy, R = size * 0.38;
        const arc = `M ${r(cx - R * 0.85)} ${r(cy + R * 0.35)}
          A ${r(R)} ${r(R)} 0 1 1 ${r(cx + R * 0.85)} ${r(cy + R * 0.35)}`;
        return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}">
  ${bgSquare(size)}
  ${s.defs}
  <path d="${arc}" fill="none" stroke="${MUTED}" stroke-width="${r(size * 0.018)}" stroke-linecap="round" opacity="0.55"/>
  <path d="${arc}" fill="none" stroke="${GREEN}" stroke-width="${r(size * 0.022)}" stroke-linecap="round" stroke-dasharray="${r(R * 1.1)} ${r(R * 2.2)}" opacity="0.9"/>
  ${s.mark}
</svg>`;
    },

    // B — 7 ticks = semaine planifiée, tick du jour en vert
    semaine: (size, id) => {
        const s = seedAt(size, id, 0.32);
        const cx = s.cx, cy = s.cy, R = size * 0.4;
        let ticks = '';
        for (let i = 0; i < 7; i++) {
            const a = (-Math.PI / 2) + (i * 2 * Math.PI / 7);
            const x1 = cx + Math.cos(a) * R;
            const y1 = cy + Math.sin(a) * R;
            const x2 = cx + Math.cos(a) * (R + size * 0.06);
            const y2 = cy + Math.sin(a) * (R + size * 0.06);
            const color = i === 4 ? GREEN : MUTED;
            const sw = i === 4 ? size * 0.028 : size * 0.018;
            ticks += `<line x1="${r(x1)}" y1="${r(y1)}" x2="${r(x2)}" y2="${r(y2)}" stroke="${color}" stroke-width="${r(sw)}" stroke-linecap="round"/>`;
        }
        return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}">
  ${bgSquare(size)}
  ${s.defs}
  <circle cx="${r(cx)}" cy="${r(cy)}" r="${r(R)}" fill="none" stroke="${MUTED}" stroke-width="${r(size * 0.012)}" opacity="0.25"/>
  ${ticks}
  ${s.mark}
</svg>`;
    },

    // C — Horizon + flèche futur (courbe bas → haut droite)
    horizon: (size, id) => {
        const s = seedAt(size, id, 0.34);
        const cx = s.cx, cy = s.cy + size * 0.06;
        const s2 = seedDef(s.cx, cy, s.rx, s.ry, id);
        const lineY = cy + s.ry + size * 0.1;
        const arrow = `M ${r(size * 0.18)} ${r(lineY + size * 0.04)}
          Q ${r(cx)} ${r(lineY - size * 0.08)} ${r(size * 0.82)} ${r(lineY - size * 0.22)}`;
        return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}">
  ${bgSquare(size)}
  ${s2.defs}
  <path d="${arrow}" fill="none" stroke="${MUTED}" stroke-width="${r(size * 0.016)}" stroke-linecap="round" opacity="0.45"/>
  <path d="${arrow}" fill="none" stroke="${GREEN}" stroke-width="${r(size * 0.022)}" stroke-linecap="round"/>
  <polygon points="${r(size * 0.82)},${r(lineY - size * 0.22)} ${r(size * 0.74)},${r(lineY - size * 0.16)} ${r(size * 0.78)},${r(lineY - size * 0.28)}" fill="${GREEN}"/>
  ${s2.mark}
</svg>`;
    },

    // D — 3 repas (traits) + graine = planification concrète
    repas: (size, id) => {
        const s = seedAt(size, id, 0.34);
        const cx = s.cx + size * 0.06, cy = s.cy;
        const s2 = seedDef(cx, cy, s.rx, s.ry, id);
        const x0 = size * 0.14;
        const lines = [
            [0.28, 0.38, MUTED],
            [0.42, 0.52, GREEN],
            [0.56, 0.66, MUTED],
        ].map(([y1, y2, c], i) =>
            `<line x1="${r(x0)}" y1="${r(size * y1)}" x2="${r(x0 + size * 0.14)}" y2="${r(size * y1)}" stroke="${c}" stroke-width="${r(size * 0.014)}" stroke-linecap="round"/>
       <line x1="${r(x0 + size * 0.04)}" y1="${r(size * y2)}" x2="${r(x0 + size * 0.2)}" y2="${r(size * y2)}" stroke="${c}" stroke-width="${r(size * 0.01)}" stroke-linecap="round" opacity="0.5"/>`
        ).join('');
        return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}">
  ${bgSquare(size)}
  ${s2.defs}
  ${lines}
  ${s2.mark}
</svg>`;
    },

    // E — Crochets Futur (>) autour de la graine — identité FuturMeal
    futur: (size, id) => {
        const s = seedAt(size, id, 0.32);
        const cx = s.cx, cy = s.cy;
        const w = size * 0.42, h = size * 0.28;
        const left = `<path d="M ${r(cx - w)} ${r(cy - h)} L ${r(cx - w * 0.55)} ${r(cy)} L ${r(cx - w)} ${r(cy + h)}" fill="none" stroke="${GREEN}" stroke-width="${r(size * 0.024)}" stroke-linecap="round" stroke-linejoin="round"/>`;
        const right = `<path d="M ${r(cx + w)} ${r(cy - h)} L ${r(cx + w * 0.55)} ${r(cy)} L ${r(cx + w)} ${r(cy + h)}" fill="none" stroke="${MUTED}" stroke-width="${r(size * 0.018)}" stroke-linecap="round" stroke-linejoin="round" opacity="0.5"/>`;
        return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}">
  ${bgSquare(size)}
  ${s.defs}
  ${left}${right}
  ${s.mark}
</svg>`;
    },

    // F — Pousse depuis la graine (≠ feuille NuLife : tige + 2 mini bracts)
    pousse: (size, id) => {
        const s = seedAt(size, id, 0.34);
        const cx = s.cx, cy = s.cy + size * 0.04;
        const s2 = seedDef(cx, cy, s.rx, s.ry, id);
        const top = cy - s.ry - size * 0.02;
        const stem = `<line x1="${r(cx)}" y1="${r(top)}" x2="${r(cx)}" y2="${r(top - size * 0.14)}" stroke="${GREEN}" stroke-width="${r(size * 0.014)}" stroke-linecap="round"/>`;
        const leafL = `<path d="M ${r(cx)} ${r(top - size * 0.08)} Q ${r(cx - size * 0.1)} ${r(top - size * 0.16)} ${r(cx - size * 0.06)} ${r(top - size * 0.2)}" fill="none" stroke="${GREEN}" stroke-width="${r(size * 0.012)}" stroke-linecap="round"/>`;
        const leafR = `<path d="M ${r(cx)} ${r(top - size * 0.1)} Q ${r(cx + size * 0.08)} ${r(top - size * 0.15)} ${r(cx + size * 0.04)} ${r(top - size * 0.19)}" fill="none" stroke="${MUTED}" stroke-width="${r(size * 0.01)}" stroke-linecap="round" opacity="0.6"/>`;
        return `<svg xmlns="http://www/svg" viewBox="0 0 ${size} ${size}">
  ${bgSquare(size)}
  ${s2.defs}
  ${stem}${leafL}${leafR}
  ${s2.mark}
</svg>`.replace('http://www/svg', 'http://www.w3.org/2000/svg');
    },
};

async function render(svg, file, w = 512) {
    await sharp(Buffer.from(svg), { density: 300 }).resize({ width: w }).png().toFile(file);
}

async function buildBoard(items, out) {
    const cols = 3, cellW = 560, cellH = 400, pad = 40, titleH = 90;
    const rows = Math.ceil(items.length / cols);
    const W = cols * cellW + pad * 2;
    const H = rows * cellH + pad * 2 + titleH;
    const composites = [];
    for (let i = 0; i < items.length; i++) {
        const col = i % cols, row = Math.floor(i / cols);
        const img = await sharp(items[i].png).resize({ width: 300, height: 300, fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } }).png().toBuffer();
        composites.push({ input: img, left: pad + col * cellW + 130, top: titleH + pad + row * cellH + 20 });
    }
    const labels = items.map((it, i) => {
        const col = i % cols, row = Math.floor(i / cols);
        const x = pad + col * cellW + 130;
        const y = titleH + pad + row * cellH + 330;
        return `<text x="${x}" y="${y}" fill="${MUTED}" font-family="Arial,sans-serif" font-size="12">${it.code}</text>
<text x="${x}" y="${y + 20}" fill="${WHITE}" font-family="Arial,sans-serif" font-size="17" font-weight="600">${it.title}</text>
<text x="${x}" y="${y + 40}" fill="${MUTED}" font-family="Arial,sans-serif" font-size="11">${it.desc}</text>`;
    }).join('');
    const base = `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}">
  <rect width="100%" height="100%" fill="${BG}"/>
  <text x="${pad}" y="42" fill="${WHITE}" font-family="Arial,sans-serif" font-size="26" font-weight="700">FuturMeal — icônes différenciées</text>
  <text x="${pad}" y="68" fill="${MUTED}" font-family="Arial,sans-serif" font-size="13">Graine striée + élément contextuel · moins proche d'un logo santé générique</text>
  ${labels}
</svg>`;
    await sharp(Buffer.from(base)).composite(composites).png().toFile(out);
}

async function main() {
    fs.mkdirSync(OUT, { recursive: true });
    const meta = [
        { key: 'assiette', code: 'A', title: 'Arc assiette', desc: 'Repas · nutrition · pas une feuille' },
        { key: 'semaine', code: 'B', title: '7 ticks semaine', desc: 'Planification · jour actif en vert' },
        { key: 'horizon', code: 'C', title: 'Horizon futur', desc: 'Courbe + flèche · performance demain' },
        { key: 'repas', code: 'D', title: 'Liste repas', desc: '3 créneaux · cœur produit' },
        { key: 'futur', code: 'E', title: 'Chevrons Futur', desc: 'Identité FuturMeal · très distinctif' },
        { key: 'pousse', code: 'F', title: 'Pousse', desc: 'Croissance · tige, pas feuille seule' },
    ];
    const board = [];
    for (const m of meta) {
        const svg = variants[m.key](512, m.key);
        const png = `${OUT}/${m.key}.png`;
        const svgPath = `${OUT}/${m.key}.svg`;
        fs.writeFileSync(svgPath, svg);
        await render(svg, png);
        board.push({ ...m, png });
    }
    await buildBoard(board, `${OUT}/00-planche-v2.png`);
    console.log('OK', OUT);
}

main().catch((e) => { console.error(e); process.exit(1); });
