import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';

const url = process.env.JOBSIMU_URL || 'http://127.0.0.1:8765';
const output = path.resolve('results');
await fs.mkdir(output, { recursive: true });
const browser = await chromium.launch({ executablePath: process.env.JOBSIMU_CHROME || '/usr/bin/google-chrome', headless: true,
  args: ['--no-sandbox', '--enable-webgl', '--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader', '--disable-dev-shm-usage'] });
const context = await browser.newContext({ viewport: { width: 1280, height: 720 }, deviceScaleFactor: 1 });
const page = await context.newPage();
const errors = [], snapshots = [], checks = [];
page.on('pageerror', error => errors.push(error.message));
page.on('console', message => {
  const text = message.text();
  if (text.startsWith('JOBSIMU_SNAPSHOT ')) {
    try { snapshots.push(JSON.parse(text.slice('JOBSIMU_SNAPSHOT '.length))); } catch {}
  }
  if (/Exception:|Error:|Assertion failed|Shader error/.test(text)) errors.push(text);
});
let serial = 0;
async function snapshot() {
  const tag = `probe-${serial++}`;
  await page.evaluate(tag => window.jobSimu.SendMessage('Shared Simulation', 'Dump', tag), tag);
  await page.waitForFunction(() => true);
  for (let i = 0; i < 100; i++) {
    const value = snapshots.find(s => s.tag === tag);
    if (value) return value;
    await page.waitForTimeout(50);
  }
  throw new Error(`Development-build probe did not answer ${tag}`);
}
async function load() {
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForFunction(() => window.jobSimu || window.jobSimuLoadError, { timeout: 240000 });
  assert.equal(await page.evaluate(() => window.jobSimuLoadError || ''), '');
  await page.waitForTimeout(2000);
}
async function click(x, y) { await page.mouse.click(x, y); await page.waitForTimeout(250); }
async function key(key, ms = 100) { await page.keyboard.down(key); await page.waitForTimeout(ms); await page.keyboard.up(key); await page.waitForTimeout(120); }
async function lock() { const state = await snapshot(); if (!state.locked) await click(640, 360); }
const signed = angle => ((angle + 540) % 360) - 180;
async function aim(target) {
  await lock();
  for (let pass = 0; pass < 6; pass++) {
    const state = await snapshot();
    const dx = target.x - state.camera.x, dy = target.y - state.camera.y, dz = target.z - state.camera.z;
    const yaw = Math.atan2(dx, dz) * 180 / Math.PI;
    const pitch = -Math.atan2(dy, Math.hypot(dx, dz)) * 180 / Math.PI;
    const yawDelta = signed(yaw - state.angles.y), pitchDelta = signed(pitch - state.angles.x);
    if (Math.abs(yawDelta) < 1 && Math.abs(pitchDelta) < 1) return;
    // Real pointer-lock mouse movement, not injected game state.
    await page.mouse.move(640 + yawDelta / .09, 360 + pitchDelta / .09);
    await page.waitForTimeout(120);
  }
}
async function walk(x, z) {
  let stalled = 0, previous = Infinity;
  for (let attempt = 0; attempt < 65; attempt++) {
    const state = await snapshot();
    const distance = Math.hypot(x - state.player.x, z - state.player.z);
    if (distance < .3) return;
    if (Math.abs(previous - distance) < .035) stalled++; else stalled = 0;
    assert.ok(stalled < 8, `Path blocked toward ${x},${z}: ${JSON.stringify(state.player)}`);
    previous = distance;
    await aim({ x, y: state.camera.y, z });
    await key('w', Math.max(40, Math.min(450, distance / 3.4 * 850)));
  }
  throw new Error(`Could not walk to ${x},${z}`);
}
async function interact(id, expectedStep) {
  let state = await snapshot();
  const station = state.stations.find(s => s.id === id);
  assert.ok(station, id);
  await aim(station.position); await key('e');
  state = await snapshot();
  assert.equal(state.station, id, `Raycast did not reach ${id}`);
  assert.equal(state.page, 'Dialogue');
  await click(630, 350);
  state = await snapshot();
  assert.equal(state.step, expectedStep);
  if (!state.complete) { await click(630, 620); await lock(); }
  checks.push(`world action ${id} -> ${expectedStep}`);
}
async function door(x, z) { await aim({ x, y: 1.2, z }); await key('e'); await page.waitForTimeout(1300); }

let outcome = 'failed';
try {
  await load();
  assert.equal((await snapshot()).page, 'MainMenu');
  await page.screenshot({ path: path.join(output, '01-main-menu.png') });
  await click(620, 325); await lock();
  const before = await snapshot(); await key('w', 400); const moved = await snapshot();
  assert.ok(moved.player.z > before.player.z + .5, 'WASD movement did not advance'); checks.push('actual keyboard movement');
  await key('Escape'); const paused = await snapshot(); assert.equal(paused.page, 'Pause');
  await key('w', 350); const still = await snapshot(); assert.ok(Math.hypot(still.player.x - paused.player.x, still.player.z - paused.player.z) < .03); checks.push('pause blocks movement');
  await click(600, 265); await lock();
  await walk(-3, -11); await interact('mai', 'prepare');
  await walk(0, -7); await walk(0, 5.5); await walk(8.2, 5.5);
  await door(8.2, 7.72); await walk(8.2, 9.2); await walk(9.1, 10.8); await interact('printer', 'begin');
  await walk(8.2, 9.2); await walk(8.2, 5.5); await walk(-15.8, 5.5);
  await door(-15.8, 7.72); await walk(-15.8, 16.3); await walk(-12, 16.3); await interact('board', 'teach');
  await walk(-12.6, 16.3); await walk(-12.6, 13.3); await interact('linh', 'assess');
  await walk(-12.6, 16.3); await walk(-15, 16); await interact('tickets', 'reflect');
  await walk(-15.8, 16); await walk(-15.8, 5.5); await walk(0, 5.5); await walk(0, -7); await walk(-3, -10);
  await interact('mai', 'complete');
  assert.equal((await snapshot()).page, 'Results');
  await page.screenshot({ path: path.join(output, '02-results.png') });
  await click(400, 530); await page.keyboard.type('Lan sau toi se kiem tra tung nhom.'); await click(400, 607);
  await page.waitForTimeout(3000); const completed = await snapshot(); assert.ok(completed.reflectionLength > 10);
  await page.reload({ waitUntil: 'domcontentloaded' }); await page.waitForFunction(() => window.jobSimu, { timeout: 240000 }); await page.waitForTimeout(1500);
  await click(620, 383); const resumed = await snapshot(); assert.equal(resumed.complete, true); assert.equal(resumed.reflectionLength, completed.reflectionLength);
  checks.push('IndexedDB save survives browser reload');
  assert.deepEqual(errors, []);
  outcome = 'passed';
} finally {
  const report = { outcome, url, checks, errors, snapshots, browser: await browser.version(), rendering: 'headless Chrome / SwiftShader; not target hardware FPS' };
  await fs.writeFile(path.join(output, 'report.json'), JSON.stringify(report, null, 2));
  await page.screenshot({ path: path.join(output, 'last-frame.png') }).catch(() => {});
  await browser.close();
  console.log(JSON.stringify({ outcome, checks, errors, report: path.join(output, 'report.json') }, null, 2));
}
