// src/pages/Admin.tsx
import React from 'react'
import { useMe } from '@/api/auth'
import { loadAvail, saveAvail, type AvailMap, type AvailWriteMap } from '@/api/avail'
import { useUsers, useUserMap } from '@/hooks/useUsers'
import client from '@/api/client'

const DAYS: Array<'samedi' | 'dimanche'> = ['samedi', 'dimanche']
const HOURS = [
  '07:00','07:30','08:00','08:30','09:00','09:30','10:00','10:30',
  '11:00','11:30','12:00','12:30','13:00','13:30','14:00','14:30',
  '15:00','15:30','16:00','16:30','17:00','17:30','18:00','18:30',
  '19:00','19:30','20:00'
]
const keyOf = (day: string, hhmm: string) => `${day} ${hhmm}`

type MinimalUser = {
  email: string
  role?: 'admin' | 'user'
  is_active?: number
  poste?: 'juge' | 'build' | 'staff' | null
  name?: string
}

type HeatLine = { equipe?: string; juge?: string; build?: string }
type Heat = { day?: string; jour?: string; start?: string; heure?: string; lignes?: HeatLine[] }

export default function Admin() {
  const { me } = useMe()
  const { users } = useUsers()
  const { resolve } = useUserMap()

  const [selectedEmail, setSelectedEmail] = React.useState<string>('')
  const [map, setMap] = React.useState<AvailMap>({})
  const [loading, setLoading] = React.useState(false)
  const [saving, setSaving] = React.useState(false)
  const [msg, setMsg] = React.useState<string | null>(null)
  const [err, setErr] = React.useState<string | null>(null)

  type Brush = 'none' | 'dispo'
  const [brush, setBrush] = React.useState<Brush>('dispo')
  const [painting, setPainting] = React.useState(false)
  const [brushEnabled, setBrushEnabled] = React.useState(true)

  const [asJuge, setAsJuge] = React.useState<Record<string, true>>({})
  const [asBuild, setAsBuild] = React.useState<Record<string, true>>({})

  const [selectedUser, setSelectedUser] = React.useState<MinimalUser | null>(null)

  // refs pour drag horizontal PAR jour
  const scrollRefs = React.useRef<Record<string, HTMLDivElement | null>>({})
  const touchRefs = React.useRef<Record<string, { x: number; scrollLeft: number } | null>>({})

  // mobile: pinceau off par défaut
  React.useEffect(() => {
    if (typeof window !== 'undefined' && 'ontouchstart' in window) {
      setBrushEnabled(false)
    }
  }, [])

  // users triés
  const sortedUsers = React.useMemo(() => {
    const list = [...users]
    list.sort((a: any, b: any) => {
      const la = (resolve(a.email, true) || a.email).toLowerCase()
      const lb = (resolve(b.email, true) || b.email).toLowerCase()
      return la.localeCompare(lb)
    })
    return list
  }, [users, resolve])

  const hydrateSelectedUser = React.useCallback((email: string) => {
    const raw = users.find(u => (u.email || '').toLowerCase() === email.toLowerCase())
    if (!raw) {
      setSelectedUser(null)
      return
    }
    const role = raw.role === 'admin' ? 'admin' : 'user'
    const poste = raw.poste === 'juge' || raw.poste === 'build' || raw.poste === 'staff' ? raw.poste : null
    setSelectedUser({
      email: raw.email.toLowerCase(),
      role,
      poste,
      is_active: typeof raw.is_active === 'number' ? raw.is_active : (raw.is_active ? 1 : 0),
      name: raw.name
    })
  }, [users])

  const doLoadAvail = async (email: string) => {
    setLoading(true); setErr(null); setMsg(null)
    try {
      const m = await loadAvail(email)
      setMap(m)
    } finally {
      setLoading(false)
    }
  }

  const doLoadRoleOverlay = async (email: string) => {
    try {
      const data: any = await client.request('/heats_get.php', { headers: { 'Cache-Control': 'no-store' } })
      const heats: Heat[] = Array.isArray(data?.heats) ? data.heats : (Array.isArray(data) ? data : [])
      const jj: Record<string, true> = {}
      const bb: Record<string, true> = {}
      for (const h of heats) {
        const day = String(h.day ?? h.jour ?? '').toLowerCase()
        const start = String(h.start ?? h.heure ?? '')
        if (!day || !start) continue
        const key = `${day} ${start}`
        for (const ln of (h.lignes ?? [])) {
          if ((ln.juge || '').toLowerCase() === email.toLowerCase()) jj[key] = true
          if ((ln.build || '').toLowerCase() === email.toLowerCase()) bb[key] = true
        }
      }
      setAsJuge(jj)
      setAsBuild(bb)
    } catch {
      setAsJuge({})
      setAsBuild({})
    }
  }

  // init
  React.useEffect(() => {
    if (!selectedEmail && sortedUsers.length) {
      const first = (sortedUsers[0]?.email || '').toLowerCase()
      setSelectedEmail(first)
      void doLoadAvail(first)
      void doLoadRoleOverlay(first)
      hydrateSelectedUser(first)
    }
  }, [sortedUsers, selectedEmail, hydrateSelectedUser])

  const gotoDelta = (delta: number) => {
    if (!sortedUsers.length) return
    const emails = sortedUsers.map(u => (u.email || '').toLowerCase()).filter(Boolean)
    const i = Math.max(0, emails.indexOf(selectedEmail.toLowerCase()))
    const j = (i + delta + emails.length) % emails.length
    const next = emails[j]
    setSelectedEmail(next)
    void doLoadAvail(next)
    void doLoadRoleOverlay(next)
    hydrateSelectedUser(next)
  }

  const toggleCell = (day: 'samedi' | 'dimanche', hhmm: string, value?: Brush) => {
    const k = keyOf(day, hhmm)
    setMap(prev => {
      const v = value ?? (prev[k] === 'dispo' ? 'none' : 'dispo')
      return { ...prev, [k]: v }
    })
  }

  const onSave = async () => {
    if (!selectedEmail) return
    setSaving(true); setErr(null); setMsg(null)
    try {
      const out: AvailWriteMap = {}
      Object.entries(map).forEach(([k, v]) => { if (v === 'dispo') out[k] = 'dispo' })
      await saveAvail(out, selectedEmail)
      setMsg('Disponibilités enregistrées ✅')
      await doLoadAvail(selectedEmail)
      await doLoadRoleOverlay(selectedEmail)
    } catch (e: any) {
      setErr(e?.message || 'Erreur à l’enregistrement')
    } finally {
      setSaving(false)
    }
  }

  async function updateUser(payload: Partial<MinimalUser>) {
    if (!selectedEmail) return
    setErr(null); setMsg(null)
    try {
      const res = await fetch('/la-hache-contest/api/users_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ email: selectedEmail, ...payload }),
      })
      if (!res.ok) throw new Error(`HTTP ${res.status}`)
      setSelectedUser(prev => (prev ? { ...prev, ...payload } : prev))
      setMsg('Modification enregistrée ✅')
    } catch (e: any) {
      setErr(e?.message || 'Erreur mise à jour utilisateur')
    }
  }

  const toggleActive = async () => {
    if (!selectedUser) return
    await updateUser({ is_active: selectedUser.is_active ? 0 : 1 })
  }

  const toggleAdmin = async () => {
    if (!selectedUser) return
    const isSelf = (me?.email || '').toLowerCase() === selectedEmail.toLowerCase()
    const next = selectedUser.role === 'admin' ? 'user' : 'admin'
    if (isSelf && next === 'user') return
    await updateUser({ role: next })
  }

  const setPoste = async (poste: 'juge' | 'build' | 'staff' | null) => {
    await updateUser({ poste })
  }

  const deleteUser = async () => {
    if (!selectedEmail) return
    if (!window.confirm(`Supprimer le bénévole ${selectedEmail} ?`)) return
    try {
      const res = await fetch('/la-hache-contest/api/admin_delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ email: selectedEmail }),
      })
      if (!res.ok) throw new Error(`HTTP ${res.status}`)
      setMsg(`Bénévole ${selectedEmail} supprimé 🗑️`)
    } catch (e: any) {
      setErr(e?.message || 'Erreur suppression bénévole')
    }
  }

  const roleBtnStyle = (active: boolean): React.CSSProperties =>
    active
      ? { border: '2px solid #111', boxShadow: '0 0 0 1px #111 inset', background: '#fff' }
      : {}

  // handlers de drag pour chaque jour
  const makeTouchHandlers = (day: string) => ({
    onTouchStart: (e: React.TouchEvent) => {
      const el = scrollRefs.current[day]
      if (!el) return
      const t = e.touches[0]
      touchRefs.current[day] = { x: t.clientX, scrollLeft: el.scrollLeft }
    },
    onTouchMove: (e: React.TouchEvent) => {
      const el = scrollRefs.current[day]
      const st = touchRefs.current[day]
      if (!el || !st) return
      const t = e.touches[0]
      const dx = t.clientX - st.x
      el.scrollLeft = st.scrollLeft - dx
    },
    onTouchEnd: () => {
      touchRefs.current[day] = null
    }
  })

  return (
    <div className="pastel-card space-y-3">
      {/* barre d'outils */}
      <div className="flex items-center gap-2 flex-wrap">
        <h1 className="text-xl font-semibold">Admin — Disponibilités bénévoles</h1>

        <select
          className="input"
          value={selectedEmail}
          onChange={e => {
            const v = (e.target.value || '').toLowerCase()
            setSelectedEmail(v)
            void doLoadAvail(v)
            void doLoadRoleOverlay(v)
            hydrateSelectedUser(v)
          }}
          style={{ minWidth: 240 }}
        >
          {sortedUsers.map((u: any) => {
            const e = (u.email || '').toLowerCase()
            return (
              <option key={e} value={e}>
                {resolve(e, true)}
              </option>
            )
          })}
        </select>

        {/* navigation */}
        <button className="tool" onClick={() => gotoDelta(-1)} title="Bénévole précédent">🔼</button>
        <button className="tool" onClick={() => gotoDelta(1)} title="Bénévole suivant">🔽</button>

        {selectedUser && (
          <>
            {/* activer/désactiver */}
            <button
              className="btn"
              onClick={toggleActive}
              title={selectedUser.is_active ? 'Désactiver' : 'Activer'}
            >
              {selectedUser.is_active ? '🚫' : '✅'}
            </button>

            {/* admin */}
            {selectedUser.role === 'admin'
              ? (me?.email || '').toLowerCase() !== selectedUser.email && (
                  <button className="btn" onClick={toggleAdmin} title="Retirer admin">⬇️</button>
                )
              : (
                  <button className="btn" onClick={toggleAdmin} title="Promouvoir admin">⬆️</button>
                )
            }

            {/* rôles */}
            <div className="flex items-center gap-1" title="Définir le poste">
              <button
                className="btn"
                style={roleBtnStyle(selectedUser.poste === 'juge')}
                onClick={() => setPoste('juge')}
              >⚖️</button>
              <button
                className="btn"
                style={roleBtnStyle(selectedUser.poste === 'build')}
                onClick={() => setPoste('build')}
              >🔧</button>
              <button
                className="btn"
                style={roleBtnStyle(selectedUser.poste === 'staff')}
                onClick={() => setPoste('staff')}
              >⭐</button>
            </div>

            {/* supprimer */}
            {(me?.email || '').toLowerCase() !== selectedUser.email && (
              <button className="btn" onClick={deleteUser} title="Supprimer bénévole">🗑️</button>
            )}
          </>
        )}

        {/* sauvegarder */}
        <button
          onClick={onSave}
          disabled={saving || loading || !selectedEmail}
          className="btn"
          title="Enregistrer"
        >
          {saving ? '…' : '💾'}
        </button>

        {/* 🎨 pinceau visible */}
        <button
          onClick={() => setBrushEnabled(b => !b)}
          style={{
            backgroundColor: brushEnabled ? '#22c55e' : '#e5e7eb',
            color: brushEnabled ? '#fff' : '#111827',
            border: '1px solid rgba(0,0,0,.1)',
            borderRadius: 9999,
            padding: '4px 12px',
            fontSize: '0.875rem'
          }}
          title={brushEnabled ? 'Pinceau activé (glisser)' : 'Pinceau désactivé (tap)'}
        >
          🎨
        </button>
      </div>

      {err && <div className="text-red-600">{err}</div>}
      {msg && <div className="text-green-700">{msg}</div>}
      {loading && <div>Chargement…</div>}

      {/* jours empilés à gauche */}
      {!loading && selectedEmail && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem', alignItems: 'flex-start' }}>
          {DAYS.map(day => {
            const handlers = makeTouchHandlers(day)
            return (
              <div key={day} style={{ width: '100%' }}>
                <div className="dispos-day mb-1" style={{ textAlign: 'left' }}>{day}</div>
                <div
                  ref={el => { scrollRefs.current[day] = el }}
                  style={{
                    width: '100%',
                    overflowX: 'scroll',          // desktop
                    WebkitOverflowScrolling: 'touch',
                    touchAction: 'none',          // on gère nous-mêmes sur mobile
                    borderRadius: '0.5rem'
                  }}
                  onTouchStart={handlers.onTouchStart}
                  onTouchMove={handlers.onTouchMove}
                  onTouchEnd={handlers.onTouchEnd}
                  onMouseLeave={() => setPainting(false)}
                >
                  <div
                    style={{
                      display: 'inline-flex',
                      minWidth: 'max-content'
                    }}
                  >
                    {HOURS.map(hhmm => {
                      const k = keyOf(day, hhmm)
                      const v = map[k] === 'dispo' ? 'dispo' : 'none'
                      const showHour = hhmm.endsWith(':00')
                      const overlayClass =
                        asJuge[k] ? 'as-juge'
                        : asBuild[k] ? 'as-build'
                        : ''
                      return (
                        <div
                          key={k}
                          className={`dispos-cell role-${v} ${overlayClass}`}
                          onMouseDown={brushEnabled ? (e) => { e.preventDefault(); setPainting(true); toggleCell(day, hhmm, brush) } : undefined}
                          onMouseUp={() => setPainting(false)}
                          onMouseEnter={brushEnabled && painting ? () => toggleCell(day, hhmm, brush) : undefined}
                          onClick={!brushEnabled ? () => toggleCell(day, hhmm) : undefined}
                          title={`${day} ${hhmm}`}
                        >
                          {showHour && <span className="hour-label">{hhmm}</span>}
                        </div>
                      )
                    })}
                  </div>
                </div>
                <div className="text-xs text-neutral-400 mt-1 md:hidden">
                  ← faites défiler horizontalement →
                </div>
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}
