/* The staff dashboard in Chromium, with the staff REST routes mocked.
 * Run: php tests/e2e/build-dashboard.php && node tests/e2e/dashboard-e2e.js */
const { chromium } = require('playwright');
const { spawn } = require('child_process');
const assert = require('assert');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const PORT = 8128;
const URL = `http://127.0.0.1:${PORT}/tests/e2e/out/dashboard.html`;
const SHOTS = process.env.PAW_SHOTS || '';
let passed = 0;
function ok(name, cond) { assert.ok(cond, name); passed++; console.log('ok   - ' + name); }

const nights = Array.from({ length: 14 }, (_, i) => ({ date: new Date(Date.UTC(2026, 9, 25 + i)).toISOString().slice(0, 10), count: [3, 4, 4, 2, 5, 6, 6, 4, 3, 2, 2, 1, 0, 1][i] }));
const STATS = { check_ins: 2, check_outs: 1, boarding: 3, grooming: 5, pending: 4, nights, today: '2026-10-25' };
const ROWS = [
  { id: 7, reference: 'PAW-7K3QX9', pet_name: 'Biscuit', pet_type: 'Dog', owner: "Zoë O'Brien", email: 'zoe@example.com', phone: '555', check_in: '2026-10-28', check_out: '2026-10-30', stay: '10/28/2026 → 10/30/2026', nights: 2, status: 'pending', status_label: 'Pending', created_at: '10/25/2026 9:00 am' },
  { id: 8, reference: 'PAW-ABCDEF', pet_name: '<b>Luna</b>', pet_type: 'Cat', owner: 'Ada Lovelace', email: 'ada@example.com', phone: '555', check_in: '2026-11-02', check_out: '2026-11-05', stay: '11/02/2026 → 11/05/2026', nights: 3, status: 'confirmed', status_label: 'Confirmed', created_at: '10/24/2026 3:12 pm' },
  { id: 9, reference: 'PAW-GR00M1', pet_name: 'Rex', pet_type: 'Dog', owner: 'Ada Lovelace', email: 'ada@example.com', phone: '555', check_in: '2026-10-27', check_out: '2026-10-27', stay: '10/27/2026 · Morning (8–12)', nights: 0, booking_type: 'grooming', service_name: 'Nail Trimming', status: 'pending', status_label: 'Pending', created_at: '10/25/2026 9:30 am' },
];
const DETAIL = {
  id: 7, reference: 'PAW-7K3QX9', pet_name: 'Biscuit', owner: "Zoë O'Brien", stay: '10/28/2026 → 10/30/2026', nights: 2, status: 'pending', status_label: 'Pending', staff_notes: '',
  answers_html: '<section class="paw-card"><h2>Pet Information</h2><dl class="paw-answers"><dt>Breed</dt><dd>Beagle</dd></dl></section>',
  files: [{ name: 'rabies.pdf', size: 2048, url: '/wp-admin/admin-post.php?action=paw_file&id=7&n=0' }],
  signature_url: '/wp-admin/admin-post.php?action=paw_file&id=7&n=sig',
  history: [{ status: 'pending', label: 'Pending', at: '10/25/2026 9:00 am', by: 'Submitted by the owner' }], admin_url: '/wp-admin/admin.php?page=paw-reservations&view=7',
};

async function mock(page, log) {
  await page.route('**/mock/staff/stats', r => r.fulfill({ json: STATS }));
  await page.route(/\/mock\/staff\/reservations(\?.*)?$/, r => { log.list.push(r.request().url()); return r.fulfill({ json: { rows: ROWS, total: 2, pages: 1, counts: { pending: 1, confirmed: 1, declined: 0, checked_in: 0, completed: 0, cancelled: 0 } } }); });
  await page.route('**/mock/staff/reservations/7', r => { log.headers.push(r.request().headers()); return r.fulfill({ json: DETAIL }); });
  await page.route('**/mock/staff/reservations/7/status', r => { log.status.push(r.request().postDataJSON()); return r.fulfill({ json: { status: 'confirmed', status_label: 'Confirmed', history: [{ status: 'confirmed', label: 'Confirmed', at: '10/25/2026 10:00 am', by: 'Dana Desk' }, DETAIL.history[0]] } }); });
  await page.route('**/mock/staff/reservations/7/notes', r => { log.notes.push(r.request().postDataJSON()); return r.fulfill({ json: { saved: true } }); });
}

(async () => {
  const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', ROOT], { stdio: 'ignore' });
  await new Promise(r => setTimeout(r, 600));
  const browser = await chromium.launch();
  try {
    const page = await browser.newPage({ viewport: { width: 1280, height: 860 } });
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    const log = { list: [], headers: [], status: [], notes: [] };
    await mock(page, log);
    await page.goto(URL);

    await page.waitForSelector('[data-paw-tile="pending"] .paw-dash__tile-num');
    ok('overview: grooming today tile', (await page.textContent('[data-paw-tile="grooming"] .paw-dash__tile-num')).trim() === '5');
    ok('overview: four tiles with the numbers', (await page.textContent('[data-paw-tile="check_ins"] .paw-dash__tile-num')).trim() === '2' && (await page.textContent('[data-paw-tile="boarding"] .paw-dash__tile-num')).trim() === '3' && (await page.textContent('[data-paw-tile="pending"] .paw-dash__tile-num')).trim() === '4');
    ok('overview: 14 bars', (await page.locator('.paw-dash__bar').count()) === 14);
    ok('overview: every bar has a readable day label', !(await page.textContent('[data-paw-chart]')).includes('Invalid'));
    await page.waitForTimeout(500); // Bars grow in.
    ok('overview: tallest bar is the busiest night', await page.evaluate(() => { const b = [...document.querySelectorAll('.paw-dash__bar-fill')].map(e => e.getBoundingClientRect().height); return b[5] === Math.max(...b) && b[12] <= 2; }));
    ok('overview is the current menu item', (await page.getAttribute('[data-paw-nav="overview"]', 'aria-current')) === 'page');
    if (SHOTS) await page.screenshot({ path: path.join(SHOTS, 'dash-overview.png') });

    await page.click('[data-paw-nav="reservations"]');
    await page.waitForSelector('.paw-dash__table tbody tr');
    ok('reservations: rows listed', (await page.locator('.paw-dash__table tbody tr').count()) === 3);
    ok('reservations: a grooming row shows its day and service', (await page.textContent('.paw-dash__table tbody tr[data-id="9"]')).includes('10/27/2026 · Morning (8–12)') && (await page.textContent('.paw-dash__table tbody tr[data-id="9"]')).includes('Nail Trimming'));
    ok('reservations: text is not parsed as HTML', (await page.textContent('.paw-dash__table')).includes('<b>Luna</b>'));
    ok('reservations: address shows the view', page.url().includes('paw_view=reservations'));
    ok('reservations: status tabs with counts', (await page.textContent('[data-paw-status-tab="pending"]')).includes('1'));
    ok('Elementor kit form styles do not override dashboard inputs', (await page.evaluate(() => getComputedStyle(document.querySelector('[data-paw-search]')).borderTopWidth)) === '1px');
    const before = log.list.length;
    await page.fill('[data-paw-search]', 'zoe');
    await page.waitForFunction(n => window.__pawListCalls === undefined || true, before);
    await page.waitForTimeout(500);
    ok('search re-queries with the term', log.list.length > before && log.list[log.list.length - 1].includes('search=zoe'));
    await page.click('[data-paw-status-tab="pending"]');
    await page.waitForTimeout(150);
    ok('status tab filters', log.list[log.list.length - 1].includes('status=pending'));

    await page.click('.paw-dash__table tbody tr >> nth=0');
    await page.waitForSelector('[data-paw-panel]:not([hidden]) [data-paw-answers] .paw-answers');
    ok('side panel opens with the answers', (await page.textContent('[data-paw-answers]')).includes('Beagle'));
    ok('side panel lists files and the signature', (await page.getAttribute('[data-paw-files] a', 'href')).includes('action=paw_file') && (await page.getAttribute('[data-paw-signature]', 'src')).includes('n=sig'));
    ok('staff requests send the REST nonce', log.headers[0]['x-wp-nonce'] === (await page.evaluate(() => window.PAW_DASH.nonce)));
    await page.selectOption('[data-paw-status-select]', 'confirmed');
    await page.click('[data-paw-status-save]');
    await page.waitForFunction(() => document.querySelector('[data-paw-panel] .paw-dash__badge').textContent.trim() === 'Confirmed');
    ok('status change posts and updates the badge', log.status[0].status === 'confirmed' && log.status[0].notify === true);
    ok('history updated', (await page.textContent('[data-paw-history]')).includes('Dana Desk'));
    await page.fill('[data-paw-notes]', 'Kennel 4');
    await page.click('[data-paw-notes-save]');
    await page.waitForTimeout(150);
    ok('notes saved', log.notes[0] && log.notes[0].notes === 'Kennel 4');
    if (SHOTS) await page.screenshot({ path: path.join(SHOTS, 'dash-panel.png') });
    await page.keyboard.press('Escape');
    ok('Escape closes the panel', await page.isHidden('[data-paw-panel]'));

    await page.setViewportSize({ width: 375, height: 800 });
    await page.waitForTimeout(100);
    ok('phone: no horizontal scroll', (await page.evaluate(() => document.documentElement.scrollWidth)) <= 375);
    ok('phone: side menu becomes a top bar', await page.evaluate(() => { const s = document.querySelector('.paw-dash__side').getBoundingClientRect(); return s.width >= 370 && s.height < 140; }));
    if (SHOTS) await page.screenshot({ path: path.join(SHOTS, 'dash-phone.png'), fullPage: true });

    await page.goto(URL + '?paw_view=reservations&open=7');
    await page.waitForSelector('[data-paw-panel]:not([hidden]) [data-paw-answers] .paw-answers');
    ok('a staff email link opens that reservation', (await page.textContent('[data-paw-panel-title]')).includes('PAW-7K3QX9'));

    await page.goto(URL);
    await page.click('[data-paw-nav="settings"]', { noWaitAfter: true, trial: true });
    ok('settings link goes to wp-admin', (await page.getAttribute('[data-paw-nav="settings"]', 'href')).includes('page=paw-settings'));
    ok('no script errors', errors.length === 0 || (console.log(errors), false));
    console.log(`\nall ${passed} passed`);
  } finally {
    await browser.close();
    server.kill();
  }
})().catch(e => { console.error('FAIL - ' + e.message); process.exit(1); });
