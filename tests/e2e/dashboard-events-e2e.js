/* Dashboard → Events in Chromium: the list, the editor and its repeatable rows, the emoji
 * picker, errors on a row field, publish, duplicate, delete, unsaved changes, an event's
 * sign-ups (meters, rows, status, CSV), categories.
 * Run: php tests/e2e/build-dashboard.php && node tests/e2e/dashboard-events-e2e.js */
const { chromium } = require('playwright');
const { spawn } = require('child_process');
const assert = require('assert');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const PORT = 8129;
const URL = `http://127.0.0.1:${PORT}/tests/e2e/out/dashboard.html`;
const SHOTS = process.env.PAW_SHOTS || '';
let passed = 0;
function ok(name, cond) { assert.ok(cond, name); passed++; console.log('ok   - ' + name); }

const SECTIONS = {
  sessions: { heading: "Pick Your Pup's Session", intro: 'Two sessions…', rows: [{ from: '10:00', to: '12:00', name: 'Session 1', who: 'Small dogs' }] },
  includes: { heading: 'Everything Your {price} Ticket Includes', intro: 'One ticket…', rows: [] },
  goodies: { heading: "Extra Goodies You'll Love", rows: [] },
  good_to_know: { heading: 'Good to Know', rows: [] },
  closing: { heading: 'Reserve Your Spot Today!', intro: 'Register in seconds' },
};
const EVENT = { id: 101, title: 'X-Mas Party', tagline: 'Jingle-bark', emoji: '🎄', category: 'holiday', start_date: '2026-12-19', start_time: '10:00', end_date: '2026-12-19', end_time: '14:00', venue: 'Paw-radise', address: 'Estero, FL', price: '15.00', free: false, note: '', description: '', ticket_url: '', spots: 3, coupon: 'PawPark', status: 'publish', photo_id: 0, photo_url: '', sections: SECTIONS, url: '/event/x-mas-party/', preview_url: '/?post_type=paw_event&p=101&paw_preview=1' };
const ROW = { id: 101, title: 'X-Mas Party', emoji: '🎄', when: 'Saturday, December 19th, 2026 · 10:00 AM – 2:00 PM', category_name: 'Holiday party', color: '#0f42c0', venue: 'Paw-radise', price_text: '$15 per dog', status: 'publish', dogs: 3, url: '/event/x-mas-party/' };
function signups(status) {
  return {
    sessions: [{ label: '10:00 AM – 12:00 PM · Session 1: Small dogs', taken: status === 'cancelled' ? 0 : 2, spots: 3 }, { label: '12:00 – 2:00 PM · Session 2', taken: 3, spots: 3 }],
    counts: { signups: status === 'cancelled' ? 1 : 2, dogs: status === 'cancelled' ? 3 : 5 },
    rows: [
      { id: 501, reference: 'EVT-ABC234', status: status, name: 'Zoe Olsen', email: 'zoe@example.com', phone: '239-420-7418', session: '10:00 AM – 12:00 PM · Session 1: Small dogs', dogs: [{ name: 'Rex', breed: 'Beagle', weight: '22 lbs' }, { name: '<img src=x onerror="window.__pwned=3">', breed: '', weight: '' }], dog_count: 2, coupon: false, total_text: '2 × $15 = $30, paid at the event', notes: 'Rex is shy', created: '2026-10-29 10:00:00' },
      { id: 502, reference: 'EVT-XYZ789', status: 'confirmed', name: 'Bo Lee', email: 'bo@example.com', phone: '', session: '12:00 – 2:00 PM · Session 2', dogs: [{ name: 'Max', breed: '', weight: '' }, { name: 'Duke', breed: '', weight: '' }, { name: 'Bear', breed: '', weight: '' }], dog_count: 3, coupon: true, total_text: 'Free with member code', notes: '', created: '2026-10-28 10:00:00' },
    ],
    csv_url: '/wp-admin/admin-post.php?action=paw_signups_csv&event=101&_wpnonce=abc',
  };
}
const MANIFEST = { categories: { people: [{ name: 'grinning-face', unicode: '😀' }, { name: 'waving-hand', unicode: '👋', tones: ['waving-hand-light', 'waving-hand-medium-light', 'waving-hand-medium', 'waving-hand-medium-dark', 'waving-hand-dark'] }, { name: 'bad"><img src=x onerror="window.__pwned=1">', unicode: '<img src=x onerror="window.__pwned=2">' }], nature: [{ name: 'dog-face', unicode: '🐶' }], activities: [{ name: 'christmas-tree', unicode: '🎄' }] } };

async function mock(page, log) {
  await page.route('**/mock/staff/stats', r => r.fulfill({ json: { check_ins: 0, check_outs: 0, boarding: 0, grooming: 0, pending: 0, nights: [], today: '2026-10-29' } }));
  await page.route(/\/mock\/staff\/events\?/, r => { log.list.push(r.request().url()); return r.fulfill({ json: { rows: r.request().url().includes('tab=drafts') ? [] : [ROW], pages: 1 } }); });
  await page.route(/\/mock\/staff\/events(\/\d+)?$/, r => {
    const req = r.request();
    const id = (/events\/(\d+)$/.exec(req.url()) || [])[1];
    if (req.method() === 'GET') return r.fulfill({ json: Object.assign({}, EVENT, { id: +id, title: id === '102' ? 'X-Mas Party (copy)' : EVENT.title, status: id === '102' ? 'draft' : 'publish' }) });
    if (req.method() === 'DELETE') { log.deleted.push(id); return r.fulfill({ json: { deleted: true } }); }
    const body = req.postDataJSON();
    log.saves.push({ id, body });
    const s = body.sections.sessions.rows[0];
    if (s && s.to <= s.from) return r.fulfill({ status: 400, json: { code: 'paw_invalid', message: 'Please check the highlighted fields.', data: { status: 400, fields: { 'sections.sessions.0.to': 'The end must be after the start.' } } } });
    return r.fulfill({ json: { ok: true, id: 101, event: Object.assign({}, body, { id: 101, url: '/event/x-mas-party/', preview_url: EVENT.preview_url, photo_url: '' }) } });
  });
  await page.route(/\/mock\/staff\/events\/\d+\/signups$/, r => { log.su.push(r.request().url()); return r.fulfill({ json: signups(log.status.length ? log.status[log.status.length - 1].status : 'confirmed') }); });
  await page.route('**/mock/staff/signups/*/status', r => { log.status.push(Object.assign({ url: r.request().url() }, r.request().postDataJSON())); return r.fulfill({ json: { row: {} } }); });
  await page.route('**/mock/staff/events/*/duplicate', r => { log.dup.push(r.request().url()); return r.fulfill({ json: { id: 102 } }); });
  await page.route('**/mock/staff/starter-events', r => { const on = r.request().postDataJSON().on; log.starter.push(on); return r.fulfill({ json: on ? { on: true, count: 30, added: 30, message: '30 events added.' } : { on: false, count: 0, removed: 28, kept: 2, message: '28 events removed. 2 kept because people signed up.' } }); });
  await page.route('**/mock/staff/event-categories', r => { log.cats.push(r.request().postDataJSON()); return r.fulfill({ json: { categories: r.request().postDataJSON().categories.map((c, i) => ({ slug: c.slug || 'new-' + i, name: c.name, color: c.color })) } }); });
  await page.route('https://cdn.jsdelivr.net/**', r => (log.cdn.push(r.request().url()), r.request().url().endsWith('manifest.json'))
    ? r.fulfill({ json: MANIFEST })
    : r.fulfill({ contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="5" fill="#ff7f50"/></svg>' }));
}

const rowTexts = (page, name) => page.$$eval(`[data-paw-ev-rows-for="${name}"] [data-paw-ev-row-field="text"]`, els => els.map(e => e.value));

(async () => {
  const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', ROOT], { stdio: 'ignore' });
  await new Promise(r => setTimeout(r, 600));
  const browser = await chromium.launch();
  try {
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    const log = { list: [], saves: [], dup: [], deleted: [], cats: [], cdn: [], su: [], status: [], starter: [] };
    await mock(page, log);
    await page.goto(URL + '?paw_view=events');

    // List.
    await page.waitForSelector('[data-paw-ev-rows] .paw-dash__ev-item');
    ok('list: loads the upcoming tab', log.list[0].includes('tab=upcoming') && (await page.textContent('.paw-dash__ev-item-title')) === 'X-Mas Party');
    ok('list: a row says how many dogs are signed up', (await page.textContent('.paw-dash__ev-chip--dogs')) === '3 dogs signed up');
    ok('list: Events is the current menu item', (await page.getAttribute('[data-paw-nav="events"]', 'aria-current')) === 'page');
    await page.click('[data-paw-ev-tab="drafts"]');
    await page.waitForSelector('[data-paw-ev-empty]:not([hidden])');
    ok('list: the drafts tab asks for drafts and says when there are none', log.list[log.list.length - 1].includes('tab=drafts'));

    // Add Event.
    await page.click('[data-paw-ev-new]');
    ok('add: an empty editor with the default headings', await page.isVisible('[data-paw-ev-form]') && await page.isHidden('[data-paw-ev-list]') && (await page.inputValue('[data-paw-ev-section="sessions"] [data-paw-ev-part="heading"]')) === "Pick Your Pup's Session" && (await page.inputValue('[data-paw-ev-field="title"]')) === '');
    ok('add: the address shows the editor', page.url().includes('event=new'));
    ok('add: no sign-ups card for a new event', await page.isHidden('[data-paw-su]'));
    ok('add: the category list', (await page.$$eval('[data-paw-ev-field="category"] option', o => o.map(x => x.textContent))).join('|') === 'No category|Holiday party');
    await page.fill('[data-paw-ev-field="title"]', 'X-Mas Party');
    await page.fill('[data-paw-ev-field="start_date"]', '2026-12-19');
    await page.fill('[data-paw-ev-field="start_time"]', '10:00');
    await page.fill('[data-paw-ev-field="end_date"]', '2026-12-19');
    await page.fill('[data-paw-ev-field="end_time"]', '14:00');
    await page.fill('[data-paw-ev-field="venue"]', 'Paw-radise');
    await page.fill('[data-paw-ev-field="price"]', '15');
    await page.fill('[data-paw-ev-field="spots"]', '3');
    await page.fill('[data-paw-ev-field="coupon"]', 'PawPark');

    // Emoji picker.
    await page.fill('[data-paw-ev-field="emoji"]', '🐶');
    await page.click('[data-emoji-target="#paw-ev-emoji"]');
    await page.waitForSelector('.paw-emoji-picker .paw-emoji-search input');
    await page.fill('.paw-emoji-picker .paw-emoji-search input', 'christmas');
    await page.click('.paw-emoji-picker .paw-emoji-item[data-name="christmas-tree"]');
    ok('emoji: the picker replaces the field with its pick', (await page.inputValue('[data-paw-ev-field="emoji"]')) === '🎄');
    ok('emoji: the picker loads a pinned copy of the emoji list', log.cdn.some(u => /kohid\/icons@[0-9a-f]{40}\//.test(u)) && !log.cdn.some(u => u.includes('@main')));
    await page.click('[data-emoji-target="#paw-ev-emoji"]');
    await page.waitForSelector('.paw-emoji-picker .paw-emoji-search input');
    ok('emoji: names from the emoji list are shown as text, never run as HTML', (await page.evaluate(() => window.__pwned)) === undefined && (await page.locator('.paw-emoji-picker img[src="x"]').count()) === 0);
    await page.click('.paw-emoji-picker .paw-emoji-tone-btn');
    await page.click('.paw-emoji-picker [data-tone="medium"]');
    await page.fill('.paw-emoji-picker .paw-emoji-search input', 'waving');
    await page.click('.paw-emoji-picker .paw-emoji-item[data-name="waving-hand-medium"]');
    ok('emoji: a skin tone pick keeps its tone', (await page.inputValue('[data-paw-ev-field="emoji"]')) === '👋🏽');
    await page.fill('[data-paw-ev-field="emoji"]', '🎄');

    // Rows: add, remove, move, drag.
    for (const t of ['A', 'B', 'C']) {
      await page.click('[data-paw-ev-add="includes"]');
      await page.fill(`[data-paw-ev-rows-for="includes"] [data-paw-ev-row]:last-child [data-paw-ev-row-field="text"]`, t);
    }
    ok('rows: three added, the empty hint hidden', (await rowTexts(page, 'includes')).join('') === 'ABC' && await page.isHidden('[data-paw-ev-section="includes"] [data-paw-ev-none]'));
    await page.click('[data-paw-ev-rows-for="includes"] [data-paw-ev-row]:nth-child(2) [data-paw-ev-remove]');
    ok('rows: removing the middle one keeps the others in order', (await rowTexts(page, 'includes')).join('') === 'AC');
    await page.click('[data-paw-ev-rows-for="includes"] [data-paw-ev-row]:nth-child(2) [data-paw-ev-up]');
    ok('rows: Move up', (await rowTexts(page, 'includes')).join('') === 'CA');
    ok('rows: the first row cannot move up', await page.isDisabled('[data-paw-ev-rows-for="includes"] [data-paw-ev-row]:first-child [data-paw-ev-up]'));
    await page.dragAndDrop('[data-paw-ev-rows-for="includes"] [data-paw-ev-row]:nth-child(2) [data-paw-ev-drag]', '[data-paw-ev-rows-for="includes"] [data-paw-ev-row]:nth-child(1)', { targetPosition: { x: 20, y: 4 } });
    ok('rows: drag to reorder', (await rowTexts(page, 'includes')).join('') === 'AC');
    ok('rows: each emoji button targets its own field', (await page.$$eval('[data-paw-ev-rows-for="includes"] [data-emoji-trigger]', b => new Set(b.map(x => x.getAttribute('data-emoji-target'))).size)) === 2);

    // A row error, then publish.
    await page.click('[data-paw-ev-add="sessions"]');
    await page.fill('[data-paw-ev-rows-for="sessions"] [data-paw-ev-row-field="from"]', '12:00');
    await page.fill('[data-paw-ev-rows-for="sessions"] [data-paw-ev-row-field="to"]', '09:00');
    await page.click('[data-paw-ev-save="publish"]');
    await page.waitForSelector('[data-paw-ev-rows-for="sessions"] [data-paw-ev-key="to"].is-invalid');
    ok('errors: the server error lands on the row field', (await page.textContent('[data-paw-ev-rows-for="sessions"] [data-paw-ev-key="to"]')).includes('The end must be after the start.') && await page.isVisible('[data-paw-ev-msg]'));
    await page.fill('[data-paw-ev-rows-for="sessions"] [data-paw-ev-row-field="to"]', '14:00');
    await page.click('[data-paw-ev-save="publish"]');
    await page.waitForSelector('[data-paw-ev-saved]:text("Saved.")');
    const sent = log.saves[log.saves.length - 1].body;
    ok('publish: sends the event with its sections in order', sent.status === 'publish' && sent.title === 'X-Mas Party' && sent.emoji === '🎄' && sent.sections.includes.rows.map(r => r.text).join('') === 'AC' && sent.sections.sessions.rows[0].to === '14:00' && sent.start_date === '2026-12-19' && sent.spots === '3' && sent.coupon === 'PawPark');
    ok('publish: the editor shows Published, Update, Preview and Delete', (await page.textContent('[data-paw-ev-status]')) === 'Published' && (await page.textContent('[data-paw-ev-save="publish"]')) === 'Update' && await page.isVisible('[data-paw-ev-preview]') && await page.isVisible('[data-paw-ev-delete]') && page.url().includes('event=101'));
    ok('published: the draft button says it takes the event offline', (await page.textContent('[data-paw-ev-save="draft"]')) === 'Switch to draft');
    ok('publish: the error is cleared', (await page.locator('[data-paw-ev-form] .is-invalid').count()) === 0);
    await page.click('[data-paw-ev-save="publish"]');
    await page.waitForTimeout(150);
    ok('update: goes to the event', log.saves[log.saves.length - 1].id === '101');

    // Limits.
    for (let i = 0; i < 7; i++) await page.click('[data-paw-ev-add="sessions"]');
    ok('limits: Add hides at 8 sessions', (await page.locator('[data-paw-ev-rows-for="sessions"] [data-paw-ev-row]').count()) === 8 && await page.isHidden('[data-paw-ev-add="sessions"]'));
    if (SHOTS) await page.screenshot({ path: path.join(SHOTS, 'dash-event-editor.png'), fullPage: true });

    // Unsaved changes.
    let asked = 0;
    page.once('dialog', d => { asked++; d.dismiss(); });
    await page.click('[data-paw-ev-back]');
    ok('unsaved: leaving asks first, and staying keeps the editor', asked === 1 && await page.isVisible('[data-paw-ev-form]'));
    page.once('dialog', d => d.accept());
    await page.click('[data-paw-ev-back]');
    await page.waitForSelector('[data-paw-ev-list]:not([hidden])');
    ok('unsaved: leaving after saying yes shows the list', await page.isHidden('[data-paw-ev-form]'));

    // Duplicate and delete.
    await page.click('[data-paw-ev-tab="upcoming"]');
    await page.waitForSelector('[data-paw-ev-duplicate]');
    await page.click('[data-paw-ev-duplicate]');
    await page.waitForFunction(() => document.querySelector('[data-paw-ev-heading]').textContent === 'X-Mas Party (copy)');
    ok('duplicate: opens the copy', log.dup.length === 1 && log.dup[0].includes('/events/101/duplicate') && (await page.textContent('[data-paw-ev-status]')) === 'Draft' && (await page.textContent('[data-paw-ev-save="publish"]')) === 'Publish' && (await page.textContent('[data-paw-ev-save="draft"]')) === 'Save draft');
    page.once('dialog', d => d.accept());
    await page.click('[data-paw-ev-delete]');
    await page.waitForSelector('[data-paw-ev-list]:not([hidden])');
    ok('delete: after confirming, back to the list', log.deleted[0] === '102');

    // An event's sign-ups.
    await page.click('[data-paw-ev-open="101"]');
    await page.waitForSelector('[data-paw-su]:not([hidden])');
    ok('sign-ups: the editor fills spots and the member code', (await page.inputValue('[data-paw-ev-field="spots"]')) === '3' && (await page.inputValue('[data-paw-ev-field="coupon"]')) === 'PawPark');
    ok('sign-ups: the counts and a meter per session', (await page.textContent('[data-paw-su-count]')) === '2 sign-ups · 5 dogs' && (await page.locator('.paw-dash__ev-meter').count()) === 2 && (await page.textContent('.paw-dash__ev-meter >> nth=1')).includes('3 of 3 spots taken') && (await page.locator('.paw-dash__ev-meter >> nth=1 >> .is-full').count()) === 1);
    ok('sign-ups: a row per sign-up with reference, dogs, notes and total', (await page.locator('[data-paw-su-rows] tr').count()) === 2 && (await page.textContent('[data-paw-su-rows] tr >> nth=0')).includes('EVT-ABC234') && (await page.textContent('[data-paw-su-rows] tr >> nth=0')).includes('Rex · Beagle · 22 lbs') && (await page.textContent('[data-paw-su-rows] tr >> nth=0')).includes('Rex is shy') && (await page.textContent('[data-paw-su-rows] tr >> nth=1')).includes('Free with member code'));
    ok('sign-ups: names are shown as text, never run as HTML', (await page.evaluate(() => window.__pwned)) === undefined);
    ok('sign-ups: Download CSV is the plain link from the server', (await page.getAttribute('[data-paw-su-csv]', 'href')) === '/wp-admin/admin-post.php?action=paw_signups_csv&event=101&_wpnonce=abc');
    if (SHOTS) await page.locator('[data-paw-su]').screenshot({ path: path.join(SHOTS, 'dash-signups.png') });
    await page.selectOption('[data-paw-su-status="501"]', 'cancelled');
    await page.waitForSelector('[data-paw-su-rows] tr.is-cancelled');
    ok('sign-ups: a status change is saved and the counts reload', log.status[0].status === 'cancelled' && log.status[0].url.includes('/signups/501/status') && (await page.textContent('[data-paw-su-count]')) === '1 sign-ups · 3 dogs');
    let leaveAsked = 0;
    const onLeave = d => { leaveAsked++; d.accept(); };
    page.once('dialog', onLeave);
    await page.click('[data-paw-ev-back]');
    await page.waitForSelector('[data-paw-ev-list]:not([hidden])');
    ok('sign-ups: a status change is not an unsaved edit', leaveAsked === 0);
    page.off('dialog', onLeave); // It never fired; don't let it catch a later dialog.

    // Phone.
    await page.click('[data-paw-ev-open="101"]');
    await page.waitForSelector('[data-paw-su]:not([hidden])');
    await page.setViewportSize({ width: 390, height: 800 });
    await page.waitForTimeout(100);
    ok('phone: no horizontal scroll in the editor', (await page.evaluate(() => document.documentElement.scrollWidth)) <= 390);
    if (SHOTS) await page.screenshot({ path: path.join(SHOTS, 'dash-event-editor-phone.png'), fullPage: true });
    await page.setViewportSize({ width: 1280, height: 900 });

    // Settings → Event categories.
    await page.goto(URL + '?paw_view=settings');
    await page.waitForSelector('[data-paw-ev-cat]');
    ok('categories: the saved one is listed with its colour', (await page.inputValue('[data-paw-ev-cat-name]')) === 'Holiday party' && await page.isChecked('[data-paw-ev-cat] [data-paw-ev-cat-color][value="#0f42c0"]'));
    await page.click('[data-paw-ev-cat-add]');
    await page.fill('[data-paw-ev-cat]:last-child [data-paw-ev-cat-name]', 'Training');
    await page.check('[data-paw-ev-cat]:last-child [data-paw-ev-cat-color][value="#06d6a0"]');
    await page.click('[data-paw-ev-cat-save]');
    await page.waitForSelector('[data-paw-ev-cat-msg].is-ok');
    ok('categories: saved with names and colours', log.cats[0].categories.length === 2 && log.cats[0].categories[0].slug === 'holiday' && log.cats[0].categories[1].name === 'Training' && log.cats[0].categories[1].color === '#06d6a0');

    // Settings → Starter events.
    const sw = '[data-paw-starter-toggle]';
    ok('starter events: the switch starts off', (await page.locator(sw).count()) === 1 && !(await page.isChecked(sw)) && (await page.getAttribute(sw, 'role')) === 'switch');
    await page.click('.paw-dash__toggle');
    await page.waitForSelector('[data-paw-starter-msg].is-ok');
    ok('starter events: on adds them and says so', log.starter[0] === true && await page.isChecked(sw) && (await page.textContent('[data-paw-starter-msg]')) === '30 events added.');
    let askedOff = '';
    page.once('dialog', d => { askedOff = d.message(); d.dismiss(); });
    await page.click('.paw-dash__toggle');
    ok('starter events: off asks first; saying no keeps them', askedOff.includes('trash') && await page.isChecked(sw) && log.starter.length === 1);
    page.once('dialog', d => d.accept());
    await page.click('.paw-dash__toggle');
    await page.waitForFunction(() => document.querySelector('[data-paw-starter-msg]').textContent.includes('removed'));
    ok('starter events: off removes them and explains the ones kept', log.starter[1] === false && !(await page.isChecked(sw)) && (await page.textContent('[data-paw-starter-msg]')).includes('2 kept because people signed up'));
    if (SHOTS) await page.locator('[data-paw-starter]').screenshot({ path: path.join(SHOTS, 'dash-starter.png') });

    ok('no script errors', errors.length === 0 || (console.log(errors), false));
    console.log(`\nall ${passed} passed`);
  } finally {
    await browser.close();
    server.kill();
  }
})().catch(e => { console.error('FAIL - ' + e.message); process.exit(1); });
