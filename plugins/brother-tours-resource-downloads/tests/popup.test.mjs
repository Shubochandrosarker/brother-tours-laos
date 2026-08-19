import { chromium } from 'playwright';
import { fileURLToPath } from 'url';
import path from 'path';
import fs from 'fs';

const here = path.dirname(fileURLToPath(import.meta.url));
const dir = path.join(here, 'harness');
// Test against the shipped assets, not a stale copy.
for (const f of ['bt-resource.css', 'bt-resource.js']) {
  fs.copyFileSync(path.join(here, '..', 'assets', f.endsWith('.css') ? 'css' : 'js', f), path.join(dir, f));
}
fs.writeFileSync(path.join(dir, 'fake.pdf'), 'fake');
const url = 'file://' + dir + '/index.html';
let pass = 0, fail = 0;
const ok  = (m) => { console.log('  PASS  ' + m); pass++; };
const bad = (m) => { console.log('  FAIL  ' + m); fail++; };

const browser = await chromium.launch({
  executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});

async function fresh() {
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await ctx.newPage();
  // Any native alert() would be a regression; record it if it ever fires.
  page.on('dialog', async d => { bad('native dialog fired: ' + d.type()); await d.dismiss(); });
  await page.route('**/fake.pdf', route => route.fulfill({ status: 200, contentType: 'application/pdf', body: 'fake' }));
  await page.goto(url);
  await page.evaluate(() => {
    document.addEventListener('click', function (e) {
      var a = e.target.closest('a[href$=".pdf"]');
      if (a) { e.preventDefault(); }
    }, false);
  });
  return { ctx, page };
}
const isOpen = (page) => page.evaluate(() =>
  !document.querySelector('[data-btrd-overlay]').hasAttribute('hidden'));

console.log('\n-- trigger timing (the change requested) --');
{
  const { ctx, page } = await fresh();
  await page.waitForTimeout(2500);
  (await isOpen(page)) ? bad('opened at 2.5s — the legacy 2s behaviour is back') : ok('still closed at 2.5s (no 2-second interruption)');
  await page.waitForTimeout(5000);
  (await isOpen(page)) ? bad('opened at 7.5s, before the 10s delay') : ok('still closed at 7.5s');
  await ctx.close();
}

console.log('\n-- timed trigger requires engagement --');
{
  const { ctx, page } = await fresh();
  await page.waitForTimeout(11000);
  (await isOpen(page)) ? bad('opened at 11s with no engagement') : ok('idle visitor never interrupted at 11s');
  await ctx.close();
}
{
  const { ctx, page } = await fresh();
  await page.mouse.move(400, 400);
  await page.mouse.down(); await page.mouse.up();          // engagement
  await page.waitForTimeout(11000);
  (await isOpen(page)) ? ok('engaged visitor sees it after 10s') : bad('engaged visitor never saw it');
  await ctx.close();
}

console.log('\n-- scroll trigger at 40% --');
{
  const { ctx, page } = await fresh();
  await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight * 0.15));
  await page.waitForTimeout(400);
  (await isOpen(page)) ? bad('opened at 15% scroll') : ok('closed at 15% scroll');
  await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight * 0.55));
  await page.waitForTimeout(400);
  (await isOpen(page)) ? ok('opened at 55% scroll (>=40%), well before the 10s timer') : bad('did not open on scroll');
  await ctx.close();
}

console.log('\n-- accessibility --');
{
  const { ctx, page } = await fresh();
  await page.click('#manual');
  await page.waitForTimeout(300);
  (await isOpen(page)) ? ok('manual button opens it') : bad('manual button did not open it');
  const focusIn = await page.evaluate(() => document.querySelector('[data-btrd-dialog]').contains(document.activeElement));
  focusIn ? ok('focus moves into the dialog') : bad('focus did not move into the dialog');

  // Tab from the last control should wrap to the first, not escape the dialog.
  await page.keyboard.press('Tab'); await page.keyboard.press('Tab'); await page.keyboard.press('Tab');
  const stillIn = await page.evaluate(() => document.querySelector('[data-btrd-dialog]').contains(document.activeElement));
  stillIn ? ok('focus stays trapped while open') : bad('focus escaped the dialog');

  await page.keyboard.press('Escape');
  await page.waitForTimeout(400);
  (await isOpen(page)) ? bad('ESC did not close') : ok('ESC closes');
  const restored = await page.evaluate(() => document.activeElement && document.activeElement.id === 'manual');
  restored ? ok('focus returns to the opening button') : bad('focus not restored');
  await ctx.close();
}

console.log('\n-- body scroll lock --');
{
  const { ctx, page } = await fresh();
  await page.evaluate(() => window.scrollTo(0, 900));
  // Open programmatically: page.click() auto-scrolls the button into view
  // first, which would move the page before the lock captures its position.
  await page.evaluate(() => window.BTResourcePopup.open('manual'));
  await page.waitForTimeout(300);
  const locked = await page.evaluate(() => getComputedStyle(document.body).position === 'fixed');
  locked ? ok('background scroll locked while open') : bad('background not locked');
  await page.keyboard.press('Escape');
  await page.waitForTimeout(400);
  const restored = await page.evaluate(() => Math.abs(window.scrollY - 900) < 4 && document.body.style.position === '');
  restored ? ok('scroll position restored on close (iOS-safe lock)') : bad('scroll position lost on close');
  await ctx.close();
}

console.log('\n-- toast instead of alert (the other change requested) --');
{
  const { ctx, page } = await fresh();
  await page.click('#manual');
  await page.waitForTimeout(200);
  await page.click('[data-btrd-download]');
  await page.waitForTimeout(400);
  const t = await page.evaluate(() => {
    const el = document.querySelector('[data-btrd-toast]');
    return { hidden: el.hasAttribute('hidden'), visible: el.classList.contains('is-visible'), text: el.textContent };
  });
  (!t.hidden && t.visible && /downloading/i.test(t.text)) ? ok('toast shown after download: "' + t.text + '"') : bad('toast did not appear');
  await ctx.close();
}

console.log('\n-- frequency suppression --');
{
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await page.goto(url);
  await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight * 0.6));
  await page.waitForTimeout(400);
  (await isOpen(page)) ? ok('first visit: auto-opened') : bad('first visit did not open');
  await page.keyboard.press('Escape');
  await page.waitForTimeout(400);
  // Same context = same session + localStorage, as a returning visitor.
  await page.goto(url);
  await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight * 0.6));
  await page.waitForTimeout(600);
  (await isOpen(page)) ? bad('re-opened after dismissal — visitor annoyed') : ok('second visit: suppressed after dismissal');
  await page.click('#manual');
  await page.waitForTimeout(300);
  (await isOpen(page)) ? ok('manual button still works despite suppression') : bad('manual button broken by suppression');
  await ctx.close();
}

console.log('\n-- analytics + attribution --');
{
  const { ctx, page } = await fresh();
  await page.click('#manual');
  await page.waitForTimeout(200);
  await page.click('[data-btrd-download]');
  await page.waitForTimeout(300);
  const events = await page.evaluate(() => window.dataLayer.map(e => e.event));
  events.includes('resource_popup_view') ? ok('resource_popup_view tracked') : bad('popup_view missing');
  events.includes('resource_download') ? ok('resource_download tracked') : bad('download missing');
  const href = await page.evaluate(() => {
    const a = document.querySelector('#bmt');
    a.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    return a.getAttribute('href');
  });
  /source_resource=lcr-guide/.test(href) ? ok('Build My Trip carries source_resource: ' + href) : bad('attribution not added: ' + href);
  await ctx.close();
}

await browser.close();
console.log('\n' + pass + ' passed, ' + fail + ' failed');
process.exit(fail ? 1 : 0);
