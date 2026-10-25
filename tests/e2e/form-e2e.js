/* Drives the real reservation form in Chromium with the REST endpoint mocked.
 * Run: php tests/e2e/build.php && node tests/e2e/form-e2e.js
 * Set PAW_SHOTS=/some/dir to also save screenshots of each step. */
const { chromium } = require('playwright');
const { spawn } = require('child_process');
const assert = require('assert');
const path = require('path');
const zlib = require('zlib');

const ROOT = path.resolve(__dirname, '../..');
const PORT = 8124;
const URL = `http://127.0.0.1:${PORT}/tests/e2e/out/index.html`;
const SHOTS = process.env.PAW_SHOTS || '';

let passed = 0;
function ok(name, cond) {
  assert.ok(cond, name);
  passed++;
  console.log('ok   - ' + name);
}

function day(offset) {
  const d = new Date();
  d.setDate(d.getDate() + offset);
  return d.toISOString().slice(0, 10);
}

/** A small valid PNG (a filled square), built without any image library. */
function pngBuffer() {
  const w = 8, h = 8;
  const raw = Buffer.alloc((w * 3 + 1) * h);
  for (let y = 0; y < h; y++) {
    raw[y * (w * 3 + 1)] = 0;
    for (let x = 0; x < w; x++) raw.set([255, 127, 80], y * (w * 3 + 1) + 1 + x * 3);
  }
  const crcTable = Array.from({ length: 256 }, (_, n) => { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; return c >>> 0; });
  const crc = b => { let c = 0xffffffff; for (const x of b) c = crcTable[(c ^ x) & 0xff] ^ (c >>> 8); return (c ^ 0xffffffff) >>> 0; };
  const chunk = (type, data) => { const len = Buffer.alloc(4); len.writeUInt32BE(data.length); const td = Buffer.concat([Buffer.from(type), data]); const c = Buffer.alloc(4); c.writeUInt32BE(crc(td)); return Buffer.concat([len, td, c]); };
  const ihdr = Buffer.alloc(13); ihdr.writeUInt32BE(w, 0); ihdr.writeUInt32BE(h, 4); ihdr[8] = 8; ihdr[9] = 2;
  return Buffer.concat([Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]), chunk('IHDR', ihdr), chunk('IDAT', zlib.deflateSync(raw)), chunk('IEND', Buffer.alloc(0))]);
}

async function visibleStep(page) {
  return page.evaluate(() => {
    const s = [...document.querySelectorAll('.paw-step')].find(el => !el.hidden);
    return s ? s.dataset.step : null;
  });
}
async function next(page) { await page.click('[data-paw-next]'); }
async function radio(page, name, value) { await page.click(`input[name="${name}"][value="${value}"] >> xpath=..`); }
async function setDate(page, name, value) {
  await page.evaluate(([n, v]) => document.querySelector(`input[name="${n}"]`)._flatpickr.setDate(v, true), [name, value]);
}
async function noHorizontalScroll(page, width, label) {
  const sw = await page.evaluate(() => document.documentElement.scrollWidth);
  ok(`${label}: no horizontal scroll at ${width}px (scrollWidth ${sw})`, sw <= width);
}
async function shot(page, name) {
  if (SHOTS) await page.screenshot({ path: path.join(SHOTS, name + '.png'), fullPage: true });
}

async function fillOwner(page) {
  await page.fill('[name=owner_first_name]', 'Zoë');
  await page.fill('[name=owner_last_name]', "O'Brien");
  await page.fill('[name=owner_email]', 'zoe@example.com');
  await page.fill('[name=owner_phone]', '+1 (555) 010-2030');
  await page.fill('[name=address_street]', '1 Main St');
  await page.fill('[name=address_state]', 'IL');
  await page.fill('[name=address_postcode]', '62701');
}

/** Walks from the welcome screen to the waiver step, checking behaviour on the way. */
async function walkToWaiver(page, width, label) {
  const narrow = width < 500;
  ok(`${label}: starts on the welcome screen`, (await visibleStep(page)) === '0');
  ok(`${label}: back is disabled on welcome`, await page.isDisabled('[data-paw-prev]'));
  await shot(page, `${label}-0-welcome`);
  await next(page);
  ok(`${label}: → opens step 1`, (await visibleStep(page)) === '1');

  await next(page);
  ok(`${label}: empty required field blocks →`, (await visibleStep(page)) === '1');
  ok(`${label}: required message shown`, (await page.textContent('#paw-owner_first_name-error')).trim() === 'This field is required.');
  ok(`${label}: invalid field focused`, await page.evaluate(() => document.activeElement && document.activeElement.name === 'owner_first_name'));
  await fillOwner(page);
  ok(`${label}: typing clears the error`, await page.isHidden('#paw-owner_first_name-error'));
  if (narrow) await noHorizontalScroll(page, width, `${label} step 1`);
  await shot(page, `${label}-1-owner`);
  await next(page);
  ok(`${label}: valid step 1 → step 2`, (await visibleStep(page)) === '2');
  ok(`${label}: progress advanced`, (await page.getAttribute('.paw-progress', 'aria-valuenow')) === '2');

  ok(`${label}: "specify" hidden until Other`, await page.isHidden('[data-field=pet_type_other]'));
  await radio(page, 'pet_type', 'other');
  ok(`${label}: Other reveals "specify"`, await page.isVisible('[data-field=pet_type_other]'));
  await next(page);
  ok(`${label}: revealed field is required`, (await page.textContent('#paw-pet_type_other-error')).trim() === 'This field is required.');
  await page.fill('[name=pet_type_other]', 'Rabbit');
  await page.fill('[name=pet_name]', 'Biscuit');
  await page.fill('[name=pet_breed]', 'Lop');
  await page.fill('[name=pet_age]', '3 years');
  await radio(page, 'pet_gender', 'female');
  await page.fill('[name=pet_weight]', '4 lbs');
  await radio(page, 'pet_spayed', 'yes');
  await radio(page, 'pet_microchipped', 'no');
  if (narrow) await noHorizontalScroll(page, width, `${label} step 2`);
  await shot(page, `${label}-2-pet`);
  await next(page);
  ok(`${label}: step 3`, (await visibleStep(page)) === '3');

  const minDate = await page.evaluate(() => document.querySelector('input[name=check_in]')._flatpickr.config.minDate.toISOString().slice(0, 10));
  ok(`${label}: check-in calendar starts at the server's today`, minDate === window_today);
  ok(`${label}: dates shown as MM/DD/YYYY`, await page.evaluate(() => document.querySelector('input[name=check_in]')._flatpickr.config.altFormat === 'm/d/Y'));
  await setDate(page, 'check_in', day(3));
  const outMin = await page.evaluate(() => document.querySelector('input[name=check_out]')._flatpickr.config.minDate.toISOString().slice(0, 10));
  ok(`${label}: check-out calendar starts the day after check-in`, outMin === day(4));
  await setDate(page, 'check_out', day(5));
  await setDate(page, 'check_in', day(6));
  ok(`${label}: moving check-in past check-out clears check-out`, (await page.inputValue('input[name=check_out]')) === '');
  await next(page);
  ok(`${label}: cleared check-out is required again`, (await page.textContent('#paw-check_out-error')).trim() === 'This field is required.');
  await setDate(page, 'check_in', day(3));
  await setDate(page, 'check_out', day(5));
  if (narrow) await noHorizontalScroll(page, width, `${label} step 3`);
  await shot(page, `${label}-3-stay`);
  await next(page);
  ok(`${label}: step 4`, (await visibleStep(page)) === '4');

  await radio(page, 'vaccinations_current', 'no');
  ok(`${label}: vaccinations "No" shows the warning`, await page.isVisible('[data-paw-warn=vaccinations_current]'));
  ok(`${label}: vaccinations "No" disables →`, await page.isDisabled('[data-paw-next]'));
  await radio(page, 'vaccinations_current', 'yes');
  ok(`${label}: "Yes" hides the warning and enables →`, (await page.isHidden('[data-paw-warn=vaccinations_current]')) && !(await page.isDisabled('[data-paw-next]')));
  await page.setInputFiles('input[name="vaccination_files[]"]', [{ name: 'rabies.heic', mimeType: 'image/heic', buffer: Buffer.from('xx') }]);
  ok(`${label}: HEIC refused in the browser`, (await page.textContent('#paw-vaccination_files-error')).trim() === 'Only PDF, JPG or PNG files are accepted.');
  await page.setInputFiles('input[name="vaccination_files[]"]', [
    { name: 'big-a.png', mimeType: 'image/png', buffer: Buffer.alloc(150000) },
    { name: 'big-b.png', mimeType: 'image/png', buffer: Buffer.alloc(150000) },
  ]);
  ok(`${label}: files over the request total refused in the browser`, (await page.textContent('#paw-vaccination_files-error')).includes('in total'));
  ok(`${label}: only the files that fit are kept`, (await page.locator('.paw-file__item').count()) === 1);
  await page.click('.paw-file__item button');
  await page.setInputFiles('input[name="vaccination_files[]"]', [{ name: 'rabies.png', mimeType: 'image/png', buffer: pngBuffer() }]);
  ok(`${label}: chosen file listed`, (await page.textContent('[data-paw-file-list]')).includes('rabies.png'));
  await radio(page, 'medical_conditions', 'no');
  await radio(page, 'medications', 'yes');
  ok(`${label}: medications "Yes" reveals details`, await page.isVisible('[data-field=medications_details]'));
  await page.fill('[name=medications_details]', '5mg daily');
  await radio(page, 'allergies', 'no');
  if (narrow) await noHorizontalScroll(page, width, `${label} step 4`);
  await shot(page, `${label}-4-health`);
  await next(page);
  ok(`${label}: step 5`, (await visibleStep(page)) === '5');

  await page.fill('[name=feeding_schedule]', 'Twice a day, one cup');
  await radio(page, 'food_preference', 'own');
  await shot(page, `${label}-5-care`);
  await next(page);
  ok(`${label}: step 6`, (await visibleStep(page)) === '6');

  await page.fill('[name=emergency_name]', 'Charles Babbage');
  await page.fill('[name=emergency_phone]', '555 010 9999');
  await page.fill('[name=emergency_relationship]', 'friend');
  await page.fill('[name=vet_name]', 'Dr. Who');
  await page.fill('[name=vet_clinic]', 'Tardis Vets');
  await page.fill('[name=vet_phone]', '555-010-1111');
  await radio(page, 'vet_authorization', 'yes');
  await shot(page, `${label}-6-emergency`);
  await next(page);
  ok(`${label}: step 7`, (await visibleStep(page)) === '7');
  ok(`${label}: → hidden on the last step`, await page.isHidden('[data-paw-next]'));
  ok(`${label}: waiver date defaults to today`, (await page.inputValue('input[name=signed_date]')) === window_today);
  const sized = await page.evaluate(() => { const c = document.querySelector('[data-paw-signature]'); return c.width === Math.round(c.getBoundingClientRect().width * Math.min(devicePixelRatio, 2)); });
  ok(`${label}: signature pad matches its displayed size`, sized);
}

async function sign(page) {
  await page.locator('[data-paw-signature]').scrollIntoViewIfNeeded();
  const box = await page.locator('[data-paw-signature]').boundingBox();
  await page.mouse.move(box.x + 30, box.y + 40);
  await page.mouse.down();
  await page.mouse.move(box.x + 120, box.y + 120, { steps: 8 });
  await page.mouse.move(box.x + 220, box.y + 50, { steps: 8 });
  await page.mouse.up();
}

let window_today;

(async () => {
  const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', ROOT], { stdio: 'ignore' });
  await new Promise(r => setTimeout(r, 600));
  const browser = await chromium.launch();
  try {
    // Desktop: the whole flow, a draft restore, a double-clicked submit.
    {
      const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
      const page = await ctx.newPage();
      const errors = [];
      page.on('pageerror', e => errors.push(e.message));
      const posts = [];
      await page.route('**/mock/reservations', async route => {
        posts.push(route.request());
        await new Promise(r => setTimeout(r, 300));
        await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ reference: 'PAW-7K3QX9' }) });
      });
      await page.goto(URL);
      window_today = await page.evaluate(() => window.PAW_FORM.today);

      // Draft restore: fill step 1, reload, values come back.
      await next(page);
      await fillOwner(page);
      await page.waitForTimeout(700);
      await page.reload();
      ok('desktop: draft restored after reload', (await page.inputValue('[name=owner_last_name]')) === "O'Brien");
      ok('desktop: restored-progress note shown', await page.isVisible('[data-paw-draft]'));
      await page.click('[data-paw-restart]');
      ok('desktop: "Start over" empties the form', (await page.inputValue('[name=owner_last_name]')) === '' && await page.isHidden('[data-paw-draft]'));
      ok('desktop: "Start over" forgets the draft', await page.evaluate(() => localStorage.getItem('paw_draft_v1') === null));
      const shown = await page.evaluate(() => document.querySelector('input[name=signed_date]')._flatpickr.altInput.value);
      const [y, m, d] = window_today.split('-');
      ok('desktop: "Start over" keeps the waiver date showing today', shown === `${m}/${d}/${y}`);

      await walkToWaiver(page, 1280, 'desktop');
      await page.click('[data-paw-submit]');
      ok('desktop: unsigned waiver blocked', (await page.textContent('#paw-signature-error')).trim() === 'Please sign in the box above.');
      ok('desktop: agreement required', (await page.textContent('#paw-waiver_agree-error')).trim() === 'Please agree to the terms to continue.');
      await page.click('input[name=waiver_agree] >> xpath=..');
      await sign(page);
      await shot(page, 'desktop-7-waiver');
      await page.dblclick('[data-paw-submit]');
      await page.waitForSelector('[data-step=done]:not([hidden])');
      ok('desktop: double-click sends exactly one request', posts.length === 1);
      ok('desktop: thank-you shows the reference', (await page.textContent('[data-paw-reference]')).trim() === 'PAW-7K3QX9');
      ok('desktop: nav hidden after submitting', await page.isHidden('[data-paw-nav]'));
      await shot(page, 'desktop-done');

      const req = posts[0];
      const body = req.postDataBuffer().toString('latin1');
      ok('request sends the nonce header', req.headers()['x-wp-nonce'] === (await page.evaluate(() => window.PAW_FORM.nonce)));
      ok('payload has owner_email', body.includes('name="owner_email"\r\n\r\nzoe@example.com'));
      ok('payload has the vaccination file', body.includes('name="vaccination_files[]"; filename="rabies.png"'));
      ok('payload has a PNG signature', /name="signature"\r\n\r\ndata:image\/png;base64,/.test(body));
      ok('payload has a UUID submission id', /name="submission_id"\r\n\r\n[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/.test(body));
      ok('payload dates are ISO', body.includes(`name="check_in"\r\n\r\n${day(3)}`));
      ok('payload drops the HEIC that was refused', !body.includes('rabies.heic'));
      ok('draft cleared after submitting', await page.evaluate(() => localStorage.getItem('paw_draft_v1') === null));
      ok('desktop: no script errors', errors.length === 0 || (console.log(errors), false));
      await ctx.close();
    }

    // Phone width: the whole flow, plus a server-side error that sends the visitor back to the right step.
    {
      const ctx = await browser.newContext({ viewport: { width: 375, height: 812 }, deviceScaleFactor: 3, hasTouch: true });
      const page = await ctx.newPage();
      const errors = [];
      page.on('pageerror', e => errors.push(e.message));
      let calls = 0;
      await page.route('**/mock/reservations', route => {
        calls++;
        if (calls === 1) {
          return route.fulfill({ status: 413, contentType: 'text/html', body: '<html><body><h1>413 Request Entity Too Large</h1></body></html>' });
        }
        if (calls === 2) {
          return route.fulfill({ status: 400, contentType: 'application/json', body: JSON.stringify({ code: 'paw_invalid', message: 'Please check the highlighted fields.', data: { status: 400, fields: { owner_email: 'Please enter a valid email address.' } } }) });
        }
        return route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ reference: 'PAW-ABCDEF' }) });
      });
      await page.goto(URL);
      await noHorizontalScroll(page, 375, 'phone welcome');
      await walkToWaiver(page, 375, 'phone');
      await page.click('input[name=waiver_agree] >> xpath=..');
      await sign(page);
      await noHorizontalScroll(page, 375, 'phone step 7');
      await page.click('[data-paw-submit]');
      await page.waitForSelector('[data-paw-form-error]:not([hidden])');
      ok('phone: a web-server 413 (HTML body) says the files are too large', (await page.textContent('[data-paw-form-error]')).includes('too large'));
      await page.click('[data-paw-submit]');
      await page.waitForFunction(() => !document.querySelector('[data-step="1"]').hidden);
      ok('phone: server field error returns to its step', (await visibleStep(page)) === '1');
      ok('phone: server message shown on the field', (await page.textContent('#paw-owner_email-error')).trim() === 'Please enter a valid email address.');
      for (let i = 0; i < 6; i++) await next(page);
      ok('phone: earlier answers kept, back on the last step', (await visibleStep(page)) === '7');
      await page.click('[data-paw-submit]');
      await page.waitForSelector('[data-step=done]:not([hidden])');
      ok('phone: retry succeeds', (await page.textContent('[data-paw-reference]')).trim() === 'PAW-ABCDEF');
      ok('phone: no script errors', errors.length === 0 || (console.log(errors), false));
      await ctx.close();
    }

    console.log(`\nall ${passed} passed`);
  } finally {
    await browser.close();
    server.kill();
  }
})().catch(e => {
  console.error('FAIL - ' + e.message);
  process.exit(1);
});
