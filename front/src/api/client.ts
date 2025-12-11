// src/api/client.ts
const BASE = '/la-hache-contest/api'

type ReqInit = RequestInit & { body?: any }

async function parse(res: Response) {
  const ct = res.headers.get('content-type') || ''
  if (ct.includes('application/json')) return await res.json()
  const text = await res.text()
  try { return JSON.parse(text) } catch { return { raw: text } }
}

export default {
  async request(path: string, init: ReqInit = {}) {
    const url = path.startsWith('/') ? `${BASE}${path}` : `${BASE}/${path}`

    const hasBody = init.body !== undefined && init.body !== null
    const headers = new Headers(init.headers || {})
    if (hasBody && !headers.has('Content-Type')) {
      headers.set('Content-Type', 'application/json')
    }

    const res = await fetch(url, {
      method: init.method || (hasBody ? 'POST' : 'GET'),
      credentials: 'include',          // <<=== cookies de session envoyés
      cache: 'no-store',
      headers,
      body: hasBody ? (typeof init.body === 'string' ? init.body : JSON.stringify(init.body)) : undefined,
    })

    const data = await parse(res)
    if (!res.ok) {
      throw new Error((data && (data.error || data.message)) || `HTTP ${res.status}`)
    }
    return data
  }
}
