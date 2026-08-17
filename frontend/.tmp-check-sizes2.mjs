import { chromium } from 'playwright';

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1280, height: 1000 } });
await page.goto('http://localhost:5173/', { waitUntil: 'networkidle' });

const cookieBtn = page.locator('.cookie-banner button', { hasText: 'Приеми всички' });
if (await cookieBtn.count()) await cookieBtn.click();

for (let i = 0; i < 60; i++) {
  await page.mouse.wheel(0, 400);
  await page.waitForTimeout(40);
}

const stepTitle = page.locator('.funnel-step-card h3').first();
const scienceTitle = page.locator('#science h3').first();

for (const [label, loc] of [['step', stepTitle], ['science', scienceTitle]]) {
  const styles = await loc.evaluate(el => {
    const cs = getComputedStyle(el);
    return { fontSize: cs.fontSize, fontWeight: cs.fontWeight, fontFamily: cs.fontFamily, textTransform: cs.textTransform, height: el.getBoundingClientRect().height };
  });
  console.log(label, styles);
}

await browser.close();
