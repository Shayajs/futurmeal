const opentype = require('opentype.js');
const sharp = require('sharp');
const fs = require('fs');
const path = require('path');

const FONT_PATH = '/tmp/outfit-600.ttf';
const OUT_DIR = '/home/shaya/prog/futurmeal/docs/charte';
const GREEN = '#00FF88';
const BG = '#0B0F19';
const SURFACE = '#12182A';
const FONT_SIZE = 160;

function r(n) { return Math.round(n * 100) / 100; }

function buildWordmark(font, textColor = '#FFFFFF') {
    const scale = FONT_SIZE / font.unitsPerEm;
    const ascender = font.ascender * scale;
    const baseline = ascender;
    const text = 'FuturMea';

    let cursor = 0;
    let pathData = '';
    let prevGlyph = null;
    for (const ch of text) {
        const glyph = font.charToGlyph(ch);
        if (prevGlyph) cursor += font.getKerningValue(prevGlyph, glyph) * scale;
        pathData += glyph.getPath(cursor, baseline, FONT_SIZE).toPathData(2);
        cursor += glyph.advanceWidth * scale;
        prevGlyph = glyph;
    }

    const advance = cursor;
    const lGlyph = font.charToGlyph('l');
    const lAdvance = lGlyph.advanceWidth * scale;
    const lBBox = lGlyph.getBoundingBox();
    const stemX = advance + lBBox.x1 * scale;
    const stemW = (lBBox.x2 - lBBox.x1) * scale;
    const stemTop = baseline - lBBox.y2 * scale;
    const stemBottomFull = baseline - lBBox.y1 * scale;

    const fullH = stemBottomFull - stemTop;
    const grainRy = fullH * 0.16;
    const grainRx = grainRy * 0.72;
    const gap = fullH * 0.05;
    const stemH = fullH - grainRy * 2 - gap;
    const grainCx = stemX + stemW / 2;
    const grainCy = stemBottomFull - grainRy;

    const pad = FONT_SIZE * 0.15;
    const vbW = advance + lAdvance + pad * 2;
    const vbH = ascender - font.descender * scale + pad * 2;

    const seed = seedDef(grainCx, grainCy, grainRx, grainRy, 'wm');

    return {
        vbW, vbH, pad, textColor, pathData, stemX, stemTop, stemW, stemH, seed,
        svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${r(vbW)} ${r(vbH)}" role="img" aria-label="FuturMeal">
  ${seed.defs}
  <g transform="translate(${r(pad)} ${r(pad)})">
    <path fill="${textColor}" d="${pathData}"/>
    <rect x="${r(stemX)}" y="${r(stemTop)}" width="${r(stemW)}" height="${r(stemH)}" rx="${r(stemW / 2)}" fill="${textColor}"/>
    ${seed.mark}
  </g>
</svg>`,
    };
}

function seedDef(cx, cy, rx, ry, id = 's') {
    const creaseW = rx * 0.3;
    const crease = `M ${r(cx)} ${r(cy - ry * 0.72)} Q ${r(cx + rx * 0.45)} ${r(cy)} ${r(cx)} ${r(cy + ry * 0.72)}`;
    return {
        cx, cy, rx, ry,
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

function seedOnlySvg(size = 64, id = 'icon') {
    const pad = size * 0.18;
    const cx = size / 2;
    const cy = size / 2 + size * 0.04;
    const ry = (size - pad * 2) / 2;
    const rx = ry * 0.72;
    const seed = seedDef(cx, cy, rx, ry, id);
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}" role="img" aria-label="FuturMeal">
  ${seed.defs}
  ${seed.mark}
</svg>`;
}

function appIconSvg(size, { bg = BG, rounded = 0.22, variant = 'dark' }) {
    const radius = size * rounded;
    const seedSize = size * (variant === 'large' ? 0.52 : 0.44);
    const inner = seedOnlySvg(Math.round(seedSize), `app-${variant}-${size}`);

    let bgEl = `<rect width="${size}" height="${size}" rx="${r(radius)}" fill="${bg}"/>`;
    if (variant === 'muted') {
        bgEl = `<rect width="${size}" height="${size}" rx="${r(radius)}" fill="${BG}"/>
      <rect x="${size * 0.08}" y="${size * 0.08}" width="${size * 0.84}" height="${size * 0.84}" rx="${r(radius * 0.85)}" fill="${SURFACE}"/>
      <circle cx="${size / 2}" cy="${size / 2}" r="${size * 0.38}" fill="rgba(0,255,136,0.08)"/>`;
    }
    if (variant === 'circle') {
        bgEl = `<circle cx="${size / 2}" cy="${size / 2}" r="${size / 2}" fill="${BG}"/>
      <circle cx="${size / 2}" cy="${size / 2}" r="${size * 0.46}" fill="${SURFACE}"/>`;
    }
    if (variant === 'ring') {
        bgEl = `<rect width="${size}" height="${size}" rx="${r(radius)}" fill="${BG}"/>
      <circle cx="${size / 2}" cy="${size / 2}" r="${size * 0.42}" fill="none" stroke="${GREEN}" stroke-width="${size * 0.012}" opacity="0.35"/>`;
    }

    const offset = (size - seedSize) / 2;
    return `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
  ${bgEl}
  <g transform="translate(${r(offset)} ${r(offset)})">${inner.replace(/^<svg[^>]*>|<\/svg>$/g, '').trim()}</g>
</svg>`;
}

function stackedSvg(font) {
    const scale = FONT_SIZE / font.unitsPerEm;
    const ascender = font.ascender * scale;
    const line1 = 'Futur';
    const line2 = 'Mea';
    let c1 = 0, p1 = '', pg = null;
    for (const ch of line1) {
        const g = font.charToGlyph(ch);
        if (pg) c1 += font.getKerningValue(pg, g) * scale;
        p1 += g.getPath(c1, ascender, FONT_SIZE).toPathData(2);
        c1 += g.advanceWidth * scale;
        pg = g;
    }
    let c2 = 0, p2 = '', pg2 = null;
    for (const ch of line2) {
        const g = font.charToGlyph(ch);
        if (pg2) c2 += font.getKerningValue(pg2, g) * scale;
        p2 += g.getPath(c2, ascender + FONT_SIZE * 0.95, FONT_SIZE).toPathData(2);
        c2 += g.advanceWidth * scale;
        pg2 = g;
    }
    const lGlyph = font.charToGlyph('l');
    const lBBox = lGlyph.getBoundingBox();
    const stemX = c2 + lBBox.x1 * scale;
    const stemW = (lBBox.x2 - lBBox.x1) * scale;
    const baseline2 = ascender + FONT_SIZE * 0.95;
    const stemTop = baseline2 - lBBox.y2 * scale;
    const stemBottom = baseline2 - lBBox.y1 * scale;
    const fullH = stemBottom - stemTop;
    const grainRy = fullH * 0.16;
    const grainRx = grainRy * 0.72;
    const gap = fullH * 0.05;
    const stemH = fullH - grainRy * 2 - gap;
    const grainCx = stemX + stemW / 2;
    const grainCy = stemBottom - grainRy;
    const seed = seedDef(grainCx, grainCy, grainRx, grainRy, 'stack');
    const vbW = Math.max(c1, c2 + lGlyph.advanceWidth * scale) + 48;
    const vbH = ascender - font.descender * scale + FONT_SIZE + 48;
    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${r(vbW)} ${r(vbH)}" role="img" aria-label="FuturMeal">
  ${seed.defs}
  <g transform="translate(24 24)">
    <path fill="#FFFFFF" d="${p1}"/>
    <path fill="#FFFFFF" d="${p2}"/>
    <rect x="${r(stemX)}" y="${r(stemTop)}" width="${r(stemW)}" height="${r(stemH)}" rx="${r(stemW / 2)}" fill="#FFFFFF"/>
    ${seed.mark}
  </g>
</svg>`;
}

async function renderSvg(svg, outPath, width) {
    await sharp(Buffer.from(svg), { density: 300 })
        .resize({ width })
        .png()
        .toFile(outPath);
}

async function compositeBoard(items, outPath) {
    const cols = 3;
    const cellW = 560;
    const cellH = 420;
    const pad = 40;
    const titleH = 80;
    const rows = Math.ceil(items.length / cols);
    const W = cols * cellW + pad * 2;
    const H = rows * cellH + pad * 2 + titleH;

    const composites = [];
    for (let i = 0; i < items.length; i++) {
        const col = i % cols;
        const row = Math.floor(i / cols);
        const img = await sharp(items[i].file).resize({ width: 420, height: 280, fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } }).png().toBuffer();
        composites.push({
            input: img,
            left: pad + col * cellW + 70,
            top: titleH + pad + row * cellH + 50,
        });
    }

    const labels = items.map((item, i) => {
        const col = i % cols;
        const row = Math.floor(i / cols);
        const x = pad + col * cellW + 70;
        const y = titleH + pad + row * cellH + 340;
        return `<text x="${x}" y="${y}" fill="#8B95A5" font-family="Arial,sans-serif" font-size="13">${item.label}</text>
<text x="${x}" y="${y + 22}" fill="#FFFFFF" font-family="Arial,sans-serif" font-size="18" font-weight="600">${item.title}</text>
<text x="${x}" y="${y + 44}" fill="#8B95A5" font-family="Arial,sans-serif" font-size="12">${item.desc}</text>`;
    }).join('');

    const board = `<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}">
  <rect width="100%" height="100%" fill="${BG}"/>
  <text x="${pad}" y="48" fill="#FFFFFF" font-family="Arial,sans-serif" font-size="28" font-weight="700">FuturMeal — déclinaisons logo</text>
  <text x="${pad}" y="72" fill="#8B95A5" font-family="Arial,sans-serif" font-size="14">Wordmark Typo validé · graine striée · généré vectoriellement</text>
  ${labels}
</svg>`;

    await sharp(Buffer.from(board)).composite(composites).png().toFile(outPath);
}

async function main() {
    const font = opentype.parse(fs.readFileSync(FONT_PATH).buffer);
    const decliDir = path.join(OUT_DIR, 'declinaisons');
    fs.mkdirSync(decliDir, { recursive: true });

    const wm = buildWordmark(font);
    fs.writeFileSync(`${OUT_DIR}/logo-typo-wordmark.svg`, wm.svg);
    await renderSvg(wm.svg, `${OUT_DIR}/logo-typo-transparent.png`, 1600);

    const seedSvg = seedOnlySvg(512, 'seed');
    fs.writeFileSync(`${decliDir}/01-graine-seule.svg`, seedSvg);
    await renderSvg(seedSvg, `${decliDir}/01-graine-seule.png`, 512);

    const icons = [
        ['02-app-carre', appIconSvg(512, { variant: 'dark' }), 'Carré sombre', 'Favicon / PWA / Android'],
        ['03-app-surface', appIconSvg(512, { variant: 'muted' }), 'Surface élevée', 'Profondeur charte fm-surface'],
        ['04-app-cercle', appIconSvg(512, { variant: 'circle' }), 'Cercle', 'Avatar, profil, badge'],
        ['05-app-anneau', appIconSvg(512, { variant: 'ring' }), 'Anneau vert', 'Accent primary subtil'],
    ];

    const boardItems = [
        { file: `${OUT_DIR}/logo-typo-transparent.png`, title: 'Wordmark principal', label: '01 · Horizontal', desc: 'Header, landing, documents' },
        { file: `${decliDir}/01-graine-seule.png`, title: 'Graine seule', label: '02 · Monogramme', desc: 'Favicon 32px, watermark discret' },
    ];

    for (const [name, svg, title, desc] of icons) {
        fs.writeFileSync(`${decliDir}/${name}.svg`, svg);
        await renderSvg(svg, `${decliDir}/${name}.png`, 512);
        await renderSvg(svg, `${decliDir}/${name}-180.png`, 180);
        boardItems.push({ file: `${decliDir}/${name}.png`, title, label: name.replace(/^\d+-/, '').replace(/-/g, ' '), desc });
    }

    const stacked = stackedSvg(font);
    fs.writeFileSync(`${decliDir}/06-stack.svg`, stacked);
    const stackedPreview = stacked.replace('<g transform', `<rect width="100%" height="100%" fill="${BG}"/><g transform`);
    await renderSvg(stackedPreview, `${decliDir}/06-stack.png`, 800);
    boardItems.push({ file: `${decliDir}/06-stack.png`, title: 'Stack vertical', label: '06 · Futur / Meal', desc: 'Réseaux sociaux, formats carrés' });

    const compactSvg = wm.svg.replace(`viewBox="0 0 ${r(wm.vbW)} ${r(wm.vbH)}"`, `viewBox="0 0 ${r(wm.vbW)} ${r(wm.vbH)}" width="480"`);
    const compactPreview = compactSvg.replace('<g transform', `<rect width="100%" height="100%" fill="${BG}"/><g transform`);
    await renderSvg(compactPreview, `${decliDir}/07-nav-compact.png`, 640);
    boardItems.push({ file: `${decliDir}/07-nav-compact.png`, title: 'Nav compact', label: '07 · Header app', desc: 'Barre de navigation Livewire' });

    // Favicon sizes
    for (const s of [16, 32, 48]) {
        await renderSvg(appIconSvg(s * 4, { variant: 'dark', rounded: 0.2 }), `${decliDir}/favicon-${s}.png`, s);
    }

    await compositeBoard(boardItems, `${decliDir}/00-planche-declinaisons.png`);
    console.log('OK', decliDir);
}

main().catch((e) => { console.error(e); process.exit(1); });
