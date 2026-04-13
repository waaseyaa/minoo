const BASE = '/admin/api/newsletter'

async function request(path, options = {}) {
  const url = `${BASE}${path}`
  const headers = { 'Content-Type': 'application/json', ...options.headers }
  const res = await fetch(url, { ...options, headers })
  if (!res.ok) {
    const body = await res.text()
    throw new Error(`API ${res.status}: ${body}`)
  }
  if (res.status === 204) return null
  return res.json()
}

export function listEditions() {
  return request('')
}

export function getEdition(id) {
  return request(`/${id}`)
}

export function createEdition(data) {
  return request('', { method: 'POST', body: JSON.stringify(data) })
}

export function addItem(editionId, data) {
  return request(`/${editionId}/items`, { method: 'POST', body: JSON.stringify(data) })
}

export function removeItem(editionId, itemId) {
  return request(`/${editionId}/items/${itemId}`, { method: 'DELETE' })
}

export function reorderItem(editionId, itemId, position) {
  return request(`/${editionId}/items/${itemId}/reorder`, {
    method: 'POST',
    body: JSON.stringify({ position }),
  })
}

export function entitySearch(query, types) {
  const params = new URLSearchParams({ q: query })
  if (types) params.set('types', types)
  return request(`/entity-search?${params}`)
}

export function getPreviewToken(id) {
  return request(`/${id}/preview-token`)
}

export function generate(id) {
  return request(`/${id}/generate`, { method: 'POST' })
}

export function downloadUrl(id) {
  return `${BASE}/${id}/download`
}

export function send(id) {
  return request(`/${id}/send`, { method: 'POST' })
}
