// src/pages/MesDispos.tsx
import React from 'react'
import { loadAvail, saveAvail, type AvailMap, type AvailWriteMap } from '@/api/avail'
import DisposGrid, { type Cell } from '@/components/DisposGrid'
import { getHeats, type Heat } from '@/api/heats'
import { useMe } from '@/api/auth'

export default function MesDispos() {
  const [avail, setAvail] = React.useState<AvailMap>({})
  const [loading, setLoading] = React.useState(true)
  const [saving, setSaving] = React.useState(false)

  // --- NEW: données planning pour le récap ---
  const { me } = useMe()
  const meEmail = (me?.email || '').toLowerCase()
  const [heats, setHeats] = React.useState<Heat[]>([])
  const [loadingHeats, setLoadingHeats] = React.useState(true)
  const [errorHeats, setErrorHeats] = React.useState<string | null>(null)

  React.useEffect(() => {
    ;(async () => {
      try {
        const a = await loadAvail()
        setAvail(a)
      } finally {
        setLoading(false)
      }
    })()
  }, [])

  // charge les heats pour construire le récap
  React.useEffect(() => {
    ;(async () => {
      setLoadingHeats(true); setErrorHeats(null)
      try {
        const h = await getHeats()
        setHeats(Array.isArray(h) ? h : [])
      } catch (e: any) {
        setErrorHeats(e?.message || 'Erreur de chargement du planning')
      } finally {
        setLoadingHeats(false)
      }
    })()
  }, [])

  const onChange = (next: Record<string, Cell>) => setAvail(next)

  // Projection restreinte à Cell = 'none' | 'dispo'
  const projectAvail = React.useMemo(() => {
    const out: Record<string, Cell> = {}
    for (const [k, v] of Object.entries(avail)) {
      out[k] = v === 'dispo' ? 'dispo' : 'none'
    }
    return out
  }, [avail])

  const onSave = async () => {
    setSaving(true)
    try {
      await saveAvail(avail as AvailWriteMap)
      alert('Disponibilités sauvegardées ✅')
    } catch (e) {
      alert('Erreur de sauvegarde: ' + (e as Error).message)
    } finally {
      setSaving(false)
    }
  }

  // --- NEW: récap filtré pour l'utilisateur connecté ---
  type Row = { day: string; start: string; wod?: string; heat?: number; lane: number; role: 'juge'|'build'; team?: string }
  const assignedRows = React.useMemo<Row[]>(() => {
    if (!meEmail) return []
    const rows: Row[] = []
    for (const h of heats) {
      const day = String((h as any).day ?? (h as any).jour ?? '').toLowerCase()
      const start = String((h as any).start ?? (h as any).heure ?? '')
      const wod = (h as any).wod
      const heatNum = (h as any).heat
      const lignes = Array.isArray((h as any).lignes) ? (h as any).lignes : []
      lignes.forEach((ln: any, idx: number) => {
        const juge = String(ln?.juge || '').toLowerCase()
        const build = String(ln?.build || '').toLowerCase()
        if (meEmail && (juge === meEmail || build === meEmail)) {
          rows.push({
            day, start, wod, heat: heatNum, lane: idx + 1,
            role: juge === meEmail ? 'juge' : 'build',
            team: ln?.equipe || ''
          })
        }
      })
    }
    // tri: jour, heure, heat, lane
    const keyDay = (d: string) => d === 'samedi' ? 0 : (d === 'dimanche' ? 1 : 2)
    rows.sort((a, b) => {
      const kd = keyDay(a.day) - keyDay(b.day)
      if (kd !== 0) return kd
      if (a.start !== b.start) return (a.start < b.start ? -1 : 1)
      const hh = (a.heat || 0) - (b.heat || 0)
      if (hh !== 0) return hh
      return a.lane - b.lane
    })
    return rows
  }, [heats, meEmail])

  return (
    <div className="w-full h-full overflow-x-auto">
      <div className="max-w-6xl mx-auto bg-white rounded-2xl shadow-md p-4 mt-2 mb-8">
        <h1 className="text-xl font-semibold mb-4">🕒 Mon espace</h1>

        {loading ? (
          <div className="text-sm text-neutral-500">Chargement...</div>
        ) : (
          <div className="overflow-x-auto">
            <DisposGrid value={projectAvail} onChange={onChange} />
          </div>
        )}

        <div className="mt-4 flex justify-center">
          <button
            onClick={onSave}
            disabled={saving}
            className="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700"
          >
            {saving ? 'Sauvegarde...' : '💾 Enregistrer mes disponibilités'}
          </button>
        </div>
      </div>

      {/* --- NEW: Récap des créneaux où l'utilisateur est attendu --- */}
      <div className="pastel-card">
        <h2 className="text-lg font-semibold mb-2">📋 Mes créneaux où je suis attendu</h2>
        {loadingHeats && <div>Chargement…</div>}
        {errorHeats && <div className="text-red-600">{errorHeats}</div>}

        {!loadingHeats && !errorHeats && (
          assignedRows.length === 0 ? (
            <div className="text-neutral-600">Aucun créneau assigné pour l’instant.</div>
          ) : (
            <div className="overflow-x-auto">
              <table className="table text-sm" style={{ minWidth: 600 }}>
                <thead>
                  <tr className="text-left">
                    <th>Jour</th>
                    <th>Heure</th>
                    <th>WOD</th>
                    <th>#</th>
                    <th>Lane</th>
                    <th>Rôle</th>
                    <th>Équipe</th>
                  </tr>
                </thead>
                <tbody>
                  {assignedRows.map((r, i) => (
                    <tr key={i}>
                      <td style={{ textTransform: 'capitalize' }}>{r.day || '—'}</td>
                      <td>{r.start || '—'}</td>
                      <td>{r.wod || '—'}</td>
                      <td>{r.heat ?? '—'}</td>
                      <td>{r.lane}</td>
                      <td>{r.role === 'juge' ? '⚖️ Juge' : '🔧 Build'}</td>
                      <td>{r.team?.trim() || '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )
        )}
      </div>
    </div>
  )
}
