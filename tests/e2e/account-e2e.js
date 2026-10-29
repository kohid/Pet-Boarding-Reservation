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
    await page.fill(`${out} #paw-acct-up-confirm`, 'correct horsf');
    await page.click(`${out} #paw-panel-signup button[type=submit]`);
    ok('sign-up: mismatched confirm caught before sending', signupCalls === 0 && (await page.textContent(`${out} #paw-panel-signup [data-paw-field=confirm] .paw-acct__error`)).includes("don't match"));
    await page.click(`${out} #paw-panel-signup [data-paw-field=password] .paw-pw__toggle`);
    ok('sign-up: show password works on the account page', (await page.getAttribute(`${out} #paw-acct-up-password`, 'type')) === 'text');
    await page.fill(`${out} #paw-acct-up-confirm`, 'correct horse');
    await page.click(`${out} #paw-panel-signup button[type=submit]`);
    await page.waitForSelector(`${out} #paw-panel-signup [data-paw-field=email] .paw-acct__error:not([hidden])`);
    ok('server field error shown under the field', (await page.textContent(`${out} #paw-panel-signup [data-paw-field=email] .paw-acct__error`)).includes('already uses'));
    ok('sign-up sends no page nonce (cached pages must still work)', signupHeaders && !('x-wp-nonce' in signupHeaders));
    ok('sign-up sends the typed details as JSON', signupBody && signupBody.first_name === 'Zoë' && signupBody.password === 'correct horse' && signupBody.confirm === 'correct horse' && signupBody.website === '');
    await page.fill(`${out} #paw-acct-up-email`, 'zoe@example.com');
    await Promise.all([page.waitForEvent('load'), page.click(`${out} #paw-panel-signup button[type=submit]`)]);
    ok('successful sign-up reloads the page', signupCalls === 2);
    await page.goto(URL + '#signup');
    ok('#signup opens the create-account tab', await page.isVisible(`${out} #paw-panel-signup`));

    // Sign in: everyone uses this panel (no separate staff line), remember me is sent, and the
    // browser goes where the server says.
    ok('no separate staff sign-in line', !(await page.textContent(out)).includes('Pawfectly staff'));
    let loginBody = null;
    await page.route('**/mock/account/login', route => {
      loginBody = route.request().postDataJSON();
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, reload: true, redirect: URL + '?went=dashboard' }) });
    });
    await page.click(`${out} [data-paw-tab=signin]`);
    await page.fill(`${out} #paw-acct-in-email`, 'desk');
    await page.fill(`${out} #paw-acct-in-password`, 'desk pass1');
    await page.check(`${out} input[name=remember]`);
    await Promise.all([page.waitForURL(/went=dashboard/), page.click(`${out} #paw-panel-signin button[type=submit]`)]);
    ok('sign-in goes to the address the server picked', page.url().includes('went=dashboard'));
    ok('sign-in sends username, remember me and redirect_to', loginBody && loginBody.email === 'desk' && loginBody.remember === '1' && 'redirect_to' in loginBody);
    await page.goto(URL);

    // My Profile, signed in: save shows the message; nonce sent.
    let profileHeaders = null;
    await page.route('**/mock/account/profile', route => {
      profileHeaders = route.request().headers();
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, message: 'Your details are saved.' }) });
    });
    ok('Elementor kit form styles do not override account inputs', (await page.evaluate(() => getComputedStyle(document.querySelector('#profile-in [name=first_name]')).borderTopWidth)) === '1px');
    ok('signed-in profile is filled in', (await page.inputValue('#profile-in [name=first_name]')) === 'Jay');
    await page.click('#profile-in form[data-paw-form="account/profile"] button[type=submit]');
    await page.waitForSelector('#profile-in [data-paw-msg]:not([hidden])');
    ok('profile saved message', (await page.textContent('#profile-in [data-paw-msg]')).trim() === 'Your details are saved.');
    ok('signed-in requests carry the REST nonce', profileHeaders && profileHeaders['x-wp-nonce'] === (await page.evaluate(() => window.PAW_ACCOUNT.nonce)));

    // My Profile: My Pets.
    const pets = '#profile-in [data-paw-pets]';
    const petNames = () => page.$$eval(`${pets} .paw-acct__pet-name`, els => els.map(e => e.textContent));
    ok('my pets: the pet from the last booking, with a note to keep it', (await petNames()).join() === 'Luna' && (await page.textContent(`${pets} [data-paw-pets-msg]`)).includes('press Save pet'));
    let petsBody = null;
    let petsFail = false;
    await page.route('**/mock/account/pets', route => {
      petsBody = route.request().postDataJSON();
      if (petsFail) {
        return route.fulfill({ status: 400, contentType: 'application/json', body: JSON.stringify({ code: 'paw_invalid', message: 'This field is required.', data: { status: 400, fields: { [`pets.${petsBody.pets.length - 1}.pet_name`]: 'This field is required.' } } }) });
      }
      const saved = petsBody.pets.map((p, i) => ({ uid: p.uid || ('0000000000' + i).slice(-12), values: p.values }));
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, pets: saved, message: 'Your pets are saved.' }) });
    });
    await page.click(`${pets} [data-paw-pet-add]`);
    ok('add: the editor has the booking form\'s pet questions, grouped', (await page.locator(`${pets} .paw-acct__pet-group legend`).allTextContents()).join('|') === 'Pet Information|Health & Vaccination Records|Special Care Instructions' && await page.isHidden(`${pets} [data-paw-pet-add]`));
    ok('add: no vaccination upload or per-booking vaccination question', (await page.locator(`${pets} [name=vaccination_files], ${pets} [name=vaccinations_current]`).count()) === 0);
    ok('conditional questions start hidden', await page.isHidden(`${pets} [data-paw-field=dog_size]`) && await page.isHidden(`${pets} [data-paw-field=medications_details]`));
    petsFail = true;
    await page.click(`${pets} .paw-acct__pet-editor button[type=submit]`);
    await page.waitForSelector(`${pets} [data-paw-field=pet_name].is-invalid`);
    ok('a missing name is shown on the field', (await page.textContent(`${pets} [data-paw-field=pet_name] .paw-acct__error`)) === 'This field is required.');
    petsFail = false;
    await page.fill(`${pets} [name=pet_name]`, 'Brax');
    await page.selectOption(`${pets} [name=pet_type]`, 'dog');
    await page.selectOption(`${pets} [name=medications]`, 'yes');
    ok('choosing Dog and medications Yes shows their follow-ups', await page.isVisible(`${pets} [data-paw-field=dog_size]`) && await page.isVisible(`${pets} [data-paw-field=medications_details]`));
    await page.selectOption(`${pets} [name=dog_size]`, 'large');
    await page.fill(`${pets} [name=medications_details]`, 'Apoquel 16mg daily');
    await page.fill(`${pets} [name=feeding_schedule]`, '2 cups, twice a day');
    await page.click(`${pets} .paw-acct__pet-editor button[type=submit]`);
    await page.waitForFunction(() => document.querySelectorAll('#profile-in .paw-acct__pet').length === 2);
    const added = petsBody.pets[1].values;
    ok('save: the whole list is sent, the new pet with its answers', petsBody.pets.length === 2 && petsBody.pets[0].values.pet_name === 'Luna' && added.pet_name === 'Brax' && added.dog_size === 'large' && added.medications_details === 'Apoquel 16mg daily' && added.feeding_schedule === '2 cups, twice a day' && !('pet_type_other' in added));
    ok('save: listed, editor closed, message shown', (await petNames()).join() === 'Luna,Brax' && await page.isHidden(`${pets} [data-paw-pet-editor]`) && (await page.textContent(`${pets} [data-paw-pets-msg]`)) === 'Your pets are saved.');
    await page.click(`${pets} [data-paw-pet-edit="1"]`);
    ok('edit: the editor opens inside that pet\'s row (valid list markup)', await page.evaluate(() => document.querySelector('#profile-in [data-paw-pet-editor]').parentElement.matches('li[data-paw-pet-row="1"]')));
    ok('edit: the saved answers are filled in', (await page.inputValue(`${pets} [name=pet_name]`)) === 'Brax' && (await page.inputValue(`${pets} [name=medications_details]`)) === 'Apoquel 16mg daily');
    await page.selectOption(`${pets} [name=medications]`, 'no');
    await page.click(`${pets} .paw-acct__pet-editor button[type=submit]`);
    await page.waitForSelector(`${pets} [data-paw-pet-editor]`, { state: 'hidden' });
    ok('after saving, focus goes back to that pet\'s Edit button', await page.evaluate(() => document.activeElement && document.activeElement.getAttribute('data-paw-pet-edit') === '1'));
    ok('edit: a hidden follow-up answer is dropped', petsBody.pets[1].uid !== '' && petsBody.pets[1].values.medications === 'no' && !('medications_details' in petsBody.pets[1].values));
    if (SHOTS) await page.locator(pets).screenshot({ path: path.join(SHOTS, 'profile-pets.png') });
    page.once('dialog', d => d.accept());
    await page.click(`${pets} [data-paw-pet-remove="0"]`);
    await page.waitForFunction(() => document.querySelectorAll('#profile-in .paw-acct__pet').length === 1);
    ok('remove: asks, then saves the list without it', petsBody.pets.length === 1 && petsBody.pets[0].values.pet_name === 'Brax' && (await petNames()).join() === 'Brax');

    // My Profile: details for bookings.
    let infoBody = null;
    await page.route('**/mock/account/info', route => {
      infoBody = route.request().postDataJSON();
      return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, values: infoBody, message: 'Your details for bookings are saved.' }) });
    });
    const info = '#profile-in form[data-paw-form="account/info"]';
    await page.fill(`${info} [name=address_city]`, 'Estero');
    await page.fill(`${info} [name=emergency_name]`, 'Amy Miralles');
    await page.selectOption(`${info} [name=vet_authorization]`, 'yes');
    await page.click(`${info} button[type=submit]`);
    await page.waitForSelector(`${info} [data-paw-msg]:not([hidden])`);
    ok('details for bookings: address, emergency contact and vet are sent', infoBody.address_city === 'Estero' && infoBody.emergency_name === 'Amy Miralles' && infoBody.vet_authorization === 'yes' && (await page.textContent(`${info} [data-paw-msg]`)) === 'Your details for bookings are saved.');

    // My Profile: recent activity.
    const act = await page.textContent('#profile-in .paw-acct__activity');
    ok('recent activity: reservations and event sign-ups', act.includes('PAW-7K3QX9') && act.includes('Biscuit') && act.includes('X-Mas Party') && act.includes('Biscuit, Luna'));

    // My Bookings: cancel.
    ok('both reservations listed', (await page.textContent('#bookings')).includes('PAW-7K3QX9') && (await page.textContent('#bookings')).includes('PAW-ABCDEF'));
    ok('only the future confirmed one can be cancelled', (await page.locator('#bookings [data-paw-cancel]').count()) === 1);
    await page.route('**/mock/account/reservations/*/cancel', route => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, status: 'cancelled', label: 'Cancelled' }) }));
    page.once('dialog', d => d.accept());
    await page.click('#bookings [data-paw-cancel]');
    await page.waitForSelector('#bookings .paw-badge--cancelled');
    ok('cancel updates the badge and removes the button', (await page.locator('#bookings [data-paw-cancel]').count()) === 0);

    // My Bookings: event sign-ups.
    const su = '#bookings .paw-acct__signups';
    ok('sign-ups: both listed, the upcoming one first, with its dogs and total', (await page.locator(su + ' tbody tr').count()) === 2 && (await page.textContent(su + ' tbody tr >> nth=0')).includes('X-Mas Party') && (await page.textContent(su + ' tbody tr >> nth=0')).includes('Biscuit, Luna') && (await page.textContent(su + ' tbody tr >> nth=0')).includes('2 × $15 = $30, paid at the event'));
    ok('sign-ups: only the upcoming one can be cancelled', (await page.locator(su + ' [data-paw-cancel-signup]').count()) === 1);
    let suCancel = '';
    await page.route('**/mock/account/signups/*/cancel', route => { suCancel = route.request().url(); return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, status: 'cancelled', label: 'Cancelled' }) }); });
    let asked = '';
    page.once('dialog', d => { asked = d.message(); d.accept(); });
    await page.click(su + ' [data-paw-cancel-signup]');
    await page.waitForSelector(su + ' .paw-badge--cancelled');
    ok('sign-ups: Cancel asks, calls the sign-up route, updates the badge and removes the button', asked.includes('event sign-up') && /\/account\/signups\/\d+\/cancel$/.test(suCancel) && (await page.locator(su + ' [data-paw-cancel-signup]').count()) === 0);
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
