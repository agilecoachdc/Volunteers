// src/pages/Stats.tsx
import React from 'react'
import { loadAvail } from '@/api/avail'
import { useUsers } from '@/hooks/useUsers'

const HOURS = [
  '07:00','07:30','08:00','08:30','09:00','09:30','10:00','10:30',
  '11:00','11:30','12:00','12:30','13:00','13:30','14:00','14:30',
  '15:00','15:30','16:00','16:30','17:00','17:30','18:00','18:30',
  '19:00','19:30','20:00'
]
type Day = 'samedi' | 'dimanche'

type Bucket = { juge:number; build:number; staff:number }
function emptyBuckets(): Record<string, Bucket> {
  const m: Record<string, Bucket> = {}
  for (const h of HOURS) m[h] = { juge:0, build:0, staff:0 }
  return m
}

function normPoste(p?: string): 'juge'|'build'|'staff'|'' {
  const s = (p||'').toLowerCase().trim()
  if (!s) return ''
  if (s.startsWith('j')) return 'juge'
  if (s.startsWith('b')) return 'build'
  if (s === 'staff')     return 'staff'
  if (s === 'juge' || s === 'build') return s as any
  return ''
}

export default function Stats(){
  const { users } = useUsers()
  const [selectedDay, setSelectedDay] = React.useState<Day>('samedi')

  const [buckets, setBuckets] = React.useState<Record<string, Bucket>>(emptyBuckets())
  const [loading, setLoading] = React.useState(true)
  const [err, setErr] = React.useState<string|null>(null)

  React.useEffect(()=>{
    (async()=>{
      setLoading(true); setErr(null)
      try {
        const roleByEmail: Record<string,'juge'|'build'|'staff'|''> = {}
        for (const u of users) {
          const email = (u.email||'').toLowerCase()
          roleByEmail[email] = normPoste((u as any).poste)
        }

        const b = emptyBuckets()
        await Promise.all(users.map(async (u)=>{
          const email = (u.email||'').toLowerCase()
          if (!email) return
          const role = roleByEmail[email]
          if (!role) return
          const avail = await loadAvail(email)
          for (const hhmm of HOURS) {
            const key = `${selectedDay} ${hhmm}`
            if ((avail[key]||'') === 'dispo') {
              if (!b[hhmm]) b[hhmm] = { juge:0, build:0, staff:0 }
              b[hhmm][role] += 1
            }
          }
        }))

        setBuckets(b)
      } catch(e:any){
        setErr(e?.message || 'Erreur chargement stats')
      } finally {
        setLoading(false)
      }
    })()
  }, [users, selectedDay])

  const maxVal = React.useMemo(()=>{
    let m = 0
    Object.values(buckets).forEach(v=>{
      m = Math.max(m, v.juge, v.build, v.staff)
    })
    return Math.max(m, 1)
  }, [buckets])

  return (
    <div className="pastel-card">
      <div className="flex items-center gap-2 mb-2">
        <h1 className="text-xl font-semibold">Stats — Disponibilités déclarées</h1>

        <div className="tools" style={{marginLeft:8}}>
          <button
            className={`tool ${selectedDay==='samedi' ? 'tool--active' : ''}`}
            onClick={()=>setSelectedDay('samedi')}
            title="Voir samedi"
          >samedi</button>
          <button
            className={`tool ${selectedDay==='dimanche' ? 'tool--active' : ''}`}
            onClick={()=>setSelectedDay('dimanche')}
            title="Voir dimanche"
          >dimanche</button>
        </div>

        <div className="ml-auto legend">
          <span><span className="dot dot-juge"></span>Juges</span>
          <span><span className="dot dot-build"></span>Build</span>
          <span><span className="dot dot-staff"></span>Staff</span>
        </div>
      </div>

      {loading && <div>Chargement…</div>}
      {err && <div className="text-red-600">{err}</div>}

      {!loading && !err && (
        <div className="stats-bars">
          {HOURS.map((hhmm)=>{
            const v = buckets[hhmm] || {juge:0,build:0,staff:0}
            const hJ = Math.round((v.juge  / maxVal) * 100)
            const hB = Math.round((v.build / maxVal) * 100)
            const hS = Math.round((v.staff / maxVal) * 100)
            return (
              <div key={hhmm} className="bar-group">
                <div className="bar bar-juge"  style={{height:`${hJ}%`}} title={`${selectedDay} ${hhmm} · juges dispo: ${v.juge}`}>
                  <span className="bar-tip">{v.juge}</span>
                </div>
                <div className="bar bar-build" style={{height:`${hB}%`}} title={`${selectedDay} ${hhmm} · build dispo: ${v.build}`}>
                  <span className="bar-tip">{v.build}</span>
                </div>
                <div className="bar bar-staff" style={{height:`${hS}%`}} title={`${selectedDay} ${hhmm} · staff dispo: ${v.staff}`}>
                  <span className="bar-tip">{v.staff}</span>
                </div>
                <div className="bar-label">{hhmm}</div>
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}
