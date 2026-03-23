const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

(async () => {
  const template = fs.readFileSync(path.join(__dirname, 'og-template.html'), 'utf-8');
  const outputPath = path.join(__dirname, '..', 'public', 'img', 'og-default.png');

  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1200, height: 630 });
  await page.setContent(template, { waitUntil: 'load' });
  await page.screenshot({ path: outputPath, type: 'png' });
  await browser.close();

  console.log('OG image generated:', outputPath);
})();
