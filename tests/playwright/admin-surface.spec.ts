import { test, expect } from '@playwright/test';

test.describe('Admin Surface API', () => {
  test('session endpoint returns 401 for unauthenticated request', async ({ request }) => {
    const response = await request.get('/admin/surface/session');
    expect(response.status()).toBe(401);
    const body = await response.json();
    expect(body.errors[0].status).toBe('401');
  });

  test('catalog endpoint returns 401 for unauthenticated request', async ({ request }) => {
    const response = await request.get('/admin/surface/catalog');
    expect(response.status()).toBe(401);
  });

  test('entity list endpoint returns 401 for unauthenticated request', async ({ request }) => {
    const response = await request.get('/admin/surface/event');
    expect(response.status()).toBe(401);
  });

  test('admin SPA entry returns 401 for unauthenticated request', async ({ request }) => {
    const response = await request.get('/admin');
    expect(response.status()).toBe(401);
  });
});
