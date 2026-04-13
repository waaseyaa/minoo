#!/usr/bin/env node
/**
 * Renders a URL to PDF via headless Chromium (Playwright).
 * Usage: node bin/render-pdf.js --url=http://... --out=/path/to/file.pdf
 *
 * Exit codes:
 *   0 — PDF written successfully
 *   1 — Argument error
 *   2 — Navigation/render error
 *   3 — PDF write error
 */
const { chromium } = require('playwright');

function parseArgs(argv) {
    const args = {};
    for (const a of argv.slice(2)) {
        const m = a.match(/^--([^=]+)=(.*)$/);
        if (m) args[m[1]] = m[2];
    }
    return args;
}

(async () => {
    const args = parseArgs(process.argv);
    if (!args.url || !args.out) {
        console.error('Usage: render-pdf.js --url=<url> --out=<path>');
        process.exit(1);
    }

    let browser;
    try {
        browser = await chromium.launch();
        const ctx = await browser.newContext();
        const page = await ctx.newPage();
        const resp = await page.goto(args.url, { waitUntil: 'networkidle', timeout: 30000 });
        if (!resp || !resp.ok()) {
            console.error(`Navigation failed: ${resp ? resp.status() : 'no response'}`);
            process.exit(2);
        }
    } catch (e) {
        console.error(`Render error: ${e.message}`);
        if (browser) await browser.close();
        process.exit(2);
    }

    try {
        const page = browser.contexts()[0].pages()[0];
        await page.pdf({
            path: args.out,
            preferCSSPageSize: true,
            printBackground: true,
        });
    } catch (e) {
        console.error(`PDF write error: ${e.message}`);
        await browser.close();
        process.exit(3);
    }

    await browser.close();
    console.log(`Wrote ${args.out}`);
})();
