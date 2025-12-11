// src/api/auth.tsx
import React from 'react'

/* ===================== Types ===================== */

export type Role = 'admin' | 'benevole'
export type Me = { email: string; role: Role } | null

type MeContextShape = {
  me: Me
  loading: boolean
  setMe: React.Dispatch<React.SetStateAction<Me>>
  refresh: () => Promise<void>
}

type MeApiResponse =
  | { auth: true; user: { email: string; role: Role }; csrf?: string | null }
  | { auth: false; user: null; csrf?: string | null }
  | Record<string, unknown>

type LoginResponse =
  | { ok: true; user?: { email: string; role: Role } }
  | { ok: false; error?: string }

type RegisterPayload = {
  first_name?: string
  last_name?: string
  name?: string
  email: string
  password: string
  optin?: boolean
}

type RegisterResponse =
  | { ok: true; user?: { email: string; role: Role } }
  | { ok: false; error?: string }

/* ===================== Helpers ===================== */

const API_BASE = '/la-hache-contest/api'

async function json<T = unknown>(res: Response): Promise<T> {
  const ct = res.headers.get('content-type') || ''
  if (ct.includes('application/json')) {
    return (await res.json()) as T
  }
  const text = await res.text()
  try { return JSON.parse(text) as T } catch { return {} as T }
}

// Type guards
function isMeOk(d: any): d is { auth: true; user: { email: string; role: Role } } {
  return !!d && d.auth === true && d.user && typeof d.user.email === 'string'
}
function isMeNone(d: any): d is { auth: false; user: null } {
  return !!d && d.auth === false && d.user === null
}

/* ===================== Context ===================== */

const MeContext = React.createContext<MeContextShape | null>(null)

export function MeProvider({ children }: { children: React.ReactNode }) {
  const [me, setMe] = React.useState<Me>(null)
  const [loading, setLoading] = React.useState<boolean>(true)

  const refresh = React.useCallback(async () => {
    try {
      setLoading(true)
      const res = await fetch(`${API_BASE}/me.php`, {
        method: 'GET',
        credentials: 'include',
        cache: 'no-store',
        headers: { 'Cache-Control': 'no-store' },
      })
      const data = await json<MeApiResponse>(res)
      if (isMeOk(data)) {
        setMe({ email: data.user.email, role: data.user.role })
      } else if (isMeNone(data)) {
        setMe(null)
      } else {
        setMe(null)
      }
    } catch {
      setMe(null)
    } finally {
      setLoading(false)
    }
  }, [])

  React.useEffect(() => { void refresh() }, [refresh])

  return (
    <MeContext.Provider value={{ me, loading, setMe, refresh }}>
      {children}
    </MeContext.Provider>
  )
}

export function useMe(): MeContextShape {
  const ctx = React.useContext(MeContext)
  if (!ctx) throw new Error('useMe must be used within <MeProvider>')
  return ctx
}

/* ===================== API actions ===================== */

export async function login(email: string, password: string): Promise<LoginResponse> {
  try {
    const res = await fetch(`${API_BASE}/login.php`, {
      method: 'POST',
      credentials: 'include',
      cache: 'no-store',
      headers: {
        'Content-Type': 'application/json',
        'Cache-Control': 'no-store',
      },
      body: JSON.stringify({ email, password }),
    })
    const data = await json<any>(res)
    if (!res.ok || (data && data.ok === false)) {
      return { ok: false, error: (data && data.error) || `HTTP ${res.status}` }
    }
    return { ok: true, user: data?.user }
  } catch (e: any) {
    return { ok: false, error: e?.message || 'network_error' }
  }
}

/** Signatures supportées :
 *   - register({ first_name, last_name, email, password, optin })
 *   - register(name, email, password)
 */
export async function register(payload: RegisterPayload): Promise<RegisterResponse>
export async function register(name: string, email: string, password: string): Promise<RegisterResponse>
export async function register(a: any, b?: any, c?: any): Promise<RegisterResponse> {
  const body: RegisterPayload =
    typeof a === 'string'
      ? { name: a, email: String(b || ''), password: String(c || '') }
      : a

  try {
    const res = await fetch(`${API_BASE}/register.php`, {
      method: 'POST',
      credentials: 'include',
      cache: 'no-store',
      headers: {
        'Content-Type': 'application/json',
        'Cache-Control': 'no-store',
      },
      body: JSON.stringify(body),
    })
    const data = await json<any>(res)
    if (!res.ok || (data && data.ok === false)) {
      return { ok: false, error: (data && data.error) || `HTTP ${res.status}` }
    }
    return { ok: true, user: data?.user }
  } catch (e: any) {
    return { ok: false, error: e?.message || 'network_error' }
  }
}

export async function logout(): Promise<void> {
  try {
    await fetch(`${API_BASE}/logout.php`, {
      method: 'POST',
      credentials: 'include',
      cache: 'no-store',
      headers: { 'Cache-Control': 'no-store' },
    })
  } catch { /* ignore */ }
  try { localStorage.removeItem('csrf') } catch {}
  try { sessionStorage.clear() } catch {}
}

/* ===================== Hooks pratiques ===================== */

export function useAuthActions() {
  const { setMe, refresh } = useMe()

  const doLogin = React.useCallback(
    async (email: string, password: string): Promise<LoginResponse> => {
      const res = await login(email, password)
      if (res.ok) await refresh()
      return res
    },
    [refresh]
  )

  const doRegister = React.useCallback(
    async (payloadOrName: any, email?: string, password?: string): Promise<RegisterResponse> => {
      const res = await register(payloadOrName as any, email as any, password as any)
      if (res.ok) await refresh()
      return res
    },
    [refresh]
  )

  const doLogout = React.useCallback(async () => {
    setMe(null)
    await logout()
  }, [setMe])

  return { doLogin, doRegister, doLogout }
}
