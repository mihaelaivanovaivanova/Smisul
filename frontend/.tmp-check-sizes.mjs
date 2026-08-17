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
const sourceLink = page.locator('.funnel-science-card__source').first();

console.log('step title font-size:', await stepTitle.evaluate(el => getComputedStyle(el).fontSize));
console.log('science title font-size:', await scienceTitle.evaluate(el => getComputedStyle(el).fontSize));
console.log('source link font-size:', await sourceLink.evaluate(el => getComputedStyle(el).fontSize));
console.log('source link line-height:', await sourceLink.evaluate(el => getComputedStyle(el).lineHeight));

await browser.close();
