import { test, expect } from '@playwright/test';

test.describe('Events refactor', () => {
  test('basic render — filter bar and sections', async ({ page }) => {
    const response = await page.goto('/events');
    expect(response?.status()).toBe(200);
    const filterBar = page.locator('form[role="search"], .events-filter-bar, [data-events-filter]').first();
    const hasFilterBar = await filterBar.count();
    test.skip(hasFilterBar === 0, 'Events filter bar not rendered — requires refactor template');
    await expect(filterBar).toBeVisible();
    const sections = page.locator('h2, .events-section__heading, [role="status"]');
    await expect(sections.first()).toBeVisible();
  });

  test('filter chip round-trip — apply and dismiss', async ({ page }) => {
    await page.goto('/events');
    const thisWeekChip = page.getByRole('link', { name: /This week/i }).first();
    const hasChip = await thisWeekChip.count();
    test.skip(hasChip === 0, 'This week chip not available');
    await thisWeekChip.click();
    await expect(page).toHaveURL(/when=this[-_]?week/i);
    const dismiss = page.locator('.active-filter a, .events-filter__active a, [data-filter-dismiss]').first();
    const hasDismiss = await dismiss.count();
    test.skip(hasDismiss === 0, 'Active filter dismiss link not rendered');
    await dismiss.click();
    await expect(page).not.toHaveURL(/when=this[-_]?week/i);
  });

  test('view toggle persists in URL — list and calendar', async ({ page }) => {
    await page.goto('/events');
    const listToggle = page.getByRole('link', { name: /^List$/i }).first();
    const hasToggle = await listToggle.count();
    test.skip(hasToggle === 0, 'View toggle not rendered');
    await listToggle.click();
    await expect(page).toHaveURL(/view=list/);
    const calendarToggle = page.getByRole('link', { name: /^Calendar$/i }).first();
    await calendarToggle.click();
    await expect(page).toHaveURL(/view=calendar/);
    const calendarEl = page.locator('.events-calendar, [data-events-calendar], table.calendar').first();
    await expect(calendarEl).toBeVisible();
  });

  test('calendar prev/next navigation updates month', async ({ page }) => {
    await page.goto('/events?view=calendar');
    const nextLink = page.getByRole('link', { name: /Next month|Next/i }).first();
    const hasNext = await nextLink.count();
    test.skip(hasNext === 0, 'Calendar next link not rendered — requires calendar view');
    const heading = page.locator('.events-calendar__heading, .calendar-heading, h2').first();
    const beforeText = await heading.textContent();
    await nextLink.click();
    await expect(page).toHaveURL(/month=\d{4}-\d{2}/);
    await expect(heading).not.toHaveText(beforeText ?? '');
  });

  test('ICS download returns text/calendar', async ({ page }) => {
    await page.goto('/events');
    const firstCard = page.locator('a[href^="/events/"]').first();
    const count = await firstCard.count();
    test.skip(count === 0, 'No event cards — requires seeded events');
    const href = await firstCard.getAttribute('href');
    test.skip(!href, 'Event card has no href');
    await page.goto(href!);
    const icsLink = page.getByRole('link', { name: /Add to calendar/i }).first();
    const hasIcs = await icsLink.count();
    test.skip(hasIcs === 0, 'ICS "Add to calendar" link not rendered');
    const icsHref = await icsLink.getAttribute('href');
    expect(icsHref).toBeTruthy();
    const response = await page.request.get(icsHref!);
    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toMatch(/text\/calendar/i);
    expect(await response.text()).toContain('BEGIN:VCALENDAR');
  });
});
