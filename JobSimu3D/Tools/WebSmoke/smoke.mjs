import { chromium } from 'playwright';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import path from 'node:path';

const url = process.env.JOBSIMU_URL || 'http://127.0.0.1:8765';
const output = path.resolve('results');
await fs.mkdir(output, { recursive: true });
const browser = await chromium.launch({
  executablePath: process.env.JOBSIMU_CHROME || '/usr/bin/google-chrome',
  headless: true,
  args: ['--no-sandbox', '--enable-webgl', '--use-gl=angle', '--use-angle=swiftshader', '--enable-unsafe-swiftshader', '--disable-dev-shm-usage']
});
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
let mouseX = 0, mouseY = 0;
async function snapshot() {
  const tag = `probe-${serial++}`;
  await page.evaluate(tag => window.jobSimu.SendMessage('Shared Simulation', 'Dump', tag), tag);
  await page.waitForFunction(() => true);
  for (let i = 0; i < 120; i++) {
    const value = snapshots.find(s => s.tag === tag);
    if (value) return value;
    await page.waitForTimeout(50);
  }
  throw new Error(`Development-build probe did not answer ${tag}`);
}

async function load() {
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await page.waitForFunction(() => window.jobSimu || window.jobSimuLoadError, null, { timeout: 240000 });
  assert.equal(await page.evaluate(() => window.jobSimuLoadError || ''), '');
  await waitReady();
}

async function waitReady() {
  for (let attempt = 0; attempt < 120; attempt++) {
    if ((await snapshot()).ready) return;
    await page.waitForTimeout(250);
  }
  throw new Error('Unity splash did not finish');
}

async function click(x, y) { await page.mouse.click(x, y); mouseX = x; mouseY = y; await page.waitForTimeout(260); }
async function key(k, ms = 100) { await page.keyboard.down(k); await page.waitForTimeout(ms); await page.keyboard.up(k); await page.waitForTimeout(120); }
async function lock() { const state = await snapshot(); if (!state.locked) await click(640, 360); }
const signed = angle => ((angle + 540) % 360) - 180;

async function aim(target) {
  await lock();
  for (let pass = 0; pass < 7; pass++) {
    const state = await snapshot();
    const dx = target.x - state.camera.x, dy = target.y - state.camera.y, dz = target.z - state.camera.z;
    const yaw = Math.atan2(dx, dz) * 180 / Math.PI;
    const pitch = -Math.atan2(dy, Math.hypot(dx, dz)) * 180 / Math.PI;
    const yawDelta = signed(yaw - state.angles.y), pitchDelta = signed(pitch - state.angles.x);
    if (Math.abs(yawDelta) < 1.2 && Math.abs(pitchDelta) < 1.2) return;
    mouseX += yawDelta / 0.09;
    mouseY += pitchDelta / 0.09;
    await page.mouse.move(mouseX, mouseY);
    await page.waitForTimeout(120);
  }
}

async function walk(x, z) {
  let stalled = 0, previous = Infinity;
  for (let attempt = 0; attempt < 70; attempt++) {
    const state = await snapshot();
    const distance = Math.hypot(x - state.player.x, z - state.player.z);
    if (distance < 0.32) return;
    if (Math.abs(previous - distance) < 0.035) stalled++; else stalled = 0;
    assert.ok(stalled < 8, `Path blocked toward ${x},${z}: ${JSON.stringify(state.player)}`);
    previous = distance;
    await aim({ x, y: state.camera.y, z });
    await key('w', Math.max(40, Math.min(450, distance / 3.4 * 850)));
  }
  throw new Error(`Could not walk to ${x},${z}`);
}

async function interact(id, actionIds, expectedPhaseAfter = null, screenshotName = null) {
  let state = await snapshot();
  const station = state.stations.find(s => s.id === id);
  assert.ok(station, `Missing station ${id}`);
  await aim(station.position);
  await key('e');
  state = await snapshot();
  assert.equal(state.station, id, `Raycast did not reach ${id}`);
  assert.equal(state.page, 'Dialogue');
  if (screenshotName) await page.screenshot({ path: path.join(output, screenshotName) });

  for (const actionId of actionIds) {
    state = await snapshot();
    const index = state.stationActions.indexOf(actionId);
    assert.ok(index >= 0 && index < 4, `Action ${actionId} not in top 4 visible buttons at ${id}: ${JSON.stringify(state.stationActions)}`);
    await click(460, 391 + index * 64);
  }

  state = await snapshot();
  if (expectedPhaseAfter) assert.equal(state.phase, expectedPhaseAfter);
  if (!state.complete && state.page === 'Dialogue') {
    await click(640, 656); // Close [Esc] button
    await lock();
  }
  checks.push(`world action ${id} (${actionIds.join(', ')}) -> phase=${state.phase}`);
  console.log(checks.at(-1));
}

async function door(x, z) {
  await aim({ x, y: 1.2, z });
  await key('e');
  await page.waitForTimeout(1300);
}

let outcome = 'failed', failure = '';
try {
  await load();
  assert.equal((await snapshot()).page, 'MainMenu');
  await page.screenshot({ path: path.join(output, '01-main-menu.png') });

  // Start new game (first button)
  await click(640, 319);
  await lock();

  // Verify real WASD movement & Pause/Planner clock stop
  const before = await snapshot();
  await key('w', 400);
  const moved = await snapshot();
  assert.ok(moved.player.z > before.player.z + 0.5, 'WASD movement did not advance');
  checks.push('actual keyboard movement');

  await key('Tab');
  assert.equal((await snapshot()).page, 'Planner');
  await key('Tab');
  await key('Escape');
  const paused = await snapshot();
  assert.equal(paused.page, 'Pause');
  await key('w', 300);
  const still = await snapshot();
  assert.ok(Math.hypot(still.player.x - paused.player.x, still.player.z - paused.player.z) < 0.03);
  checks.push('pause & planner block movement and clock');
  await click(640, 249); // Resume button
  await lock();

  // Phase A: Courtyard Puddle + Cô Mai
  await walk(2.5, -10.5);
  await interact('yard_puddle', ['yard.inspect_puddle']);
  await walk(-1.6, -9.0);
  await interact('mai', ['brief.ask_class', 'brief.accept'], 'Preparation');

  // Phase B: Staff Room (combine reading previous papers + printing + group cards!)
  await walk(0, -7);
  await walk(0, 5.5);
  await walk(8.2, 5.5);
  await door(8.2, 7.72);
  await walk(8.2, 9.2);
  await walk(10.2, 9.2);
  await walk(10.2, 12.2);
  await interact('prev_work', ['prep.read_prev_work']);
  await walk(10.2, 14.5);
  await interact('printer', ['prep.print'], null, '02-preparation.png');
  await walk(14.5, 12.5);
  await interact('group-kit', ['prep.group_cards']);
  await walk(10.2, 12.2);
  await walk(8.4, 10.4);
  await interact('staff_exit', ['prep.enter_class'], 'Opening');

  // Phase C: Corridor -> Class 8A -> Opening on board
  await walk(8.2, 9.2);
  await walk(8.2, 5.5);
  await walk(-15.8, 5.5);
  await door(-15.8, 7.72);
  await walk(-15.8, 8.85);
  await walk(-16.7, 8.85);
  await walk(-16.7, 11.86);
  await walk(-12.0, 11.86);
  await walk(-12.0, 15.2);
  await interact('board', ['open.clear_task', 'open.check_pair_prompt', 'open.start_group_work'], 'Facilitating', '03-classroom-opening.png');

  // Phase D: Facilitate multiple groups (Linh + Minh + Nam + Recheck Minh!)
  await walk(-14.5, 15.1);
  await interact('group1_sheet', ['g1.inspect_sheet']);
  await walk(-12.0, 15.1);
  await walk(-12.0, 11.86);
  await walk(-14.5, 11.86);
  await interact('linh', ['g1.ask_private']);

  await walk(-9.5, 11.86);
  await interact('nam', ['g2.ask_reasoning', 'g2.design_test']);

  await walk(-7.3, 11.86);
  await walk(-7.3, 8.85);
  await walk(-9.5, 8.85);
  await interact('minh', ['g4.clarify_cause', 'g4.scaffold_steps', 'g4.recheck_new_example'], null, '04-group4-minh.png');

  await walk(-7.3, 8.85);
  await walk(-7.3, 11.86);
  await walk(-12.0, 11.86);
  await walk(-12.0, 15.2);
  await interact('board', ['teach.to_check'], 'CheckingLearning');

  // Phase E: Collect all 4 group sheets & end lesson
  await walk(-14.8, 15.2);
  await interact('tickets', ['assess.collect_sheets']);
  await walk(-12.0, 15.2);
  await interact('board', ['assess.end_lesson'], 'Reflection');

  // Phase F: Walk back to Cô Mai, open Reflection, submit, verify Results & reload persistence!
  await walk(-12.0, 11.86);
  await walk(-16.7, 11.86);
  await walk(-16.7, 8.85);
  await walk(-15.8, 8.85);
  await walk(-15.8, 5.5);
  await walk(0, 5.5);
  await walk(0, -7);
  await walk(-1.6, -9.0);
  await aim((await snapshot()).stations.find(s => s.id === 'mai').position);
  await key('e');
  assert.equal((await snapshot()).page, 'Reflection');
  await page.screenshot({ path: path.join(output, '05-reflection.png') });

  // Click submit reflection button (396, 656)
  await click(396, 656);
  const finished = await snapshot();
  assert.equal(finished.complete, true);
  assert.equal(finished.page, 'Results');
  await page.screenshot({ path: path.join(output, '06-results.png') });

  // Save explicitly and reload browser to verify IndexedDB save recovery
  await click(291, 622);
  await page.waitForTimeout(2200);
  await page.reload({ waitUntil: 'domcontentloaded' });
  await page.waitForFunction(() => window.jobSimu, null, { timeout: 240000 });
  await waitReady();
  await click(640, 379); // Continue saved session
  const resumed = await snapshot();
  assert.equal(resumed.complete, true);
  assert.equal(resumed.page, 'Results');
  assert.deepEqual(resumed.flags, finished.flags);
  assert.equal(resumed.evidence, finished.evidence);
  checks.push('IndexedDB v2 save survives browser reload with exact flags & evidence');
  assert.deepEqual(errors, []);
  outcome = 'passed';
} catch (error) {
  failure = error.stack;
  process.exitCode = 1;
} finally {
  const report = {
    outcome,
    failure,
    url,
    checks,
    errors,
    snapshotsCount: snapshots.length,
    lastSnapshot: snapshots.at(-1),
    browser: await browser.version(),
    rendering: 'headless Chrome / SwiftShader; not target hardware FPS'
  };
  await fs.writeFile(path.join(output, 'report.json'), JSON.stringify(report, null, 2));
  await page.screenshot({ path: path.join(output, 'last-frame.png') }).catch(() => {});
  await browser.close();
  console.log(JSON.stringify({ outcome, failure, checks, errors, report: path.join(output, 'report.json') }, null, 2));
}
