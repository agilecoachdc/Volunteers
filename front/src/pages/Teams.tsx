// src/pages/Teams.tsx
import React from 'react'
import { useMe } from '@/api/auth'
import { getHeats } from '@/api/heats'
import { loadTeams, saveTeams, type Team } from '@/api/teams'

type Cat = ''|'Régular'|'Inter'|'RX'
type HeatLine = { equipe?: string; juge?: string; build?: string }
type Heat = { wod: string; heat: number; start: string; lignes: HeatLine[] }

const CAT_ORDER: Cat[] = ['', 'Régular','Inter','RX']

export default function TeamsPage(){
  const { me } = useMe()
  const isAdmin = me?.role === 'admin'

  const [teams, setTeams] = React.useState<Team[]>([])
  const [heats, setHeats] = React.useState<Heat[]>([])
  const [loading, setLoading] = React.useState(true)
  const [saving, setSaving] = React.useState(false)
  const [msg, setMsg] = React.useState<string|null>(null)
  const [err, setErr] = React.useState<string|null>(null)

  React.useEffect(()=>{(async()=>{
    setLoading(true); setErr(null)
    try{
      const [t, hRaw] = await Promise.all([
        loadTeams(),
        getHeats().then((r:any)=> Array.isArray(r)? r : (r?.heats || []))
      ])
      setTeams(t)
      const norm = (x:any):Heat => ({
        wod: String(x?.wod ?? ''),
        heat: Number(x?.heat ?? x?.numero ?? 0),
        start: String(x?.start ?? x?.heure ?? ''),
        lignes: Array.isArray(x?.lignes) ? x.lignes : []
      })
      setHeats((hRaw||[]).map(norm))
    }catch(e:any){ setErr(e?.message||'Erreur de chargement') }
    finally{ setLoading(false) }
  })()},[])

  const wods = React.useMemo(()=>{
    const s = new Set(heats.map(h=> String(h.wod)))
    return Array.from(s).sort((a,b)=> a.localeCompare(b))
  },[heats])

  const setTeamById = (id:string, patch: Partial<Team>)=>{
    setTeams(ts => ts.map((t)=> t.id === id ? {...t, ...patch} : t))
  }
  const addTeam = ()=>{ if (isAdmin) setTeams(ts => [...ts, { id: crypto.randomUUID(), name:'Nouvelle équipe', cat: '' }]) }
  const removeTeam = (id:string)=>{ if (isAdmin) setTeams(ts => ts.filter(t=> t.id !== id)) }

  const saveAdmin = async ()=>{
    if (!isAdmin) return
    setSaving(true); setMsg(null); setErr(null)
    try{ await saveTeams(teams); setMsg('💾 Équipes enregistrées') }
    catch(e:any){ setErr(e?.message||'Erreur sauvegarde') }
    finally{ setSaving(false) }
  }

  const repartirTeams = async ()=>{
    if (!isAdmin) return
    setSaving(true); setMsg(null); setErr(null)
    try{
      const res = await fetch('/la-hache-contest/api/repartir_team.php', {
        method: 'POST',
        credentials: 'include',
        cache: 'no-store',
        headers: { 'Cache-Control': 'no-store' }
      })
      const data = await res.json()
      const raw = Array.isArray(data) ? data : (data?.heats || [])
      if (!Array.isArray(raw)) throw new Error('répartition: réponse invalide')
      const norm = (x:any):Heat => ({
        wod: String(x?.wod ?? ''),
        heat: Number(x?.heat ?? x?.numero ?? 0),
        start: String(x?.start ?? x?.heure ?? ''),
        lignes: Array.isArray(x?.lignes) ? x.lignes : []
      })
      setHeats(raw.map(norm))
      setMsg('✅ Répartition équipes OK')
    }catch(e:any){
      setErr(e?.message || 'Erreur répartition équipes')
    }finally{
      setSaving(false)
    }
  }

  function findSlotFor(teamName:string, wod:string){
    for(const h of heats) if(String(h.wod)===wod){
      const i = (h.lignes||[]).findIndex(l => (l.equipe||'').trim()===teamName)
      if(i>=0) return { heat:h.heat, time:h.start, line:i+1 }
    }
    return null
  }

  const sorted = React.useMemo(()=>{
    return teams.slice().sort((a,b)=>
      CAT_ORDER.indexOf(a.cat as Cat) - CAT_ORDER.indexOf(b.cat as Cat) ||
      a.name.localeCompare(b.name)
    )
  }, [teams])

  // Cartouche resserrée: largeur 8–9 caractères, padding symétrique
  const Cartouche: React.FC<{heat:number; time:string; line:number}> = ({heat,time,line}) => {
    return (
      <div
        className="rounded border border-neutral-300"
        style={{
          display:'inline-flex',
          flexDirection:'column',
          gap:2,
          padding: '3px 8px',
          minWidth: '8ch',   // “Ligne 10” tient
          maxWidth: '9ch',
          width: 'fit-content',
          lineHeight: 1.15
        }}
      >
        <div className="font-semibold">Heat {heat}</div>
        <div className="text-sm text-neutral-700">{time}</div>
        <div className="text-sm">Ligne {line}</div>
      </div>
    )
  }

  return (
    <div className="pastel-card space-y-3">
      <div className="flex items-center gap-2">
        <h1 className="text-xl font-semibold">Teams</h1>
        {isAdmin && (
          <>
            <button className="btn" onClick={addTeam}>➕ Ajouter</button>
            <button className="btn" onClick={saveAdmin} disabled={saving}>💾</button>
            <button className="btn" onClick={repartirTeams} disabled={saving}>Répartir</button>
            {msg && <span className="text-sm text-green-700">{msg}</span>}
            {err && <span className="text-sm text-red-600">{err}</span>}
          </>
        )}
      </div>

      {loading ? <div>Chargement…</div> : (
        <div className="overflow-auto">
          <table className="table text-sm" style={{minWidth: 900}}>
            <thead>
              <tr className="text-left">
                <th>Nom</th>
                <th>Catégorie</th>
                {wods.map(w => <th key={w}>{w}</th>)}
              </tr>
            </thead>
            <tbody>
              {sorted.map((t)=>(
                <tr key={t.id}>
                  <td>
                    {isAdmin
                      ? (
                        <div className="flex items-center gap-2">
                          <input
                            className="input"
                            style={{width:180}}
                            value={t.name}
                            onChange={e=>setTeamById(t.id, {name:e.target.value})}
                          />
                          <button className="btn" title="Supprimer" onClick={()=>removeTeam(t.id)}>🗑️</button>
                        </div>
                      )
                      : t.name}
                  </td>
                  <td>
                    {isAdmin
                      ? (
                        <select
                          className="input"
                          value={t.cat}
                          onChange={e=>setTeamById(t.id, {cat: e.target.value as Cat})}
                          style={{width:140}}
                        >
                          <option value="">—</option>
                          <option value="Régular">Régular</option>
                          <option value="Inter">Inter</option>
                          <option value="RX">RX</option>
                        </select>
                      ) : (t.cat || '—')}
                  </td>
                  {wods.map(w=>{
                    const slot = findSlotFor(t.name, w)
                    return (
                      <td key={w}>
                        {slot
                          ? <Cartouche heat={slot.heat} time={slot.time} line={slot.line} />
                          : <span className="text-neutral-500">—</span>
                        }
                      </td>
                    )
                  })}
                </tr>
              ))}
              {sorted.length === 0 && (
                <tr><td colSpan={2 + wods.length} className="text-neutral-500">Aucune équipe.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
