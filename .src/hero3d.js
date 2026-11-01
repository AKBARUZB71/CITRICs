// Bosh ekran: real vaqtli 3D (Three.js). Shakllar aniq geometriya, yorliqlar va mevalar — fotorealistik teksturalar
// (ChatGPT'da yaratilgan). Mahsulotlar aylanib turadi va navbatma-navbat almashadi.
import * as THREE from 'three';
import { RoomEnvironment } from 'https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/environments/RoomEnvironment.js';

const stage = document.querySelector('.hx');
const REDUCE = matchMedia('(prefers-reduced-motion: reduce)').matches;
const MOBILE = matchMedia('(max-width: 900px)').matches;
const TEX = '/models/tex/';

function webglOk() {
  try { const c = document.createElement('canvas'); return !!(c.getContext('webgl2') || c.getContext('webgl')); } catch (e) { return false; }
}
if (stage && webglOk()) init();

const V = (x, y) => new THREE.Vector2(x, y);

function init() {
  const items = [...stage.querySelectorAll('.hx-item')];
  const dots = [...document.querySelectorAll('.hx-nav button')];
  const canvas = document.createElement('canvas');
  canvas.className = 'hx-gl';
  stage.querySelector('.hx-move').appendChild(canvas);

  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true, powerPreference: 'high-performance' });
  let dpr = Math.min(Math.max((devicePixelRatio || 1) * (MOBILE ? 1.5 : 2), 2), 3);
  renderer.setPixelRatio(dpr);
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.0;
  const ANISO = renderer.capabilities.getMaxAnisotropy();

  const scene = new THREE.Scene();
  const pmrem = new THREE.PMREMGenerator(renderer);
  scene.environment = pmrem.fromScene(new RoomEnvironment(), .04).texture;
  const camera = new THREE.PerspectiveCamera(28, 1, .1, 100);
  camera.position.set(0, .4, 14);
  camera.lookAt(0, 0, 0);
  const key = new THREE.DirectionalLight(0xffffff, 1.5); key.position.set(4, 6, 8); scene.add(key);
  const rim = new THREE.DirectionalLight(0xffd08a, 2.2); rim.position.set(-6, 4, -6); scene.add(rim);
  const fill = new THREE.DirectionalLight(0xffffff, .35); fill.position.set(-5, -3, 6); scene.add(fill);

  const root = new THREE.Group(); scene.add(root);
  const loader = new THREE.TextureLoader();
  const tex = (name, wrap) => new Promise((res) => loader.load(TEX + name, (t) => {
    t.colorSpace = THREE.SRGBColorSpace; t.anisotropy = ANISO;
    if (wrap) { t.wrapS = THREE.RepeatWrapping; }
    res(t);
  }, undefined, () => res(null)));

  const metal = new THREE.MeshStandardMaterial({ color: 0xe3e6ea, metalness: 1, roughness: .22, side: THREE.DoubleSide });
  const SEG = MOBILE ? 96 : 160;

  // ---------- Mahsulotlar ----------
  function makeCan(label) {
    const R = 1.0, H = 3.5, g = new THREE.Group();
    const m = new THREE.MeshPhysicalMaterial({ map: label, metalness: .45, roughness: .26, clearcoat: 1, clearcoatRoughness: .08 });
    g.add(new THREE.Mesh(new THREE.CylinderGeometry(R, R, H, SEG, 1, true), m));
    const top = new THREE.Mesh(new THREE.LatheGeometry([V(R, 0), V(R * .99, .08), V(R * .9, .28), V(R * .86, .36), V(R * .875, .42), V(R * .85, .45), V(R * .8, .41), V(R * .79, .35), V(0, .35)], SEG), metal);
    top.position.y = H / 2; g.add(top);
    const bottom = new THREE.Mesh(new THREE.LatheGeometry([V(0, -.18), V(R * .72, -.26), V(R * .86, -.24), V(R * .99, -.06), V(R, 0)], SEG), metal);
    bottom.position.y = -H / 2; g.add(bottom);
    const tab = new THREE.Mesh(new THREE.CapsuleGeometry(.12, .4, 8, 24), metal);
    tab.rotation.z = Math.PI / 2; tab.scale.set(1, 1, .18); tab.position.set(.16, H / 2 + .37, 0); g.add(tab);
    const rivet = new THREE.Mesh(new THREE.CylinderGeometry(.065, .065, .05, 32), metal); rivet.position.set(0, H / 2 + .37, 0); g.add(rivet);
    return { g, tilt: [.14, 0, -.22], spin: .5, h: 4.3 };
  }
  function makeSack(print) {
    const geo = new THREE.BoxGeometry(2.4, 3.3, .8, 64, 90, 18);
    const p = geo.attributes.position;
    for (let i = 0; i < p.count; i++) {
      const x = p.getX(i), y = p.getY(i), z = p.getZ(i), nx = x / 1.2, ny = y / 1.65;
      const puff = Math.sqrt(Math.max(0, 1 - Math.pow(Math.abs(nx), 6))) * Math.sqrt(Math.max(0, 1 - Math.pow(Math.abs(ny), 8)));
      p.setZ(i, (z / .4) * (.05 + .5 * puff));
      p.setX(i, x * (1 - .05 * (1 - ny * ny)) + Math.sin(y * 3.1) * .012);
      if (Math.abs(ny) > .97) p.setY(i, y + Math.sin(x * 14) * .015);
    }
    geo.computeVertexNormals();
    const front = new THREE.MeshPhysicalMaterial({ map: print, roughness: .78, sheen: .6, sheenRoughness: .8, sheenColor: new THREE.Color(0xffffff) });
    const side = new THREE.MeshStandardMaterial({ color: 0xf1efe9, roughness: .85 });
    const g = new THREE.Group(); g.add(new THREE.Mesh(geo, [side, side, side, side, front, front]));
    return { g, tilt: [.06, 0, -.1], sway: .55, h: 4.1 };
  }
  function makeBottle(label) {
    const g = new THREE.Group();
    const prof = [V(0, -1.75), V(.62, -1.75), V(.74, -1.68), V(.78, -1.5), V(.78, .55), V(.74, .8), V(.58, 1.05), V(.38, 1.25), V(.32, 1.42), V(.32, 1.62), V(.36, 1.66), V(.36, 1.72), V(.3, 1.74)];
    g.add(new THREE.Mesh(new THREE.LatheGeometry([V(0, -1.66), V(.68, -1.66), V(.72, -1.5), V(.72, .55), V(.68, .78), V(.53, 1.03), V(.34, 1.23), V(.28, 1.4), V(0, 1.4)], SEG),
      new THREE.MeshPhysicalMaterial({ color: 0xc8101c, roughness: .1, clearcoat: 1, emissive: 0x2a0004 })));
    const glass = new THREE.MeshPhysicalMaterial({ color: 0xffffff, roughness: .03, clearcoat: 1, clearcoatRoughness: .02, transparent: true, opacity: .16, depthWrite: false, envMapIntensity: 1.8 });
    const shell = new THREE.Mesh(new THREE.LatheGeometry(prof, SEG), glass); shell.renderOrder = 2; g.add(shell);
    const lab = new THREE.Mesh(new THREE.CylinderGeometry(.792, .792, 1.6, SEG, 1, true, -Math.PI / 2, Math.PI),
      new THREE.MeshPhysicalMaterial({ map: label, roughness: .4, clearcoat: .5 }));
    lab.position.y = -.55; g.add(lab);
    const cap = new THREE.Mesh(new THREE.CylinderGeometry(.42, .42, .5, SEG), new THREE.MeshPhysicalMaterial({ color: 0x5fa82a, roughness: .3, clearcoat: 1 }));
    cap.position.y = 1.86; g.add(cap);
    return { g, tilt: [.1, 0, .16], sway: .75, h: 4.2 };
  }
  function makeJar(label) {
    const g = new THREE.Group();
    g.add(new THREE.Mesh(new THREE.LatheGeometry([V(0, -1.3), V(.9, -1.3), V(1.02, -1.22), V(1.06, -1.05), V(1.06, .85), V(1.0, .95), V(.92, .98), V(.92, 1.08)], SEG),
      new THREE.MeshPhysicalMaterial({ color: 0xfbfaf6, roughness: .35, clearcoat: .6 })));
    const lab = new THREE.Mesh(new THREE.CylinderGeometry(1.068, 1.068, 1.55, SEG, 1, true, -Math.PI * .36, Math.PI * .72),
      new THREE.MeshPhysicalMaterial({ map: label, roughness: .38, clearcoat: .5 }));
    lab.position.y = -.17; g.add(lab);
    const lid = new THREE.Mesh(new THREE.CylinderGeometry(1.1, 1.1, .6, SEG), new THREE.MeshPhysicalMaterial({ color: 0x62aa2b, roughness: .25, clearcoat: 1, clearcoatRoughness: .1 }));
    lid.position.y = 1.32; g.add(lid);
    return { g, tilt: [.2, 0, -.14], sway: .7, h: 3.9 };
  }

  // ---------- Mevalar va barglar (foto teksturali) ----------
  function slice(t, rind, s) {
    const face = new THREE.MeshPhysicalMaterial({ map: t, emissiveMap: t, emissive: 0xffffff, emissiveIntensity: .32, transparent: true, alphaTest: .4, roughness: .35, clearcoat: .7, clearcoatRoughness: .2 });
    const side = new THREE.MeshStandardMaterial({ color: rind, roughness: .5 });
    const m = new THREE.Mesh(new THREE.CylinderGeometry(1, 1, .13, 96), [side, face, face]);
    m.scale.setScalar(s); return m;
  }
  function leaf(t, s) {
    const geo = new THREE.PlaneGeometry(1, 1.4, 24, 24), p = geo.attributes.position;
    for (let i = 0; i < p.count; i++) { const x = p.getX(i), y = p.getY(i); p.setZ(i, x * x * .5 + Math.sin(y * 2.2) * .08); }
    geo.computeVertexNormals();
    const m = new THREE.Mesh(geo, new THREE.MeshStandardMaterial({ map: t, transparent: true, alphaTest: .45, roughness: .45, side: THREE.DoubleSide }));
    m.scale.setScalar(s); return m;
  }

  // ---------- Yumshoq soya ----------
  const shTex = (() => {
    const c = document.createElement('canvas'); c.width = c.height = 256; const g = c.getContext('2d');
    const r = g.createRadialGradient(128, 128, 0, 128, 128, 128);
    r.addColorStop(0, 'rgba(90,40,0,.55)'); r.addColorStop(1, 'rgba(90,40,0,0)');
    g.fillStyle = r; g.fillRect(0, 0, 256, 256);
    return new THREE.CanvasTexture(c);
  })();
  const shadow = new THREE.Mesh(new THREE.PlaneGeometry(4.2, 1.1), new THREE.MeshBasicMaterial({ map: shTex, transparent: true, depthWrite: false }));
  shadow.rotation.x = -Math.PI / 2; shadow.position.y = -2.55; root.add(shadow);

  function resize() {
    const r = stage.getBoundingClientRect(); const W = r.width, H = r.height;
    if (!W || !H) return;
    renderer.setSize(W, H, false);
    camera.aspect = W / H; camera.updateProjectionMatrix();
    const anchor = (parseFloat(getComputedStyle(stage).getPropertyValue('--anchor')) || 50) / 100;
    const visH = 2 * camera.position.z * Math.tan(THREE.MathUtils.degToRad(camera.fov / 2));
    const visW = visH * camera.aspect;
    root.scale.setScalar(Math.min(1, visW / 7.2, visH / 6.9));
    root.position.x = (anchor - .5) * visW;
  }

  Promise.all([tex('can.webp', true), tex('sack.webp'), tex('bottle.webp'), tex('jar.webp'), tex('lemon.webp'), tex('orange.webp'), tex('mint.webp')]).then(([tCan, tSack, tBottle, tJar, tLemon, tOrange, tMint]) => {
    if (!tCan) return; // teksturalar bo'lmasa — rasm ko'rinishi qoladi
    const makers = [() => makeCan(tCan), () => tSack && makeSack(tSack), () => tBottle && makeBottle(tBottle), () => tJar && makeJar(tJar)];
    const products = makers.map((f) => f()).map((p) => {
      if (!p) return null;
      p.pivot = new THREE.Group(); p.pivot.add(p.g); p.pivot.rotation.set(p.tilt[0], 0, p.tilt[2]);
      p.pivot.visible = false; root.add(p.pivot);
      return p;
    });

    const deco = [];
    const add = (m, rx, rz, y, sp, ph) => { m.rotation.set(Math.random() * 6, Math.random() * 6, Math.random() * 6); m.scale.multiplyScalar(1); deco.push({ m, rx, rz, y, sp, ph, base: m.scale.clone(), rs: new THREE.Vector3(Math.random() - .5, Math.random() - .5, Math.random() - .5).normalize() }); root.add(m); };
    if (tLemon) { add(slice(tLemon, 0xf7c600, .7), 2.5, 2.1, 1.3, .15, 0); add(slice(tLemon, 0xf7c600, .55), 2.3, 2.3, -1.3, .18, 3.9); }
    if (tOrange) { add(slice(tOrange, 0xf28a00, .66), 2.6, 2.0, -.2, .13, 2.2); }
    if (tMint) { add(leaf(tMint, 1.0), 2.0, 1.7, 2.1, .1, 1.2); add(leaf(tMint, .85), 2.2, 1.8, -2.0, .11, 5.0); add(leaf(tMint, .7), 2.7, 1.5, .6, .12, 2.9); }

    resize();
    new ResizeObserver(resize).observe(stage);
    stage.classList.add('gl');
    window.CITRIC_HERO_GL = true;

    let cur = 0, prev = -1, phaseT = 0, timer = 0, t = 0, raf = 0, visible = true, mx = 0, my = 0, cx = 0, cy = 0;
    const DUR = 6500;
    const easeOutBack = (x) => { const c1 = 1.3, c3 = c1 + 1; return 1 + c3 * Math.pow(x - 1, 3) + c1 * Math.pow(x - 1, 2); };
    function go(n) {
      n = (n + products.length) % products.length;
      let guard = 0; while (!products[n] && guard++ < 4) n = (n + 1) % products.length;
      if (n === cur || !products[n]) return;
      prev = cur; cur = n; phaseT = 0;
      if (products[cur].spin) products[cur].g.rotation.y = -1.2; products[cur].extra = 0;
      dots.forEach((d, i) => d.classList.toggle('on', i === cur));
      plan();
    }
    function plan() { clearTimeout(timer); if (!REDUCE && !document.hidden) timer = setTimeout(() => go(cur + 1), DUR); }
    dots.forEach((d, i) => d.addEventListener('click', () => go(i)));
    window.CITRIC_HERO = { go, get cur() { return cur; } };
    stage.closest('.hero').addEventListener('pointermove', (e) => { mx = e.clientX / innerWidth * 2 - 1; my = e.clientY / innerHeight * 2 - 1; });

    const clock = new THREE.Clock();
    function frame() {
      const dt = Math.min(clock.getDelta(), .05); t += dt; phaseT += dt;
      let shadowK = 0;
      products.forEach((p, i) => {
        if (!p) return;
        let k = 0;
        if (i === cur) k = easeOutBack(Math.min(1, phaseT / 1.1));
        else if (i === prev) k = Math.max(0, 1 - Math.pow(Math.min(1, phaseT / .6), 2));
        p.pivot.visible = k > .001;
        if (!p.pivot.visible) return;
        if (i === cur) shadowK = Math.min(1, k);
        const s = Math.max(.001, k) * 4.3 / p.h;
        p.pivot.scale.setScalar(s);
        p.pivot.position.y = Math.sin(t * .9) * .12 + .1 - (1 - Math.min(1, k)) * .6;
        p.pivot.rotation.z = p.tilt[2] + Math.sin(t * .5) * .035;
        const whirl = i === prev ? dt * 5 : 0;
        if (p.sway) { p.extra = (p.extra || 0) + whirl; p.g.rotation.y = Math.sin(t * .55) * p.sway + p.extra; }
        else p.g.rotation.y += dt * (REDUCE ? 0 : p.spin) + whirl;
      });
      shadow.material.opacity = shadowK * (.75 - Math.sin(t * .9) * .15);
      shadow.scale.setScalar(1 - Math.sin(t * .9) * .06);
      for (const d of deco) {
        const a = t * d.sp + d.ph;
        d.m.position.set(Math.cos(a) * d.rx, d.y + Math.sin(t * .7 + d.ph) * .2, Math.sin(a) * d.rz);
        d.m.rotateOnAxis(d.rs, dt * .45);
        const pr = Math.max(0, Math.min(1, (t - .4) / 1));
        d.m.scale.copy(d.base).multiplyScalar(Math.max(.0001, easeOutBack(pr)));
      }
      cx += (mx - cx) * .05; cy += (my - cy) * .05;
      root.rotation.y = cx * .25; root.rotation.x = cy * .1;
      renderer.render(scene, camera);
      if (visible && !document.hidden) raf = requestAnimationFrame(frame);
    }
    function start() { cancelAnimationFrame(raf); clock.getDelta(); raf = requestAnimationFrame(frame); }
    if (REDUCE) { t = 2; phaseT = 2; frame(); return; }
    new IntersectionObserver((en) => { visible = en[0].isIntersecting; if (visible) start(); else cancelAnimationFrame(raf); }).observe(stage);
    document.addEventListener('visibilitychange', () => { plan(); if (!document.hidden && visible) start(); });
    plan();
    start();
  });
}
