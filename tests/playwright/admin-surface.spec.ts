import { test, expect } from '@playwright/test';

test.describe('Admin Surface API', () => {
  test('session endpoint rejects unauthenticated request', async ({ request }) => {
    const response = await request.get('/admin/surface/session');
    expect([401, 404]).toContain(response.status());
  });

  test('catalog endpoint rejects unauthenticated request', async ({ request }) => {
    const response = await request.get('/admin/surface/catalog');
    expect([401, 404]).toContain(response.status());
  });

  test('entity list endpoint rejects unauthenticated request', async ({ request }) => {
    const response = await request.get('/admin/surface/event');
    expect([401, 404]).toContain(response.status());
  });

  test('admin SPA entry rejects unauthenticated request', async ({ request }) => {
    const response = await request.get('/admin');
    expect([401, 404]).toContain(response.status());
  });
});
