/* Account menu (and, from Task 7, My Profile and My Bookings) in Chromium.
 * Run: php tests/e2e/build-account.php && node tests/e2e/account-e2e.js */
const { chromium } = require('playwright');
const { spawn } = require('child_process');
const assert = require('assert');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const PORT = 8127;
const URL = `http://127.0.0.1:${PORT}/tests/e2e/out/account.html`;
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

    if (typeof globalThis.pawAccountChecks === 'function') await globalThis.pawAccountChecks(page, ok);
    ok('no script errors', errors.length === 0 || (console.log(errors), false));
    console.log(`\nall ${passed} passed`);
  } finally {
    await browser.close();
    server.kill();
  }
})().catch(e => { console.error('FAIL - ' + e.message); process.exit(1); });
