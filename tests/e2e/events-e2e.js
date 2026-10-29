/* The public events pages in Chromium: [pawfectly_calendar] (views, navigation, the month
 * picker, search, categories, popover, "+N more", phone dots), [pawfectly_events] (tabs, Load
 * more) and the X-Mas Party event page. The public routes run the real PHP (events-router.php).
 * Run: php tests/e2e/build-events.php && node tests/e2e/events-e2e.js */
const { chromium } = require('playwright');
const { spawn } = require('child_process');
const assert = require('assert');
const path = require('path');

const ROOT = path.resolve(__dirname, '../..');
const PORT = 8130;
const BASE = `http://127.0.0.1:${PORT}/tests/e2e/out/`;
const SHOTS = process.env.PAW_SHOTS || '';
let passed = 0;
function ok(name, cond) { assert.ok(cond, name); passed++; console.log('ok   - ' + name); }
async function shot(page, name) { if (SHOTS) await page.screenshot({ path: path.join(SHOTS, name + '.png'), fullPage: true }); }
async function noScroll(page, label) {
  const sw = await page.evaluate(() => document.documentElement.scrollWidth);
  ok(`${label}: no horizontal scroll at 390px (scrollWidth ${sw})`, sw <= 390);
}
const title = page => page.textContent('[data-paw-cal-title-text]').then(t => t.trim());
async function waitLoaded(page) {
  await page.waitForFunction(() => document.querySelector('[data-paw-cal-loader]').hidden);
}
async function nextTitle(page, before) {
  await page.waitForFunction(b => document.querySelector('[data-paw-cal-title-text]').textContent.trim() !== b, before);
  await waitLoaded(page);
}

(async () => {
  const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', ROOT, path.join(__dirname, 'events-router.php')], { stdio: 'ignore' });
  await new Promise(r => setTimeout(r, 700));
  const browser = await chromium.launch();
  try {
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));

    /* ---------- Calendar ---------- */
    await page.goto(BASE + 'calendar.html');
    ok('calendar: opens on December 2026', (await title(page)) === 'December 2026');
    const xmas = page.locator('.paw-cal__cell[data-date="2026-12-19"] [data-paw-cal-event]');
    ok('month: the X-Mas Party chip on December 19th, with its time', (await xmas.count()) === 1 && (await xmas.textContent()).includes('10:00 AM') && (await xmas.textContent()).includes('X-Mas Party'));
    ok('month: drafts never show', !(await page.textContent('[data-paw-cal-box="month"]')).includes('Secret draft'));
    ok('month: an event over several days runs across its days', await page.locator('.paw-cal__cell[data-date="2026-12-31"] .paw-cal__ev.is-multi.is-mstart').count() === 1 && await page.locator('.paw-cal__cell[data-date="2027-01-01"] .paw-cal__ev.is-multi').count() === 1);
    ok('month: the category colour', (await xmas.getAttribute('style')).includes('#e8a33a'));

    const busy = '.paw-cal__cell[data-date="2026-12-08"]';
    ok('+N more: three chips shown, the rest behind "+2 more"', (await page.locator(busy + ' [data-paw-cal-event]:visible').count()) === 3 && (await page.textContent(busy + ' [data-paw-cal-more]')).trim() === '+2 more');
    await page.click(busy + ' [data-paw-cal-more]');
    ok('+N more: expands the day', (await page.locator(busy + ' [data-paw-cal-event]:visible').count()) === 5 && (await page.textContent(busy + ' [data-paw-cal-more]')).trim() === 'Show less');

    await xmas.hover();
    await page.waitForSelector('.paw-cal-pop.is-shown');
    ok('popover: time, title, venue and a link to the event', (await page.textContent('.paw-cal-pop__title')) === 'X-Mas Party' && (await page.textContent('.paw-cal-pop__time')).includes('10:00 AM – 2:00 PM') && (await page.getAttribute('.paw-cal-pop__btn--primary', 'href')).endsWith('/event/x-mas-party/'));
    await page.click('[data-paw-pop-add]');
    ok('popover: Add to calendar lists Google, Outlook and .ics', (await page.locator('.paw-cal-pop__menu a').count()) === 3 && (await page.getAttribute('.paw-cal-pop__menu a >> nth=0', 'href')).startsWith('https://calendar.google.com/'));
    await shot(page, 'cal-month');
    await page.mouse.move(5, 5);

    let before = await title(page);
    await page.click('[data-paw-cal-next]');
    await nextTitle(page, before);
    ok('next: January 2027, with the new year event ending on the 2nd', (await title(page)) === 'January 2027' && await page.locator('.paw-cal__cell[data-date="2027-01-02"] .paw-cal__ev.is-mend').count() === 1);
    before = await title(page);
    await page.click('[data-paw-cal-prev]');
    await nextTitle(page, before);
    ok('previous: back to December', (await title(page)) === 'December 2026');

    before = await title(page);
    await page.click('[data-paw-cal-today]');
    await nextTitle(page, before);
    const now = new Date();
    ok('today: the current month', (await title(page)) === now.toLocaleString('en-US', { month: 'long' }) + ' ' + now.getFullYear());

    await page.click('[data-paw-cal-title]');
    ok('picker: opens under the title', await page.isVisible('[data-paw-cal-picker]'));
    while ((await page.textContent('.paw-cal__pk-head span')) !== 'December 2026') {
      await page.click('[data-paw-pk="1"]');
    }
    before = await title(page);
    await page.click('[data-paw-pk-day="2026-12-19"]');
    await nextTitle(page, before);
    ok('picker: a day takes you to its month', (await title(page)) === 'December 2026' && await page.isHidden('[data-paw-cal-picker]'));

    await page.click('[data-paw-cal-view="list"]');
    ok('list view: the month by day', await page.isVisible('[data-paw-cal-box="list"]') && await page.isHidden('[data-paw-cal-box="month"]') && (await page.locator('.paw-cal__agenda-day').count()) >= 4 && (await page.textContent('.paw-cal__agenda-day[data-day="2026-12-19"]')).includes('X-Mas Party'));
    await shot(page, 'cal-list');

    await page.fill('[data-paw-cal-q]', 'x-mas');
    await page.click('.paw-cal__search button[type=submit]');
    await page.waitForFunction(() => document.querySelectorAll('.paw-cal__agenda-day').length === 1);
    ok('search: finds the event', (await page.textContent('[data-paw-cal-box="list"]')).includes('X-Mas Party'));
    await page.fill('[data-paw-cal-q]', 'zzz');
    await page.click('.paw-cal__search button[type=submit]');
    await page.waitForSelector('[data-paw-cal-box="list"] .paw-cal__empty');
    ok('search: nothing found says so', (await page.textContent('[data-paw-cal-box="list"]')).includes('No events match “zzz” this month.'));
    await page.fill('[data-paw-cal-q]', '');
    await page.selectOption('[data-paw-cal-cat]', 'training');
    await page.waitForFunction(() => !document.querySelector('[data-paw-cal-box="list"]').textContent.includes('X-Mas') && document.querySelector('[data-paw-cal-box="list"]').textContent.includes('Agility'));
    ok('category: only training events', (await page.textContent('[data-paw-cal-box="list"]')).includes('Puppy class 1'));
    await page.selectOption('[data-paw-cal-cat]', '');
    await waitLoaded(page);

    await page.click('[data-paw-cal-view="month"]');
    await page.click('.paw-cal__cell[data-date="2026-12-19"] [data-paw-cal-goto]');
    await page.waitForFunction(() => document.querySelector('[data-paw-cal-title-text]').textContent.trim() === 'December 19, 2026');
    await waitLoaded(page);
    ok('day view: a day number opens that day', await page.isVisible('[data-paw-cal-box="day"]') && (await page.textContent('[data-paw-cal-box="day"]')).includes('X-Mas Party'));
    before = await title(page);
    await page.click('[data-paw-cal-next]');
    await nextTitle(page, before);
    ok('day view: next goes one day on', (await title(page)) === 'December 20, 2026' && (await page.textContent('[data-paw-cal-box="day"]')).includes('No events on Sunday, December 20, 2026.'));

    await page.setViewportSize({ width: 390, height: 800 });
    await page.goto(BASE + 'calendar.html');
    await noScroll(page, 'calendar');
    ok('phone: chips become dots', (await page.locator('.paw-cal__cell[data-date="2026-12-19"] .paw-cal__ev').boundingBox()).width <= 10);
    await page.click('.paw-cal__cell[data-date="2026-12-19"] [data-paw-cal-goto]');
    await page.waitForFunction(() => document.querySelector('[data-paw-cal-title-text]').textContent.trim() === 'December 19, 2026');
    ok('phone: tapping a day opens it', await page.isVisible('[data-paw-cal-box="day"]'));
    await shot(page, 'cal-phone');
    await page.setViewportSize({ width: 1280, height: 900 });

    /* ---------- Events list ---------- */
    await page.goto(BASE + 'events.html');
    const cards = () => page.locator('.paw-events__card').count();
    ok('events: nine upcoming, soonest first', (await cards()) === 9 && (await page.textContent('.paw-events__card >> nth=0')).includes('Puppy class 1'));
    ok('events: a card shows the date block, emoji and price', (await page.textContent('.paw-events__card >> nth=0 >> .paw-events__mon')) === 'DEC' && (await page.textContent('.paw-events__card >> nth=0 >> .paw-events__emoji')) === '🐶' && (await page.textContent('.paw-events__card >> nth=0 >> .paw-events__price')) === '$15 per dog');
    await page.click('[data-paw-events-more]');
    await page.waitForFunction(() => document.querySelectorAll('.paw-events__card').length > 9);
    ok('events: Load more adds the rest, then hides', (await cards()) === 18 && await page.isHidden('[data-paw-events-more]'));
    await shot(page, 'events-list');
    await page.goto(BASE + 'events.html?paw_events=past');
    ok('events: the tabs are links that keep the page', (await page.getAttribute('.paw-events__tab >> nth=2', 'href')) === '/tests/e2e/out/events.html?paw_events=past' && (await page.getAttribute('.paw-events__tab[aria-current="page"]', 'href')).endsWith('paw_events=upcoming'));
    await page.setViewportSize({ width: 390, height: 800 });
    await page.goto(BASE + 'events.html');
    await noScroll(page, 'events list');
    await page.setViewportSize({ width: 1280, height: 900 });

    /* ---------- Event page ---------- */
    await page.goto(BASE + 'event.html');
    const text = await page.textContent('.paw-event');
    const order = ['One Day Only · Saturday, December 19th, 2026', 'X-Mas Party', 'A jingle-bark holiday party for dogs of every breed', '10:00 AM – 2:00 PM', 'Paw-radise Indoor Dog Park, Estero, FL', '$15 per dog', 'Deck the paws!', 'Get Tickets — $15', 'FREE for Dog Park Members', "Pick Your Pup's Session", 'Session 1: Small dogs (under 30 lbs)', 'Everything Your $15 Ticket Includes', 'Raffle prizes', "Extra Goodies You'll Love", 'Holiday Photo Booth', 'Good to Know', '1 Bite Policy', 'Reserve Your Spot Today!', 'Saturday, December 19th, 2026, 10:00 AM – 2:00 PM — $15 per dog'];
    let at = -1;
    const missing = order.filter(p => { const i = text.indexOf(p, at + 1); if (i < 0) return true; at = i; return false; });
    ok('event page: the X-Mas Party, top to bottom', missing.length === 0 || (console.log(missing), false));
    ok('event page: phone numbers are tap-to-call', (await page.locator('.paw-event a[href="tel:2394207418"]').count()) === 3);
    await page.click('[data-paw-ticket]');
    await page.waitForTimeout(400);
    ok('event page: Get Tickets with no ticket link goes to the closing block', page.url().endsWith('#paw-event-reserve') && (await page.textContent('#paw-event-reserve')).includes('Sign-ups open soon'));
    await page.click('.paw-event__addcal summary');
    ok('event page: Add to calendar', (await page.getAttribute('.paw-event__addcal a >> nth=0', 'href')).includes('calendar.google.com') && (await page.getAttribute('.paw-event__addcal a >> nth=2', 'href')).endsWith('?paw_ics=1'));
    await shot(page, 'event-page');
    await page.setViewportSize({ width: 390, height: 800 });
    await page.goto(BASE + 'event.html');
    await noScroll(page, 'event page');
    await shot(page, 'event-page-phone');

    ok('no script errors', errors.length === 0 || (console.log(errors), false));
    console.log(`\nall ${passed} passed`);
  } finally {
    await browser.close();
    server.kill();
  }
})().catch(e => { console.error('FAIL - ' + e.message); process.exit(1); });
