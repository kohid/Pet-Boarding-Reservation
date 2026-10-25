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
// Run `php tests/e2e/build.php signed-in` too: the sign-in test reloads into that page.
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
async function walkToWaiver(page, width, label, opts = {}) {
  const narrow = width < 500;
  ok(`${label}: starts on the welcome screen`, (await visibleStep(page)) === '0');
  ok(`${label}: back is hidden on welcome`, await page.isHidden('[data-paw-prev]'));
  ok(`${label}: form background is transparent`, (await page.evaluate(() => getComputedStyle(document.querySelector('.paw-app')).backgroundColor)) === 'rgba(0, 0, 0, 0)');
  ok(`${label}: back/next are not fixed to the screen`, (await page.evaluate(() => getComputedStyle(document.querySelector('.paw-nav')).position)) !== 'fixed');
  await shot(page, `${label}-0-welcome`);
  await next(page);
  ok(`${label}: → opens step 1`, (await visibleStep(page)) === '1');

  await next(page);
  ok(`${label}: empty required field blocks →`, (await visibleStep(page)) === '1');
  ok(`${label}: required message shown`, (await page.textContent('#paw-owner_first_name-error')).trim() === 'This field is required.');
  ok(`${label}: invalid field focused`, await page.evaluate(() => document.activeElement && document.activeElement.name === 'owner_first_name'));
  await fillOwner(page);
  ok(`${label}: typing clears the error`, await page.isHidden('#paw-owner_first_name-error'));
  if (opts.createAccount) {
    ok(`${label}: password field hidden until the box is ticked`, await page.isHidden('[data-field=account_password]'));
    await page.click('input[name=create_account] >> xpath=..');
    ok(`${label}: ticking "create an account" asks for a password`, await page.isVisible('[name=account_password]'));
    await page.fill('[name=account_password]', 'short');
    await next(page);
    ok(`${label}: short account password blocks Next`, (await visibleStep(page)) === '1' && (await page.textContent('#paw-account_password-error')).trim() === 'Use at least 8 characters.');
    await page.fill('[name=account_password]', 'correct horse');
    ok(`${label}: a confirm box appears with the password`, await page.isVisible('[name=account_password_confirm]'));
    await page.fill('[name=account_password_confirm]', 'correct horsf');
    await next(page);
    ok(`${label}: mismatched confirm blocks Next`, (await visibleStep(page)) === '1' && (await page.textContent('#paw-account_password_confirm-error')).trim() === "The two passwords don't match.");
    await page.click('[data-field=account_password] .paw-pw__toggle');
    ok(`${label}: show password reveals what was typed`, (await page.getAttribute('[name=account_password]', 'type')) === 'text' && (await page.getAttribute('[data-field=account_password] .paw-pw__toggle', 'aria-pressed')) === 'true');
    await page.click('[data-field=account_password] .paw-pw__toggle');
    ok(`${label}: and hides it again`, (await page.getAttribute('[name=account_password]', 'type')) === 'password');
    await page.fill('[name=account_password_confirm]', 'correct horse');
  }
  if (narrow) await noHorizontalScroll(page, width, `${label} step 1`);
  await shot(page, `${label}-1-owner`);
  await next(page);
  ok(`${label}: valid step 1 → step 2`, (await visibleStep(page)) === '2');
  ok(`${label}: progress advanced`, (await page.getAttribute('.paw-progress', 'aria-valuenow')) === '2');
  ok(`${label}: progress bar sits above the step title`, await page.evaluate(() => document.querySelector('.paw-progress').getBoundingClientRect().top <= document.querySelector('[data-step="2"] h2').getBoundingClientRect().top));
  ok(`${label}: Elementor kit form styles do not override the underline style either`, await page.evaluate(() => document.body.classList.contains('elementor-kit-5') && getComputedStyle(document.querySelector('[name="pets[0][pet_breed]"]')).borderTopWidth === '0px'));
  ok(`${label}: theme input borders do not override the underline style`, await page.evaluate(() => { const s = getComputedStyle(document.querySelector('[name="pets[0][pet_name]"]')); return s.borderTopWidth === '0px' && s.borderBottomWidth === '1px'; }));
  ok(`${label}: back/next follow the step content`, await page.evaluate(() => document.querySelector('.paw-nav').getBoundingClientRect().top > document.querySelector('[data-step="2"] .paw-card').getBoundingClientRect().bottom));

  ok(`${label}: "specify" hidden until Other`, await page.isHidden('[data-field=pet_type_other]'));
  await radio(page, 'pets[0][pet_type]', 'other');
  ok(`${label}: Other reveals "specify"`, await page.isVisible('[data-field=pet_type_other]'));
  await next(page);
  ok(`${label}: revealed field is required`, (await page.textContent('#paw-p0-pet_type_other-error')).trim() === 'This field is required.');
  await page.fill('[name="pets[0][pet_type_other]"]', 'Rabbit');
  await page.fill('[name="pets[0][pet_name]"]', 'Biscuit');
  await page.fill('[name="pets[0][pet_breed]"]', 'Lop');
  await page.fill('[name="pets[0][pet_age]"]', '3 years');
  await radio(page, 'pets[0][pet_gender]', 'female');
  await page.fill('[name="pets[0][pet_weight]"]', '4 lbs');
  await radio(page, 'pets[0][pet_spayed]', 'yes');
  await radio(page, 'pets[0][pet_microchipped]', 'no');
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

  await radio(page, 'pets[0][vaccinations_current]', 'no');
  ok(`${label}: vaccinations "No" shows the warning`, await page.isVisible('[data-paw-warn=vaccinations_current]'));
  ok(`${label}: vaccinations "No" disables →`, await page.isDisabled('[data-paw-next]'));
  await radio(page, 'pets[0][vaccinations_current]', 'yes');
  ok(`${label}: "Yes" hides the warning and enables →`, (await page.isHidden('[data-paw-warn=vaccinations_current]')) && !(await page.isDisabled('[data-paw-next]')));
  await page.setInputFiles('input[name="vaccination_files_0[]"]', [{ name: 'rabies.heic', mimeType: 'image/heic', buffer: Buffer.from('xx') }]);
  ok(`${label}: HEIC refused in the browser`, (await page.textContent('#paw-p0-vaccination_files-error')).trim() === 'Only PDF, JPG or PNG files are accepted.');
  await page.setInputFiles('input[name="vaccination_files_0[]"]', [
    { name: 'big-a.png', mimeType: 'image/png', buffer: Buffer.alloc(150000) },
    { name: 'big-b.png', mimeType: 'image/png', buffer: Buffer.alloc(150000) },
  ]);
  ok(`${label}: files over the request total refused in the browser`, (await page.textContent('#paw-p0-vaccination_files-error')).includes('in total'));
  ok(`${label}: only the files that fit are kept`, (await page.locator('.paw-file__item').count()) === 1);
  await page.click('.paw-file__item button');
  await page.setInputFiles('input[name="vaccination_files_0[]"]', [{ name: 'rabies.png', mimeType: 'image/png', buffer: pngBuffer() }]);
  ok(`${label}: chosen file listed`, (await page.textContent('[data-paw-file-list]')).includes('rabies.png'));
  await radio(page, 'pets[0][medical_conditions]', 'no');
  await radio(page, 'pets[0][medications]', 'yes');
  ok(`${label}: medications "Yes" reveals details`, await page.isVisible('[data-field=medications_details]'));
  await page.fill('[name="pets[0][medications_details]"]', '5mg daily');
  await radio(page, 'pets[0][allergies]', 'no');
  if (narrow) await noHorizontalScroll(page, width, `${label} step 4`);
  await shot(page, `${label}-4-health`);
  await next(page);
  ok(`${label}: step 5`, (await visibleStep(page)) === '5');

  await page.fill('[name="pets[0][feeding_schedule]"]', 'Twice a day, one cup');
  await radio(page, 'pets[0][food_preference]', 'own');
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
      ok('payload has the vaccination file', body.includes('name="vaccination_files_0[]"; filename="rabies.png"'));
      ok('payload has a PNG signature', /name="signature"\r\n\r\ndata:image\/png;base64,/.test(body));
      ok('payload has a UUID submission id', /name="submission_id"\r\n\r\n[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/.test(body));
      ok('payload dates are ISO', body.includes(`name="check_in"\r\n\r\n${day(3)}`));
      ok('payload drops the HEIC that was refused', !body.includes('rabies.heic'));
      ok('no account fields sent when the box is not ticked', !body.includes('name="account_password"') && !body.includes('name="create_account"'));
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
      let phoneBody = '';
      const nonces = [];
      await page.route('**/mock/nonce', route => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ nonce: 'fresh-nonce' }) }));
      await page.route('**/mock/reservations', route => {
        calls++;
        nonces.push(route.request().headers()['x-wp-nonce']);
        if (calls === 1) {
          // What WordPress core answers for an expired nonce on a cached page.
          return route.fulfill({ status: 403, contentType: 'application/json', body: JSON.stringify({ code: 'rest_cookie_invalid_nonce', message: 'Cookie check failed', data: { status: 403 } }) });
        }
        if (calls === 2) {
          return route.fulfill({ status: 413, contentType: 'text/html', body: '<html><body><h1>413 Request Entity Too Large</h1></body></html>' });
        }
        if (calls === 3) {
          return route.fulfill({ status: 400, contentType: 'application/json', body: JSON.stringify({ code: 'paw_invalid', message: 'Please check the highlighted fields.', data: { status: 400, fields: { owner_email: 'Please enter a valid email address.' } } }) });
        }
        phoneBody = route.request().postDataBuffer().toString('latin1');
        return route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ reference: 'PAW-ABCDEF', account: true }) });
      });
      await page.goto(URL);
      await noHorizontalScroll(page, 375, 'phone welcome');
      await walkToWaiver(page, 375, 'phone', { createAccount: true });
      await page.click('input[name=waiver_agree] >> xpath=..');
      await sign(page);
      await noHorizontalScroll(page, 375, 'phone step 7');
      await page.click('[data-paw-submit]');
      await page.waitForSelector('[data-paw-form-error]:not([hidden])');
      ok("phone: core's stale-nonce answer gets one retry with a fresh nonce", calls >= 2 && nonces[1] === 'fresh-nonce');
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
      ok('phone: account request sent with the reservation', /name="create_account"\r\n\r\n1/.test(phoneBody) && /name="account_password"\r\n\r\ncorrect horse/.test(phoneBody));
      ok('phone: thank-you says the account is ready', (await page.textContent('[data-step=done]')).includes('Your account is ready'));
      ok('phone: no script errors', errors.length === 0 || (console.log(errors), false));
      await ctx.close();
    }

    // Inline sign-in on the owner step: wrong password, then success → reload signed in, filled in, on step 1.
    {
      const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
      const page = await ctx.newPage();
      const errors = [];
      page.on('pageerror', e => errors.push(e.message));
      let loginCalls = 0;
      let signedIn = false;
      await page.route('**/mock/login', route => {
        loginCalls++;
        if (loginCalls === 1) {
          return route.fulfill({ status: 400, contentType: 'application/json', body: JSON.stringify({ code: 'paw_login', message: 'The email or password is not right.', data: { status: 400 } }) });
        }
        signedIn = true;
        return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, reload: true }) });
      });
      await page.route('**/tests/e2e/out/index.html', async route => {
        if (!signedIn) return route.continue();
        const res = await route.fetch({ url: route.request().url().replace('index.html', 'signed-in.html') });
        return route.fulfill({ response: res });
      });
      await page.goto(URL);
      await next(page);
      ok('sign-in: box hidden until asked for', await page.isHidden('#paw-signin'));
      await page.click('[data-paw-signin-toggle]');
      ok('sign-in: link opens the box', await page.isVisible('#paw-signin'));
      await page.fill('[name=owner_phone]', '555 222 3333');
      await page.fill('#paw-login-email', 'jay@example.com');
      await page.fill('#paw-login-password', 'wrong pass');
      await page.click('[data-paw-signin]');
      await page.waitForSelector('[data-paw-signin-error]:not([hidden])');
      ok('sign-in: wrong password message', (await page.textContent('[data-paw-signin-error]')).trim() === 'The email or password is not right.');
      await page.fill('#paw-login-password', 'some pass1');
      await page.click('[data-paw-signin]');
      await page.waitForSelector('text=Signed in as');
      ok('sign-in: back on the owner step after the reload', (await visibleStep(page)) === '1');
      ok('sign-in: details filled in from the account', (await page.inputValue('[name=owner_first_name]')) === 'Jay' && (await page.inputValue('[name=owner_email]')) === 'jay@example.com');
      ok('sign-in: what the owner typed before signing in is kept', (await page.inputValue('[name=owner_phone]')) === '555 222 3333');
      ok('sign-in: no account prompts once signed in', (await page.locator('[name=create_account]').count()) === 0);
      await page.waitForTimeout(700); // Let the draft save.
      // Stay on the page: the form's own click handler runs first, then this one stops the navigation.
      await page.evaluate(() => document.querySelector('.paw-account--in a').addEventListener('click', e => e.preventDefault()));
      ok('sign-in: a draft exists before logging out', await page.evaluate(() => localStorage.getItem('paw_draft_v1') !== null));
      await page.click('.paw-account--in a');
      ok('sign-in: "Not you? Log out" clears the saved draft', await page.evaluate(() => localStorage.getItem('paw_draft_v1') === null));
      ok('sign-in: no script errors', errors.length === 0 || (console.log(errors), false));
      await ctx.close();
    }

    // Two pets: the welcome menu, add and remove pets, grooming cards, per-pet health, the estimate.
    {
      const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } });
      const page = await ctx.newPage();
      const errors = [];
      page.on('pageerror', e => errors.push(e.message));
      let body = '';
      await page.route('**/mock/reservations', async route => {
        body = route.request().postDataBuffer().toString('latin1');
        await route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ reference: 'PAW-TW0PET' }) });
      });
      await page.goto(URL);
      ok('pets: the welcome screen shows the menu', (await page.locator('[data-paw-menu] .paw-menu__item').count()) === 8 && await page.isVisible('input[name=booking_type][value=boarding]'));
      await radio(page, 'booking_type', 'both');
      await next(page);
      await fillOwner(page);
      await next(page);
      const fillPet = async (n, name, type, size) => {
        await page.fill(`[name="pets[${n}][pet_name]"]`, name);
        await radio(page, `pets[${n}][pet_type]`, type);
        if (size) await radio(page, `pets[${n}][dog_size]`, size);
        await page.fill(`[name="pets[${n}][pet_breed]"]`, 'Mixed');
        await page.fill(`[name="pets[${n}][pet_age]"]`, '3');
        await radio(page, `pets[${n}][pet_gender]`, 'male');
        await page.fill(`[name="pets[${n}][pet_weight]"]`, '30 lbs');
        await radio(page, `pets[${n}][pet_spayed]`, 'yes');
        await radio(page, `pets[${n}][pet_microchipped]`, 'no');
      };
      await fillPet(0, 'Rex', 'dog', 'large');
      await page.click('[data-paw-add-pet]');
      ok('pets: a second card', (await page.locator('[data-paw-pets="pet"] [data-paw-pet]').count()) === 2);
      await fillPet(1, 'Tom', 'cat');
      await page.click('[data-paw-add-pet]');
      await fillPet(2, 'Ivy', 'dog', 'small');
      await page.click('[data-paw-pets="pet"] [data-paw-pet="1"] [data-paw-remove-pet]');
      ok('pets: removing pet 2 moves pet 3 up with its answers', (await page.locator('[data-paw-pets="pet"] [data-paw-pet]').count()) === 2 && (await page.inputValue('[name="pets[1][pet_name]"]')) === 'Ivy' && await page.isChecked('input[name="pets[1][dog_size]"][value=small]'));
      for (let i = 2; i < 5; i++) await page.click('[data-paw-add-pet]');
      ok('pets: five at most, then the add button hides', (await page.locator('[data-paw-pets="pet"] [data-paw-pet]').count()) === 5 && await page.isHidden('[data-paw-add-pet]'));
      for (let i = 4; i >= 2; i--) await page.click(`[data-paw-pets="pet"] [data-paw-pet="${i}"] [data-paw-remove-pet]`);
      await page.fill('[name="pets[1][pet_name]"]', 'Tom');
      await radio(page, 'pets[1][pet_type]', 'cat');
      await page.waitForTimeout(700);
      await page.reload();
      ok('pets: the draft brings back both pets', (await page.inputValue('[name="pets[0][pet_name]"]')) === 'Rex' && (await page.inputValue('[name="pets[1][pet_name]"]')) === 'Tom');
      await next(page); await next(page); await next(page);
      ok('pets: on Reservation Details', (await visibleStep(page)) === '3');
      ok("pets: grooming card for the dog, none for the cat", await page.isVisible('[data-paw-pets="stay"] [data-paw-pet="0"]') && await page.isHidden('[data-paw-pets="stay"] [data-paw-pet="1"]'));
      ok('pets: grooming cards titled with the pet name', (await page.textContent('[data-paw-pets="stay"] [data-paw-pet="0"] [data-paw-pet-name]')).trim() === 'Rex');
      ok('pets: "No grooming" offered in boarding + grooming', await page.isVisible('[data-paw-pets="stay"] [data-paw-pet="0"] [data-paw-svc=none]'));
      await setDate(page, 'check_in', day(3));
      await setDate(page, 'check_out', day(5));
      await setDate(page, 'appointment_date', day(4));
      await radio(page, 'pets[0][service]', 'nail_trim');
      ok('pets: the groomer list has Any / Flexible and the groomers', (await page.locator('[name="pets[0][groomer]"]').count()) === 3 && await page.isChecked('input[name="pets[0][groomer]"][value=any]'));
      await radio(page, 'pets[0][groomer]', 'Ana');
      ok('pets: day and time hidden for one-time grooming', await page.isHidden('[data-paw-pets="stay"] [data-paw-pet="0"] [data-field=preferred_day]'));
      await radio(page, 'pets[0][grooming_frequency]', '4');
      ok('pets: a repeat schedule asks the day and time', await page.isVisible('[data-paw-pets="stay"] [data-paw-pet="0"] [data-field=preferred_day]'));
      await radio(page, 'pets[0][preferred_day]', 'sat');
      await radio(page, 'pets[0][preferred_time]', 'morning');
      const lines = (await page.locator('[data-paw-estimate-lines] li').allTextContents()).map(t => t.replace(/\s+/g, ' ').trim());
      ok('pets: the estimate covers both pets', lines.length === 3 && lines[0].startsWith('Rex: Boarding, 2 nights') && lines[1].startsWith('Rex: Nail Trimming') && lines[2].startsWith('Tom: Boarding, 2 nights'));
      await noHorizontalScroll(page, 390, 'pets step 3');
      await shot(page, 'phone-3-pets');
      await next(page);
      ok('pets: on Health', (await visibleStep(page)) === '4');
      ok('pets: a health section per pet', (await page.locator('[data-paw-pets="health"] [data-paw-pet]').count()) === 2 && (await page.textContent('[data-paw-pets="health"] [data-paw-pet="1"] [data-paw-pet-name]')).trim() === 'Tom');
      for (const n of [0, 1]) {
        await radio(page, `pets[${n}][vaccinations_current]`, 'yes');
        await radio(page, `pets[${n}][medical_conditions]`, 'no');
        await radio(page, `pets[${n}][medications]`, 'no');
        await radio(page, `pets[${n}][allergies]`, 'no');
      }
      await page.setInputFiles('input[name="vaccination_files_0[]"]', [{ name: 'rex.png', mimeType: 'image/png', buffer: pngBuffer() }]);
      await next(page);
      ok("pets: Tom's missing files are flagged on Tom's section", (await visibleStep(page)) === '4' && (await page.textContent('#paw-p1-vaccination_files-error')).trim().startsWith('Please upload'));
      await page.setInputFiles('input[name="vaccination_files_1[]"]', [{ name: 'tom.png', mimeType: 'image/png', buffer: pngBuffer() }]);
      ok("pets: each pet lists its own file", (await page.textContent('[data-paw-pets="health"] [data-paw-pet="0"] [data-paw-file-list]')).includes('rex.png') && !(await page.textContent('[data-paw-pets="health"] [data-paw-pet="0"] [data-paw-file-list]')).includes('tom.png'));
      await next(page);
      for (const n of [0, 1]) {
        await page.fill(`[name="pets[${n}][feeding_schedule]"]`, 'Twice');
        await radio(page, `pets[${n}][food_preference]`, 'own');
      }
      await next(page);
      await page.fill('[name=emergency_name]', 'Charles Babbage');
      await page.fill('[name=emergency_phone]', '555 010 9999');
      await page.fill('[name=emergency_relationship]', 'friend');
      await page.fill('[name=vet_name]', 'Dr. Who');
      await page.fill('[name=vet_clinic]', 'Tardis Vets');
      await page.fill('[name=vet_phone]', '555-010-1111');
      await radio(page, 'vet_authorization', 'yes');
      await next(page);
      await page.click('input[name=waiver_agree] >> xpath=..');
      await page.click('input[name=contact_consent] >> xpath=..');
      await sign(page);
      await page.click('[data-paw-submit]');
      await page.waitForSelector('[data-step=done]:not([hidden])');
      ok('pets: sent with both pets, their files, the groomer and consent', body.includes('name="pets[1][pet_name]"\r\n\r\nTom') && body.includes('name="vaccination_files_0[]"; filename="rex.png"') && body.includes('name="vaccination_files_1[]"; filename="tom.png"') && body.includes('name="pets[0][groomer]"\r\n\r\nAna') && body.includes('name="contact_consent"\r\n\r\n1') && !body.includes('pets[2]'));
      await shot(page, 'phone-pets-done');
      ok('pets: no script errors', errors.length === 0 || (console.log(errors), false));
      await ctx.close();
    }

    // Grooming only: a cat in the booking is caught before the server.
    {
      const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
      const page = await ctx.newPage();
      await page.goto(URL);
      await page.evaluate(() => localStorage.clear());
      await page.reload();
      await radio(page, 'booking_type', 'grooming');
      await next(page);
      await fillOwner(page);
      await next(page);
      await page.fill('[name="pets[0][pet_name]"]', 'Tom');
      await radio(page, 'pets[0][pet_type]', 'cat');
      await page.fill('[name="pets[0][pet_breed]"]', 'Tabby');
      await page.fill('[name="pets[0][pet_age]"]', '2');
      await radio(page, 'pets[0][pet_gender]', 'male');
      await page.fill('[name="pets[0][pet_weight]"]', '9 lbs');
      await radio(page, 'pets[0][pet_spayed]', 'yes');
      await radio(page, 'pets[0][pet_microchipped]', 'no');
      await next(page);
      await setDate(page, 'appointment_date', day(2));
      await radio(page, 'appointment_time', 'morning');
      await next(page);
      ok('grooming: a cat is told grooming is for dogs', (await visibleStep(page)) === '3' && (await page.textContent('[data-paw-step-error]')).includes('Grooming is for dogs only'));
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
