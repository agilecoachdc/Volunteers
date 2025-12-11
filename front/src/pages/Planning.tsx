// src/pages/Planning.tsx
import React from 'react'
import { getHeats, type Heat } from '@/api/heats'
import { useUserMap } from '@/hooks/useUsers'
import { useMe } from '@/api/auth'

function wodNum(w: string) {
  const m = /(\d+)/.exec(w || '')
  return m ? parseInt(m[1], 10) : 0
}
function byWodHeat(a: Heat, b: Heat) {
  const wa = wodNum(a.wod), wb = wodNum(b.wod)
  return wa !== wb ? wa - wb : (a.heat || 0) - (b.heat || 0)
}
function groupByWod(heats: Heat[]) {
  const m = new Map<string, Heat[]>()
  for (const h of heats) {
    const key = h.wod || 'WOD ?'
    if (!m.has(key)) m.set(key, [])
    m.get(key)!.push(h)
  }
  for (const [, arr] of m) arr.sort(byWodHeat)
  return Array.from(m.entries()).sort((a, b) => wodNum(a[0]) - wodNum(b[0]))
}
function maxLanes(heats: Heat[]) {
  let n = 0
  for (const h of heats) n = Math.max(n, h.lignes?.length || 0)
  return n
}

export default function Planning() {
  const [heats, setHeats] = React.useState<Heat[]>([])
  const [loading, setLoading] = React.useState(true)
  const [error, setError] = React.useState<string | null>(null)

  const { resolve, users } = useUserMap()
  const { me } = useMe()
  const meEmail = (me?.email || '').toLowerCase()
  const [selectedEmail, setSelectedEmail] = React.useState<string>(meEmail)

  React.useEffect(() => {
    (async () => {
      setLoading(true); setError(null)
      try {
        const arr = await getHeats()
        setHeats(arr)
      } catch (e: any) {
        setError(e?.message || 'Erreur de chargement')
      } finally {
        setLoading(false)
      }
    })()
  }, [])

  React.useEffect(() => {
    if (meEmail) setSelectedEmail(meEmail)
  }, [meEmail])

  const allEmails = React.useMemo(() => {
    const s = new Set<string>()
    users.forEach(u => { if (u.email) s.add(u.email.toLowerCase()) })
    return Array.from(s).sort((a, b) => resolve(a, true).localeCompare(resolve(b, true)))
  }, [users, resolve])

  const groups = groupByWod(heats)
  const lanes = maxLanes(heats)
  const email = (selectedEmail || '').toLowerCase()

  const chipBg = (isJ: boolean, isB: boolean) =>
    isJ
      ? { background: 'var(--role-juge-bg)', color: 'var(--role-juge-fg)' }
      : isB
        ? { background: 'var(--role-build-bg)', color: 'var(--role-build-fg)' }
        : undefined

  const short = (v?: string) => {
    const e = (v || '').toLowerCase()
    if (!e) return ''
    return resolve(e, true) || v || ''
  }

  // navigation 🔼 / 🔽
  const gotoPrev = () => {
    if (!allEmails.length) return
    const idx = Math.max(0, allEmails.indexOf(email))
    const prev = (idx - 1 + allEmails.length) % allEmails.length
    setSelectedEmail(allEmails[prev])
  }
  const gotoNext = () => {
    if (!allEmails.length) return
    const idx = Math.max(0, allEmails.indexOf(email))
    const next = (idx + 1) % allEmails.length
    setSelectedEmail(allEmails[next])
  }

  return (
    <div className="pastel-card space-y-3 planning-dense">
      <div className="flex items-center gap-2 flex-wrap">
        <h1 className="text-xl font-semibold">Planning</h1>
        <select
          className="input"
          value={selectedEmail}
          onChange={e => setSelectedEmail((e.target.value || '').toLowerCase())}
          // ⬇️ moins large pour que les boutons ne sortent pas du cadre
          style={{ minWidth: 150, maxWidth: 200 }}
          title="Choisir un bénévole"
        >
          {allEmails.map(e => (
            <option key={e} value={e}>
              {resolve(e, true)} {e === meEmail ? '(moi)' : ''}
            </option>
          ))}
        </select>

        {/* 🔼 / 🔽 navigation bénévoles */}
        <button className="btn" onClick={gotoPrev} title="Bénévole précédent">🔼</button>
        <button className="btn" onClick={gotoNext} title="Bénévole suivant">🔽</button>

        {/* Légende */}
        <div className="ml-auto legend" style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          <span><span className="legend-swatch" style={{ background: 'var(--role-juge-bg)' }}></span>Juge</span>
          <span><span className="legend-swatch" style={{ background: 'var(--role-build-bg)' }}></span>Build</span>
        </div>
      </div>

      {loading && <div>Chargement…</div>}
      {error && <div className="text-red-600">{error}</div>}

      {/* ⬇️ ICI on aligne sur la structure de Heats pour que SEUL le tableau défile */}
      {!loading && !error && groups.map(([wod, arr]) => (
        <div key={wod} className="pastel-card">
          <h2 className="text-xl font-semibold mb-2">{wod}</h2>

          <div className="w-full overflow-x-auto">
            <div className="inline-block min-w-full align-middle">
              <div className="overflow-x-auto">
                {/* contenu plus large que l'écran → scroll dans le cadre */}
                <table className="table text-sm" style={{ minWidth: 900 }}>
                  <thead>
                    <tr className="text-left">
                      <th style={{ width: 80 }}>Jour</th>
                      <th style={{ width: 60 }}>Heure</th>
                      <th style={{ width: 40 }}>#</th>
                      {Array.from({ length: lanes }, (_, i) => <th key={i}>Lane {i + 1}</th>)}
                    </tr>
                  </thead>
                  <tbody>
                    {arr.map(h => (
                      <tr key={`${h.wod}-${h.heat}-${h.day}-${h.start}`}>
                        <td>{h.day}</td>
                        <td>{h.start}</td>
                        <td>{h.heat}</td>
                        {Array.from({ length: lanes }, (_, lane) => {
                          const ln = h.lignes[lane]
                          if (!ln) return <td key={lane}></td>
                          const isJ = (ln.juge || '').toLowerCase() === email
                          const isB = (ln.build || '').toLowerCase() === email
                          const bgStyle = chipBg(isJ, isB)
                          return (
                            <td key={lane}>
                              <div className="slot-card cell-compact" style={bgStyle}>
                                <div className="line-compact" style={{ marginBottom: 2 }}>
                                  {ln.equipe ? <span className="chip">{ln.equipe}</span> : <span className="chip">—</span>}
                                </div>
                                <div className="line-compact" style={{ marginBottom: 2 }}>
                                  {ln.juge ? <span className="chip chip-juge">{short(ln.juge)}</span> : <span className="chip">—</span>}
                                </div>
                                <div className="line-compact">
                                  {ln.build ? <span className="chip chip-build">{short(ln.build)}</span> : <span className="chip">—</span>}
                                </div>
                              </div>
                            </td>
                          )
                        })}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </div>

        </div>
      ))}
    </div>
  )
}
