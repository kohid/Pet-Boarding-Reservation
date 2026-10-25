/* Account menu (and, from Task 7, My Profile and My Bookings) in Chromium.
 * Run: php tests/e2e/build-account.php && node tests/e2e/account-e2e.js */
const { chromium } = require('playwright');
const { spawn } = require('child_process');
const assert = require('assert');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const PORT = 8127;
const URL = `http://127.0.0.1:${PORT}/tests/e2e/out/account.html`;
// My Profile / My Bookings sections are below the menus on the same page.
const SHOTS = process.env.PAW_SHOTS || '';
let passed = 0;
function ok(name, cond) { assert.ok(cond, name); passed++; console.log('ok   - ' + name); }

(async () => {
  const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', ROOT], { stdio: 'ignore' });
  await new Promise(r => setTimeout(r, 600));
  const browser = await chromium.launch();
  try {
    const page = await browser.newPage({ viewport: { width: 900, height: 700 } });
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.goto(URL);

    const guest = '#guest-menu';
    ok('guest avatar is the person icon', (await page.locator(`${guest} .paw-um__btn svg`).count()) === 1);
    ok('menu starts closed', await page.isHidden(`${guest} .paw-um__panel`));
    await page.click(`${guest} .paw-um__btn`);
    ok('click opens the menu', await page.isVisible(`${guest} .paw-um__panel`));
    ok('guest sees Guest / Not signed in', (await page.textContent(`${guest} .paw-um__head`)).includes('Not signed in'));
    ok('guest items: Sign In', (await page.locator(`${guest} .paw-um__item`).allTextContents()).join('|') === 'Sign In');
    ok('aria-expanded follows', (await page.getAttribute(`${guest} .paw-um__btn`, 'aria-expanded')) === 'true');
    await page.click('#outside');
    ok('click outside closes', await page.isHidden(`${guest} .paw-um__panel`));

    const member = '#member-menu';
    ok('signed-in avatar shows initials', (await page.textContent(`${member} .paw-um__btn`)).trim() === 'JM');
    await page.focus(`${member} .paw-um__btn`);
    await page.keyboard.press('ArrowDown');
    ok('ArrowDown opens and focuses the first item', await page.isVisible(`${member} .paw-um__panel`) && (await page.evaluate(() => document.activeElement.textContent)) === 'My Profile');
    await page.click(`${member} .paw-um__btn`);
    await page.click(`${member} .paw-um__btn`);
    await page.keyboard.press('Escape');
    ok('Escape closes and returns focus to the button', await page.isHidden(`${member} .paw-um__panel`) && (await page.evaluate(() => document.activeElement.classList.contains('paw-um__btn'))));
    await page.click(`${guest} .paw-um__btn`);
    await page.click(`${member} .paw-um__btn`);
    ok('opening one menu closes the other', await page.isHidden(`${guest} .paw-um__panel`) && await page.isVisible(`${member} .paw-um__panel`));
    await page.waitForTimeout(250);
    if (SHOTS) await page.screenshot({ path: path.join(SHOTS, 'account-menu.png') });

    await page.keyboard.press('Escape');

    // My Profile, signed out: tabs, field errors, sign-up reload.
    const out = '#profile-out';
    ok('sign-in tab shown first', await page.isVisible(`${out} #paw-panel-signin`) && await page.isHidden(`${out} #paw-panel-signup`));
    await page.click(`${out} [data-paw-tab=signup]`);
    ok('create-account tab switches panels', await page.isVisible(`${out} #paw-panel-signup`) && (await page.getAttribute(`${out} [data-paw-tab=signup]`, 'aria-selected')) === 'true');
    let signupCalls = 0;
    let signupBody = null;
    let signupHeaders = null;
    await page.route('**/mock/account/signup', route => {
      signupCalls++;
      signupBody = route.request().postDataJSON();
      signupHeaders = route.request().headers();
      if (signupCalls === 1) {
        return route.fulfill({ status: 409, contentType: 'application/json', body: JSON.stringify({ code: 'paw_email_exists', message: 'An account already uses this email address.', data: { status: 409, fields: { email: 'An account already uses this email address.' } } }) });
      }
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, reload: true }) });
    });
    await page.fill(`${out} #paw-acct-up-first_name`, 'Zoë');
    await page.fill(`${out} #paw-acct-up-last_name`, "O'Brien");
    await page.fill(`${out} #paw-acct-up-email`, 'jay@example.com');
    await page.fill(`${out} #paw-acct-up-password`, 'correct horse');
    await page.click(`${out} #paw-panel-signup button[type=submit]`);
    await page.waitForSelector(`${out} #paw-panel-signup [data-paw-field=email] .paw-acct__error:not([hidden])`);
    ok('server field error shown under the field', (await page.textContent(`${out} #paw-panel-signup [data-paw-field=email] .paw-acct__error`)).includes('already uses'));
    ok('sign-up sends no page nonce (cached pages must still work)', signupHeaders && !('x-wp-nonce' in signupHeaders));
    ok('sign-up sends the typed details as JSON', signupBody && signupBody.first_name === 'Zoë' && signupBody.password === 'correct horse' && signupBody.website === '');
    await page.fill(`${out} #paw-acct-up-email`, 'zoe@example.com');
    await Promise.all([page.waitForEvent('load'), page.click(`${out} #paw-panel-signup button[type=submit]`)]);
    ok('successful sign-up reloads the page', signupCalls === 2);
    await page.goto(URL + '#signup');
    ok('#signup opens the create-account tab', await page.isVisible(`${out} #paw-panel-signup`));

    // My Profile, signed in: save shows the message; nonce sent.
    let profileHeaders = null;
    await page.route('**/mock/account/profile', route => {
      profileHeaders = route.request().headers();
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, message: 'Your details are saved.' }) });
    });
    ok('signed-in profile is filled in', (await page.inputValue('#profile-in [name=first_name]')) === 'Jay');
    await page.click('#profile-in form[data-paw-form="account/profile"] button[type=submit]');
    await page.waitForSelector('#profile-in [data-paw-msg]:not([hidden])');
    ok('profile saved message', (await page.textContent('#profile-in [data-paw-msg]')).trim() === 'Your details are saved.');
    ok('signed-in requests carry the REST nonce', profileHeaders && profileHeaders['x-wp-nonce'] === (await page.evaluate(() => window.PAW_ACCOUNT.nonce)));

    // My Bookings: cancel.
    ok('both reservations listed', (await page.textContent('#bookings')).includes('PAW-7K3QX9') && (await page.textContent('#bookings')).includes('PAW-ABCDEF'));
    ok('only the future confirmed one can be cancelled', (await page.locator('#bookings [data-paw-cancel]').count()) === 1);
    await page.route('**/mock/account/reservations/*/cancel', route => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, status: 'cancelled', label: 'Cancelled' }) }));
    page.once('dialog', d => d.accept());
    await page.click('#bookings [data-paw-cancel]');
    await page.waitForSelector('#bookings .paw-badge--cancelled');
    ok('cancel updates the badge and removes the button', (await page.locator('#bookings [data-paw-cancel]').count()) === 0);
    if (SHOTS) await page.screenshot({ path: path.join(SHOTS, 'account-pages.png'), fullPage: true });
    await page.setViewportSize({ width: 375, height: 800 });
    ok('no horizontal scroll at 375px', (await page.evaluate(() => document.documentElement.scrollWidth)) <= 375);

    ok('no script errors', errors.length === 0 || (console.log(errors), false));
    console.log(`\nall ${passed} passed`);
  } finally {
    await browser.close();
    server.kill();
  }
})().catch(e => { console.error('FAIL - ' + e.message); process.exit(1); });
