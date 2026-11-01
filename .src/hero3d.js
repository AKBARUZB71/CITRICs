// Bosh ekran: real vaqtli 3D sahna (Three.js) — markazda turli mahsulotlar navbatma-navbat almashadi
// (banka, 25 kg qop, bo'yoq shishasi, aroma bankasi), atrofida sitrus bo'laklari, yalpiz, kristallar
import * as THREE from 'three';
import { RoomEnvironment } from 'https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/environments/RoomEnvironment.js';

const host = document.querySelector('.h3d');
const REDUCE = matchMedia('(prefers-reduced-motion: reduce)').matches;
const MOBILE = matchMedia('(max-width: 900px)').matches;
const FONT = 'Manrope, system-ui, sans-serif';
let ANISO = 8;

function webglOk() {
  try { const c = document.createElement('canvas'); return !!(c.getContext('webgl2') || c.getContext('webgl')); } catch (e) { return false; }
}

if (host && webglOk()) (document.fonts ? document.fonts.ready : Promise.resolve()).then(init).catch(init);

// Teksturalar yuqori aniqlikda chiziladi (S marta katta kanvas), shuning uchun yaqindan ham tiniq
function canvasTex(w, h, draw, S) {
  S = S || (MOBILE ? 1 : 2);
  const c = document.createElement('canvas'); c.width = w * S; c.height = h * S;
  const g = c.getContext('2d'); g.scale(S, S);
  draw(g, w, h);
  const t = new THREE.CanvasTexture(c);
  t.colorSpace = THREE.SRGBColorSpace; t.anisotropy = ANISO;
  return t;
}
function text(g, str, x, y, font, color, align) {
  g.font = font; g.fillStyle = color; g.textAlign = align || 'center'; g.textBaseline = 'middle'; g.fillText(str, x, y);
}
function lemonIcon(g, x, y, r) {
  g.save(); g.translate(x, y);
  g.beginPath(); g.arc(0, 0, r, 0, 7); g.fillStyle = '#ffd21f'; g.fill();
  g.beginPath(); g.arc(0, 0, r * .88, 0, 7); g.fillStyle = '#fff6c8'; g.fill();
  for (let i = 0; i < 10; i++) {
    g.save(); g.rotate(i / 10 * Math.PI * 2);
    g.beginPath(); g.moveTo(0, 0); g.arc(0, 0, r * .78, -.27, .27); g.closePath();
    g.fillStyle = i % 2 ? '#ffd83a' : '#ffcb12'; g.fill(); g.restore();
  }
  g.beginPath(); g.arc(0, 0, r * .12, 0, 7); g.fillStyle = '#fff6c8'; g.fill();
  g.restore();
}

// ---------- 1. Banka yorlig'i ----------
function canLabel() {
  return canvasTex(2048, 1024, (g, W, H) => {
    const bg = g.createLinearGradient(0, 0, 0, H);
    bg.addColorStop(0, '#ffbe2e'); bg.addColorStop(.55, '#ffa800'); bg.addColorStop(1, '#f07f00');
    g.fillStyle = bg; g.fillRect(0, 0, W, H);
    const wave = (y, amp, col) => {
      g.beginPath(); g.moveTo(0, H);
      for (let x = 0; x <= W; x += 4) g.lineTo(x, y + Math.sin(x / W * Math.PI * 4) * amp);
      g.lineTo(W, H); g.closePath(); g.fillStyle = col; g.fill();
    };
    wave(H * .66, 34, '#8cc63f'); wave(H * .72, 30, '#70b32f'); wave(H * .84, 22, '#4f8a1c');
    g.fillStyle = 'rgba(255,255,255,.9)'; g.fillRect(0, 22, W, 6); g.fillRect(0, H - 30, W, 6);
    g.save(); g.shadowColor = 'rgba(120,50,0,.35)'; g.shadowBlur = 18; g.shadowOffsetY = 6;
    text(g, 'CITRIC', W * .25, H * .36, '800 190px ' + FONT, '#fff'); g.restore();
    text(g, '.UZ', W * .25 + 300, H * .36 + 60, '800 50px ' + FONT, '#16220e');
    text(g, 'FOOD  INGREDIENTS', W * .25, H * .36 + 150, '700 40px ' + FONT, 'rgba(255,255,255,.92)');
    lemonIcon(g, W * .75, H * .32, 150);
    text(g, 'E330', W * .75, H * .32 + 250, '800 92px ' + FONT, '#fff');
  });
}

// ---------- 2. Qop (25 kg) ----------
function sackLabel() {
  return canvasTex(1024, 1400, (g, W, H) => {
    g.fillStyle = '#f6f4ee'; g.fillRect(0, 0, W, H);
    // To'qima izi
    g.globalAlpha = .07; g.fillStyle = '#7d7a70';
    for (let y = 0; y < H; y += 6) g.fillRect(0, y, W, 2);
    for (let x = 0; x < W; x += 6) g.fillRect(x, 0, 2, H);
    g.globalAlpha = 1;
    g.fillStyle = '#70b32f'; g.fillRect(0, 0, W, 210);
    g.fillStyle = '#ffa800'; g.fillRect(0, 210, W, 16);
    text(g, 'CITRIC', W / 2 - 40, 105, '800 120px ' + FONT, '#fff');
    text(g, '.UZ', W / 2 + 225, 130, '800 46px ' + FONT, '#16220e');
    lemonIcon(g, W / 2, 470, 150);
    text(g, 'CITRIC ACID', W / 2, 740, '800 104px ' + FONT, '#16220e');
    text(g, 'MONOHYDRATE  ·  E330', W / 2, 840, '700 48px ' + FONT, '#4f8a1c');
    g.fillStyle = '#16220e'; g.fillRect(W / 2 - 220, 930, 440, 4);
    text(g, 'FOOD GRADE', W / 2, 1010, '700 44px ' + FONT, '#5b6157');
    g.fillStyle = '#ffa800'; g.fillRect(W / 2 - 230, 1110, 460, 150);
    text(g, 'NET 25 KG', W / 2, 1186, '800 84px ' + FONT, '#fff');
  });
}

// ---------- 3. Bo'yoq shishasi yorlig'i ----------
function bottleLabel() {
  return canvasTex(1400, 700, (g, W, H) => {
    g.fillStyle = '#ffffff'; g.fillRect(0, 0, W, H);
    g.fillStyle = '#70b32f'; g.fillRect(0, 0, W, 90);
    g.fillStyle = '#ffa800'; g.fillRect(0, H - 70, W, 70);
    text(g, 'CITRIC.UZ', W / 2, 46, '800 54px ' + FONT, '#fff');
    text(g, 'FOOD COLOR', W / 2, 230, '800 120px ' + FONT, '#16220e');
    text(g, 'PONCEAU 4R  ·  E124', W / 2, 360, '700 62px ' + FONT, '#d21f2b');
    g.fillStyle = '#d21f2b'; g.beginPath(); g.arc(W / 2, 500, 56, 0, 7); g.fill();
    text(g, 'LIQUID  ·  500 ML', W / 2, H - 35, '700 40px ' + FONT, '#fff');
  });
}

// ---------- 4. Aroma bankasi yorlig'i ----------
function jarLabel() {
  return canvasTex(2048, 760, (g, W, H) => {
    g.fillStyle = '#ffffff'; g.fillRect(0, 0, W, H);
    g.fillStyle = '#ffa800'; g.fillRect(0, 0, W, 70); g.fillRect(0, H - 70, W, 70);
    const face = (cx) => {
      text(g, 'CITRIC.UZ', cx, 150, '800 64px ' + FONT, '#70b32f');
      text(g, 'VANILLIN', cx, 330, '800 150px ' + FONT, '#16220e');
      text(g, 'FOOD FLAVOR  ·  1 KG', cx, 480, '700 56px ' + FONT, '#5b6157');
      g.fillStyle = '#70b32f'; g.fillRect(cx - 160, 560, 320, 10);
    };
    face(W * .25); face(W * .75);
  });
}

// ---------- Sitrus kesimi va barg ----------
function sliceTexture(c) {
  return canvasTex(512, 512, (g, W) => {
    const R = W / 2; g.translate(R, R);
    g.beginPath(); g.arc(0, 0, R, 0, 7); g.fillStyle = c.rindDark; g.fill();
    g.beginPath(); g.arc(0, 0, R - 12, 0, 7); g.fillStyle = c.rind; g.fill();
    g.beginPath(); g.arc(0, 0, R - 22, 0, 7); g.fillStyle = c.pith; g.fill();
    const n = 10;
    for (let i = 0; i < n; i++) {
      const a0 = i / n * Math.PI * 2 + .045, a1 = (i + 1) / n * Math.PI * 2 - .045;
      g.save(); g.beginPath(); g.moveTo(Math.cos((a0 + a1) / 2) * 22, Math.sin((a0 + a1) / 2) * 22);
      g.arc(0, 0, R - 36, a0, a1); g.closePath();
      const gr = g.createRadialGradient(0, 0, 10, 0, 0, R - 36);
      gr.addColorStop(0, c.flesh0); gr.addColorStop(.7, c.flesh1); gr.addColorStop(1, c.flesh2);
      g.fillStyle = gr; g.fill(); g.clip();
      for (let k = 0; k < 220; k++) {
        const a = a0 + Math.random() * (a1 - a0), r = 30 + Math.random() * (R - 70);
        g.beginPath(); g.ellipse(Math.cos(a) * r, Math.sin(a) * r, 3 + Math.random() * 6, 1.5 + Math.random() * 2, a, 0, 7);
        g.fillStyle = 'rgba(255,255,255,' + (.08 + Math.random() * .22) + ')'; g.fill();
      }
      g.restore();
    }
    g.beginPath(); g.arc(0, 0, 20, 0, 7); g.fillStyle = c.pith; g.fill();
  });
}
function leafTexture() {
  return canvasTex(256, 512, (g, W, H) => {
    const gr = g.createLinearGradient(0, 0, W, 0);
    gr.addColorStop(0, '#2f7a1c'); gr.addColorStop(.5, '#4fa62a'); gr.addColorStop(1, '#2f7a1c');
    g.fillStyle = gr; g.fillRect(0, 0, W, H);
    g.strokeStyle = 'rgba(210,255,170,.55)'; g.lineWidth = 6;
    g.beginPath(); g.moveTo(W / 2, H); g.lineTo(W / 2, 0); g.stroke();
    g.lineWidth = 3;
    for (let y = H * .9; y > H * .1; y -= 52) {
      g.beginPath(); g.moveTo(W / 2, y); g.quadraticCurveTo(W * .3, y - 30, W * .06, y - 70); g.stroke();
      g.beginPath(); g.moveTo(W / 2, y); g.quadraticCurveTo(W * .7, y - 30, W * .94, y - 70); g.stroke();
    }
  });
}

const V = (x, y) => new THREE.Vector2(x, y);

function init() {
  const canvas = host.querySelector('canvas');
  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true, powerPreference: 'high-performance' });
  ANISO = renderer.capabilities.getMaxAnisotropy();
  // Tiniqlik: ekran zichligidan yuqoriroq (supersampling), keyin kadr tezligiga qarab moslashadi
  let dpr = Math.min(Math.max((devicePixelRatio || 1) * (MOBILE ? 1.5 : 2), 2), 3);
  renderer.setPixelRatio(dpr);
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.05;

  const scene = new THREE.Scene();
  const pmrem = new THREE.PMREMGenerator(renderer);
  scene.environment = pmrem.fromScene(new RoomEnvironment(), .04).texture;
  const camera = new THREE.PerspectiveCamera(30, 1, .1, 100);
  camera.position.set(0, 0, 14);

  const key = new THREE.DirectionalLight(0xffffff, 1.6); key.position.set(5, 6, 8); scene.add(key);
  const rim = new THREE.DirectionalLight(0xffc266, 2.4); rim.position.set(-6, 3, -5); scene.add(rim);
  const fill = new THREE.DirectionalLight(0xbfffa0, .5); fill.position.set(-4, -5, 6); scene.add(fill);

  const root = new THREE.Group(); scene.add(root);
  const metal = new THREE.MeshStandardMaterial({ color: 0xdfe3e8, metalness: 1, roughness: .24, side: THREE.DoubleSide });
  const SEG = MOBILE ? 96 : 160;

  // ---------- Mahsulotlar ----------
  function makeCan() {
    const R = 1.1, H = 3.5, g = new THREE.Group();
    g.add(new THREE.Mesh(new THREE.CylinderGeometry(R, R, H, SEG, 1, true),
      new THREE.MeshPhysicalMaterial({ map: canLabel(), metalness: .35, roughness: .3, clearcoat: 1, clearcoatRoughness: .1 })));
    const top = new THREE.Mesh(new THREE.LatheGeometry([V(R, 0), V(R * .99, .08), V(R * .9, .28), V(R * .86, .36), V(R * .875, .42), V(R * .85, .45), V(R * .8, .41), V(R * .79, .35), V(0, .35)], SEG), metal);
    top.position.y = H / 2; g.add(top);
    const bottom = new THREE.Mesh(new THREE.LatheGeometry([V(0, -.18), V(R * .72, -.26), V(R * .86, -.24), V(R * .99, -.06), V(R, 0)], SEG), metal);
    bottom.position.y = -H / 2; g.add(bottom);
    const tab = new THREE.Mesh(new THREE.CapsuleGeometry(.13, .42, 8, 24), metal);
    tab.rotation.z = Math.PI / 2; tab.scale.set(1, 1, .18); tab.position.set(.18, H / 2 + .37, 0); g.add(tab);
    const rivet = new THREE.Mesh(new THREE.CylinderGeometry(.07, .07, .05, 32), metal); rivet.position.set(0, H / 2 + .37, 0); g.add(rivet);
    return { g, tilt: [.16, 0, -.3], spin: .45 };
  }
  function makeSack() {
    const geo = new THREE.BoxGeometry(2.3, 3.2, .8, 64, 90, 18);
    const p = geo.attributes.position;
    for (let i = 0; i < p.count; i++) {
      const x = p.getX(i), y = p.getY(i), z = p.getZ(i);
      const nx = x / 1.15, ny = y / 1.6;
      const puff = Math.sqrt(Math.max(0, 1 - Math.pow(Math.abs(nx), 6))) * Math.sqrt(Math.max(0, 1 - Math.pow(Math.abs(ny), 8)));
      p.setZ(i, (z / .4) * (.05 + .55 * puff));
      p.setX(i, x * (1 - .05 * (1 - ny * ny)) + Math.sin(y * 3.1) * .015);
      // Yuqori tikuv: chetlari biroz to'lqinli
      if (Math.abs(ny) > .97) p.setY(i, y + Math.sin(x * 14) * .015);
    }
    geo.computeVertexNormals();
    const label = sackLabel();
    const front = new THREE.MeshPhysicalMaterial({ map: label, roughness: .72, sheen: .5, sheenRoughness: .8, sheenColor: new THREE.Color(0xffffff) });
    const side = new THREE.MeshStandardMaterial({ color: 0xf2f0ea, roughness: .8 });
    const m = new THREE.Mesh(geo, [side, side, side, side, front, front]);
    const g = new THREE.Group(); g.add(m);
    return { g, tilt: [.08, 0, -.12], sway: .6 };
  }
  function makeBottle() {
    const prof = [V(0, -1.75), V(.62, -1.75), V(.74, -1.68), V(.78, -1.5), V(.78, .55), V(.74, .8), V(.58, 1.05), V(.38, 1.25), V(.32, 1.42), V(.32, 1.62), V(.36, 1.66), V(.36, 1.72), V(.3, 1.74)];
    const g = new THREE.Group();
    const glass = new THREE.MeshPhysicalMaterial({ color: 0xffffff, roughness: .04, metalness: 0, clearcoat: 1, clearcoatRoughness: .03, transparent: true, opacity: .16, depthWrite: false, envMapIntensity: 1.6 });
    const liquidProf = [V(0, -1.66), V(.68, -1.66), V(.72, -1.5), V(.72, .55), V(.68, .7), V(0, .7)];
    g.add(new THREE.Mesh(new THREE.LatheGeometry(liquidProf, SEG), new THREE.MeshPhysicalMaterial({ color: 0xd3121f, roughness: .12, clearcoat: 1, sheen: .3, emissive: 0x3a0005 })));
    const shell = new THREE.Mesh(new THREE.LatheGeometry(prof, SEG), glass); shell.renderOrder = 2; g.add(shell);
    const lab = new THREE.Mesh(new THREE.CylinderGeometry(.79, .79, 1.35, SEG, 1, true, -Math.PI * .45, Math.PI * .9),
      new THREE.MeshPhysicalMaterial({ map: bottleLabel(), roughness: .45, clearcoat: .4 }));
    lab.position.y = -.5; g.add(lab);
    const cap = new THREE.Mesh(new THREE.CylinderGeometry(.42, .42, .5, SEG), new THREE.MeshPhysicalMaterial({ color: 0x70b32f, roughness: .35, clearcoat: .8 }));
    cap.position.y = 1.86; g.add(cap);
    g.scale.setScalar(1.12);
    return { g, tilt: [.12, 0, .22], sway: .8 };
  }
  function makeJar() {
    const g = new THREE.Group();
    const prof = [V(0, -1.3), V(.9, -1.3), V(1.02, -1.22), V(1.06, -1.05), V(1.06, .85), V(1.0, .95), V(.92, .98), V(.92, 1.08)];
    g.add(new THREE.Mesh(new THREE.LatheGeometry(prof, SEG), new THREE.MeshPhysicalMaterial({ color: 0xfbfaf6, roughness: .38, clearcoat: .6, sheen: .3 })));
    const lab = new THREE.Mesh(new THREE.CylinderGeometry(1.068, 1.068, 1.5, SEG, 1, true), new THREE.MeshPhysicalMaterial({ map: jarLabel(), roughness: .4, clearcoat: .5 }));
    lab.position.y = -.17; g.add(lab);
    const lid = new THREE.Mesh(new THREE.CylinderGeometry(1.1, 1.1, .6, SEG), new THREE.MeshPhysicalMaterial({ color: 0x70b32f, roughness: .3, clearcoat: 1, clearcoatRoughness: .15 }));
    lid.position.y = 1.32; g.add(lid);
    // Qopqoq qirralari
    const ribs = new THREE.Mesh(new THREE.CylinderGeometry(1.115, 1.115, .44, 72, 1, true), new THREE.MeshStandardMaterial({ color: 0x5f9c26, roughness: .45, wireframe: false }));
    ribs.position.y = 1.32; ribs.scale.set(1, 1, 1); g.add(ribs);
    g.scale.setScalar(1.15);
    return { g, tilt: [.22, 0, -.18], spin: .38 };
  }
  const products = [makeCan(), makeSack(), makeBottle(), makeJar()];
  products.forEach((p) => {
    p.pivot = new THREE.Group(); p.pivot.add(p.g);
    p.pivot.rotation.set(p.tilt[0], 0, p.tilt[2]);
    p.pivot.visible = false; p.k = 0;
    root.add(p.pivot);
  });

  // ---------- Atrofdagi bo'laklar ----------
  const LEMON = { rindDark: '#e9b400', rind: '#ffd21f', pith: '#fff7d6', flesh0: '#fff6b0', flesh1: '#ffe14a', flesh2: '#ffcf1a' };
  const ORANGE = { rindDark: '#e06800', rind: '#ff8c1a', pith: '#fff1d8', flesh0: '#ffd59a', flesh1: '#ffa233', flesh2: '#ff8a10' };
  const LIME = { rindDark: '#3f8a17', rind: '#5ea82a', pith: '#f2ffd9', flesh0: '#eaffb0', flesh1: '#b9e35a', flesh2: '#93cc33' };
  function slice(c, s) {
    const face = new THREE.MeshPhysicalMaterial({ map: sliceTexture(c), roughness: .36, clearcoat: .8, clearcoatRoughness: .2, sheen: .4, sheenColor: new THREE.Color(0xffffff) });
    const side = new THREE.MeshStandardMaterial({ color: c.rind, roughness: .55 });
    const m = new THREE.Mesh(new THREE.CylinderGeometry(1, 1, .16, 96), [side, face, face]);
    m.scale.setScalar(s); return m;
  }
  const leafTex = leafTexture();
  function leaf(s) {
    const sh = new THREE.Shape();
    sh.moveTo(0, 0); sh.bezierCurveTo(.42, .25, .46, .9, 0, 1.3); sh.bezierCurveTo(-.46, .9, -.42, .25, 0, 0);
    const geo = new THREE.ShapeGeometry(sh, 40);
    const p = geo.attributes.position, uv = geo.attributes.uv;
    for (let i = 0; i < p.count; i++) {
      const x = p.getX(i), y = p.getY(i);
      p.setZ(i, x * x * .9 + Math.sin(y * 2.2) * .12);
      uv.setXY(i, (x + .46) / .92, y / 1.3);
    }
    geo.computeVertexNormals();
    const m = new THREE.Mesh(geo, new THREE.MeshStandardMaterial({ map: leafTex, roughness: .45, side: THREE.DoubleSide }));
    m.scale.setScalar(s); return m;
  }
  const crystalMat = new THREE.MeshPhysicalMaterial({ color: 0xffffff, roughness: .05, metalness: 0, clearcoat: 1, transparent: true, opacity: .9, iridescence: .5, flatShading: true });
  function crystal(s) { const m = new THREE.Mesh(new THREE.OctahedronGeometry(1, 0), crystalMat); m.scale.set(s, s * 1.5, s); return m; }

  const items = [
    { m: slice(LEMON, .82), rx: 2.6, rz: 2.2, y: 1.15, sp: .16, ph: 0 },
    { m: slice(ORANGE, .74), rx: 2.7, rz: 2.0, y: -1.2, sp: .14, ph: 2.3 },
    { m: slice(LEMON, .6), rx: 2.3, rz: 2.4, y: -.1, sp: .19, ph: 4.2 },
    { m: slice(LIME, .55), rx: 3.0, rz: 1.8, y: 1.9, sp: .12, ph: 3.4 },
    { m: leaf(1.05), rx: 2.0, rz: 1.6, y: 2.05, sp: .1, ph: 1.2 },
    { m: leaf(.9), rx: 2.2, rz: 1.7, y: -1.95, sp: .11, ph: 5.1 },
    { m: leaf(.75), rx: 2.8, rz: 1.5, y: .55, sp: .13, ph: 2.9 },
    { m: crystal(.2), rx: 1.9, rz: 2.0, y: .9, sp: .22, ph: .7 },
    { m: crystal(.15), rx: 2.5, rz: 1.6, y: -.6, sp: .2, ph: 3.9 },
    { m: crystal(.12), rx: 3.1, rz: 1.4, y: -1.8, sp: .17, ph: 5.8 },
    { m: crystal(.17), rx: 2.1, rz: 2.2, y: 2.4, sp: .18, ph: 2.0 },
  ];
  items.forEach((it, i) => {
    it.base = it.m.scale.clone(); it.m.scale.setScalar(.0001);
    it.rs = new THREE.Vector3(Math.random() - .5, Math.random() - .5, Math.random() - .5).normalize();
    it.delay = .5 + i * .09;
    it.m.rotation.set(Math.random() * 6, Math.random() * 6, Math.random() * 6);
    root.add(it.m);
  });

  // ---------- Kukun zarrachalari ----------
  const N = 260, pos = new Float32Array(N * 3), spd = new Float32Array(N);
  for (let i = 0; i < N; i++) { pos[i * 3] = (Math.random() - .5) * 9; pos[i * 3 + 1] = (Math.random() - .5) * 8; pos[i * 3 + 2] = (Math.random() - .5) * 5; spd[i] = .15 + Math.random() * .35; }
  const pg = new THREE.BufferGeometry(); pg.setAttribute('position', new THREE.BufferAttribute(pos, 3));
  const dot = canvasTex(64, 64, (g) => { const r = g.createRadialGradient(32, 32, 0, 32, 32, 32); r.addColorStop(0, 'rgba(255,255,255,1)'); r.addColorStop(1, 'rgba(255,255,255,0)'); g.fillStyle = r; g.fillRect(0, 0, 64, 64); });
  root.add(new THREE.Points(pg, new THREE.PointsMaterial({ size: .09, map: dot, transparent: true, opacity: .75, depthWrite: false, color: 0xfff3c4 })));

  // ---------- O'lcham va joylashuv ----------
  function resize() {
    const r = host.getBoundingClientRect(); const W = r.width, H = r.height;
    if (!W || !H) return;
    renderer.setSize(W, H, false);
    camera.aspect = W / H; camera.updateProjectionMatrix();
    const anchor = parseFloat(getComputedStyle(host).getPropertyValue('--anchor')) || .5;
    const visH = 2 * camera.position.z * Math.tan(THREE.MathUtils.degToRad(camera.fov / 2));
    const visW = visH * camera.aspect;
    root.scale.setScalar(Math.min(1, visW / 8.2, visH / 7.4));
    root.position.x = (anchor - .5) * visW;
  }
  resize();
  new ResizeObserver(resize).observe(host);

  let mx = 0, my = 0, cx = 0, cy = 0;
  host.closest('.hero').addEventListener('pointermove', (e) => { mx = e.clientX / innerWidth * 2 - 1; my = e.clientY / innerHeight * 2 - 1; });

  // ---------- Mahsulotlar almashinuvi: har 6 soniyada keyingisi ----------
  const HOLD = 6, OUT = .7;
  let cur = 0, next = -1, phaseT = 0;
  products[0].pivot.visible = true;
  const easeOutBack = (x) => { const c1 = 1.4, c3 = c1 + 1; return 1 + c3 * Math.pow(x - 1, 3) + c1 * Math.pow(x - 1, 2); };
  const easeIn = (x) => x * x * x;

  const clock = new THREE.Clock();
  let t = 0, raf = 0, visible = true, frames = 0, slow = 0;
  function frame() {
    const dt = Math.min(clock.getDelta(), .05); t += dt;
    // Kadr tezligi past bo'lsa aniqlik pasaytiriladi (silliqlik birinchi o'rinda)
    if (++frames > 20 && frames < 200) { slow = dt > .03 ? slow + 1 : Math.max(0, slow - 1); if (slow > 25 && dpr > 1.5) { dpr = Math.max(1.5, dpr - .5); renderer.setPixelRatio(dpr); resize(); slow = 0; } }

    phaseT += dt;
    if (next < 0 && phaseT > HOLD && !REDUCE) { next = (cur + 1) % products.length; phaseT = 0; }
    products.forEach((p, i) => {
      let k = 0;
      if (i === cur) k = next < 0 ? easeOutBack(Math.min(1, (i === 0 && t < 1.4 ? t / 1.4 : Math.min(1, phaseT / 1.1)))) : 1 - easeIn(Math.min(1, phaseT / OUT));
      p.pivot.visible = k > .001;
      if (!p.pivot.visible) return;
      p.pivot.scale.setScalar(Math.max(.001, k));
      p.pivot.position.y = Math.sin(t * .9) * .14 - (1 - Math.min(1, k)) * .8;
      p.pivot.rotation.z = p.tilt[2] + Math.sin(t * .5) * .05;
      const whirl = next >= 0 && i === cur ? dt * 6 * easeIn(Math.min(1, phaseT / OUT)) : 0;
      // Yassi mahsulotlar (qop, shisha) old tomoni ko'rinib chayqaladi, qolganlari aylanadi
      if (p.sway) { p.extra = (p.extra || 0) + whirl; p.g.rotation.y = Math.sin(t * .55) * p.sway + p.extra; }
      else p.g.rotation.y += dt * p.spin + whirl;
    });
    if (next >= 0 && phaseT >= OUT) { cur = next; next = -1; phaseT = 0; products[cur].extra = 0; if (!products[cur].sway) products[cur].g.rotation.y = -1.6; }

    for (const it of items) {
      const a = t * it.sp + it.ph;
      it.m.position.set(Math.cos(a) * it.rx, it.y + Math.sin(t * .7 + it.ph) * .22, Math.sin(a) * it.rz);
      it.m.rotateOnAxis(it.rs, dt * .55);
      const pr = Math.max(0, Math.min(1, (t - it.delay) / .9));
      it.m.scale.copy(it.base).multiplyScalar(Math.max(.0001, easeOutBack(pr)));
    }
    const arr = pg.attributes.position.array;
    for (let i = 0; i < N; i++) { arr[i * 3 + 1] += spd[i] * dt; if (arr[i * 3 + 1] > 4) arr[i * 3 + 1] = -4; }
    pg.attributes.position.needsUpdate = true;

    cx += (mx - cx) * .05; cy += (my - cy) * .05;
    root.rotation.y = cx * .28; root.rotation.x = cy * .14;
    renderer.render(scene, camera);
    if (!REDUCE && visible && !document.hidden) raf = requestAnimationFrame(frame);
  }
  function start() { cancelAnimationFrame(raf); clock.getDelta(); raf = requestAnimationFrame(frame); }
  if (REDUCE) { t = 3; phaseT = 3; frame(); host.classList.add('ready'); return; }
  new IntersectionObserver((en) => { visible = en[0].isIntersecting; if (visible) start(); else cancelAnimationFrame(raf); }).observe(host);
  document.addEventListener('visibilitychange', () => { if (!document.hidden && visible) start(); });
  host.classList.add('ready');
  start();
}
