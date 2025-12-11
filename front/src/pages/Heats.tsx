// src/pages/Heats.tsx
import React from 'react'
import { getHeats, saveHeats, repartir, type Heat, type Line } from '@/api/heats'
import { useUserMap } from '@/hooks/useUsers'

type EditField = 'equipe' | 'juge' | 'build' | 'day' | 'start' | 'wod' | 'heat'
type EditTarget = { hIdx: number; cIdx?: number; field: EditField }

export default function HeatsPage(){
  const [heats, setHeats] = React.useState<Heat[]>([])
  const [cols, setCols] = React.useState<number>(10)
  const [loading, setLoading] = React.useState(true)
  const [saving, setSaving] = React.useState(false)
  const [error, setError] = React.useState<string|null>(null)
  const [info, setInfo] = React.useState<string|null>(null)

  const [newDay, setNewDay] = React.useState<'samedi'|'dimanche'>('samedi')
  const [newTime, setNewTime] = React.useState<string>('09:00')
  const [newWod, setNewWod] = React.useState<string>('WOD1')
  const [newHeatNum, setNewHeatNum] = React.useState<number>(1)

  const [editing, setEditing] = React.useState<EditTarget | null>(null)
  const [editValue, setEditValue] = React.useState<string>('')

  const { resolve } = useUserMap()
  const short = (v?: string) => {
    const e = (v||'').toLowerCase()
    if (!e) return ''
    return resolve(e, true) || v || ''
  }

  const computeCols = (arr: Heat[]) =>
    Math.max(10, arr.reduce((m,h)=>Math.max(m, h.lignes?.length||0), 0))

  const load = async ()=>{
    setLoading(true); setError(null); setInfo(null)
    try {
      const h = await getHeats()
      setHeats(h)
      setCols(computeCols(h))
      const lastHeat = h[h.length-1]?.heat ?? 0
      setNewHeatNum((lastHeat || 0) + 1)
      if (h.length>0) {
        setNewDay((h[h.length-1].day as any) || 'samedi')
        setNewWod(h[h.length-1].wod || 'WOD1')
      }
    } catch(e:any){
      setError(e.message||'Erreur de chargement')
    } finally { setLoading(false) }
  }
  React.useEffect(()=>{ void load() }, [])

  const ensureCols = (n:number)=>{
    setCols(n)
    setHeats(v=>v.map(h=>{
      const arr = [...(h.lignes||[])]
      if (arr.length < n) for(let i=arr.length;i<n;i++) arr.push({equipe:'',juge:'',build:''})
      else if (arr.length > n) arr.length = n
      return { ...h, lignes: arr }
    }))
  }
  const addHeatQuick = ()=>{
    const lignes: Line[] = Array.from({length: cols}, ()=>({equipe:'',juge:'',build:''}))
    const h: Heat = { day:newDay, start:newTime, wod:newWod, heat:newHeatNum, lignes }
    setHeats(v=>[...v, h])
    setNewHeatNum(n=>n+1)
  }

  const onSave = async ()=>{
    setSaving(true); setError(null); setInfo(null)
    try { await saveHeats(heats); setInfo('Enregistré ✅') }
    catch(e:any){ setError(e.message||'Erreur à l’enregistrement') }
    finally{ setSaving(false) }
  }

  const sanitizeUniquePerHeat = (arr: Heat[]): Heat[] => {
    return arr.map(h=>{
      const seen = new Set<string>()
      const lignes = (h.lignes||[]).map((cell)=>{
        const c = { ...cell }
        const judgeKey = (c.juge||'').toLowerCase()
        const buildKey = (c.build||'').toLowerCase()
        if (judgeKey) {
          const sig = `${judgeKey}|juge|${h.heat}`
          if (seen.has(sig)) c.juge = '' ; else seen.add(sig)
        }
        if (buildKey) {
          const sig = `${buildKey}|build|${h.heat}`
          if (seen.has(sig)) c.build = '' ; else seen.add(sig)
        }
        return c
      })
      return { ...h, lignes }
    })
  }

  const onRepartir = async ()=>{
    setSaving(true); setError(null); setInfo(null)
    try {
      const fresh = await repartir()
      setHeats(sanitizeUniquePerHeat(fresh))
      setInfo(`Répartition OK ✅`)
    } catch(e:any){
      setError(e.message||'Erreur sur la répartition')
    } finally {
      setSaving(false)
    }
  }

  // Edition inline
  const teamLine = (txt?: string) => (txt && txt.trim() ? txt.trim() : '-')
  const startEdit = (t: EditTarget, initial: string) => { setEditing(t); setEditValue(initial) }
  const commitEdit = () => {
    if (!editing) return
    const { hIdx, cIdx, field } = editing
    const v = editValue
    setHeats(prev=>{
      const copy = [...prev]
      const h = { ...copy[hIdx] }
      if (cIdx == null) {
        if (field==='day')   { const vv=String(v).toLowerCase(); if (vv==='samedi'||vv==='dimanche') h.day=vv as any }
        if (field==='start') h.start = String(v)
        if (field==='wod')   h.wod   = String(v)
        if (field==='heat')  { const n=Number(v); if (Number.isFinite(n)) h.heat=n }
        copy[hIdx]=h; return copy
      }
      const lignes=[...(h.lignes||[])]; const cell={...(lignes[cIdx]||{equipe:'',juge:'',build:''})}
      if (field==='equipe') cell.equipe=v
      if (field==='juge')   cell.juge=v
      if (field==='build')  cell.build=v
      lignes[cIdx]=cell; h.lignes=lignes; copy[hIdx]=h; return copy
    })
    setEditing(null); setEditValue('')
  }
  const cancelEdit = ()=>{ setEditing(null); setEditValue('') }

  const FieldHeader: React.FC<{hIdx:number; field:'day'|'start'|'wod'|'heat'; value:string|number}> =
  ({hIdx,field,value})=>{
    const isMe = editing && editing.hIdx===hIdx && editing.cIdx==null && editing.field===field
    const baseId = `heat-${hIdx}-${field}`
    if (isMe) {
      const type = field==='heat' ? 'number' : (field==='start' ? 'time' : 'text')
      return (
        <input
          id={baseId} name={baseId}
          autoFocus type={type} className="input"
          style={{minWidth: field==='wod' ? 80 : undefined}}
          value={String(editValue)}
          onChange={e=>setEditValue(e.target.value)}
          onBlur={commitEdit}
          onKeyDown={e=>{ if (e.key==='Enter') commitEdit(); if (e.key==='Escape') cancelEdit() }}
        />
      )
    }
    return (
      <div className="cursor-text" onClick={()=>startEdit({hIdx,field,cIdx:undefined}, String(value))} title="Cliquer pour éditer">
        {String(value)}
      </div>
    )
  }

  const FieldTeam: React.FC<{hIdx:number; cIdx:number; value:string}> = ({hIdx,cIdx,value})=>{
    const isMe = editing && editing.hIdx===hIdx && editing.cIdx===cIdx && editing.field==='equipe'
    const baseId = `heat-${hIdx}-col-${cIdx}-equipe`
    if (isMe) {
      return (
        <input
          id={baseId} name={baseId}
          autoFocus type="text" className="input"
          value={editValue}
          onChange={e=>setEditValue(e.target.value)}
          onBlur={commitEdit}
          onKeyDown={e=>{ if (e.key==='Enter') commitEdit(); if (e.key==='Escape') cancelEdit() }}
        />
      )
    }
    return (
      <div className="cursor-text" onClick={()=>startEdit({hIdx,cIdx,field:'equipe'}, value)} title="Cliquer pour éditer l'équipe">
        {teamLine(value)}
      </div>
    )
  }

  const FieldRole: React.FC<{
    hIdx:number; cIdx:number; field:'juge'|'build'; value:string; chipClass:string;
  }> = ({hIdx,cIdx,field,value,chipClass})=>{
    const isMe = editing && editing.hIdx===hIdx && editing.cIdx===cIdx && editing.field===field
    const baseId = `heat-${hIdx}-col-${cIdx}-${field}`
    if (isMe) {
      return (
        <input
          id={baseId} name={baseId}
          autoFocus type="text" className="input"
          placeholder={`${field==='juge'?'Juge':'Build'} (email ou nom)`}
          value={editValue}
          onChange={e=>setEditValue(e.target.value)}
          onBlur={commitEdit}
          onKeyDown={e=>{ if (e.key==='Enter') commitEdit(); if (e.key==='Escape') cancelEdit() }}
        />
      )
    }
    const label = short(value) || '-'
    return (
      <div className="cursor-text" onClick={()=>startEdit({hIdx,cIdx,field}, value)} title={`Cliquer pour éditer ${field}`}>
        {label !== '-' ? <span className={chipClass}>{label}</span> : <span>-</span>}
      </div>
    )
  }

  return (
    <div className="pastel-card space-y-3 planning-dense">
      <div className="flex items-center gap-2 flex-wrap">
        <h1 className="text-xl font-semibold">Heats</h1>

        {/* Ajout rapide (inchangé) */}
        <div className="flex items-center gap-2" style={{padding:'6px 10px', border:'1px solid #e5e7eb', borderRadius:8, background:'#f9fafb'}}>
          <label className="text-sm">Jour</label>
          <select className="input" value={newDay} onChange={e=>setNewDay(e.target.value as any)}>
            <option value="samedi">samedi</option>
            <option value="dimanche">dimanche</option>
          </select>
          <label className="text-sm">Heure</label>
          <input className="input" type="time" value={newTime} onChange={e=>setNewTime(e.target.value)} />
          <label className="text-sm">WOD</label>
          <input className="input" type="text" value={newWod} onChange={e=>setNewWod(e.target.value)} style={{width:50}} />
          <label className="text-sm">#</label>
          <input className="input" type="number" value={newHeatNum} onChange={e=>setNewHeatNum(Number(e.target.value)||1)} style={{width:50}} />
          <button className="btn" onClick={addHeatQuick} title="Ajouter un heat">+ Ajouter</button>
        </div>

        <span className="badge">Heats: {heats.length}</span>
        <span className="badge">Colonnes: {cols}</span>

        {/* Actions globales */}
        <button onClick={()=>load()} disabled={loading||saving} className="btn" title="Rafraîchir">🔄</button>
        <button onClick={onRepartir} disabled={loading||saving} className="btn" title="Répartir">Répartir</button>
        <button onClick={()=>ensureCols(cols+1)} disabled={saving} className="btn" title="Ajouter une colonne">+ Col</button>
        <button onClick={()=>ensureCols(Math.max(1,cols-1))} disabled={saving} className="btn" title="Retirer une colonne">– Col</button>

        {/* Reset rôles */}
        <button
          onClick={async ()=>{
            try{
              setSaving(true); setError(null); setInfo(null)
              await fetch('/la-hache-contest/api/reset_roles.php', { method:'POST', credentials:'include', cache:'no-store' })
              const fresh = await getHeats()
              setHeats(fresh)
              setInfo('Rôles vidés 🧽')
            }catch(e:any){
              setError(e?.message || 'Erreur reset rôles')
            }finally{ setSaving(false) }
          }}
          className="btn"
          title="Vider juges/build de tous les heats"
        >🧽</button>

        <button onClick={onSave} disabled={loading||saving} className="btn" aria-label="Enregistrer" title="Enregistrer">💾</button>

        {/* Légende */}
        <div className="ml-auto legend" style={{ display:'flex', alignItems:'center', gap:10 }}>
          <span><span className="legend-swatch chip-juge"></span>Juge</span>
          <span><span className="legend-swatch chip-build"></span>Build</span>
          <span><span className="legend-swatch" style={{background:'#fee2e2', border:'2px solid #ef4444'}}></span>Équipe sans rôle(s)</span>
        </div>
      </div>

      {error && <div className="text-red-600">{error}</div>}
      {info && <div className="text-green-700">{info}</div>}
      {loading && <div>Chargement…</div>}

      {!loading && (
        <div className="overflow-auto">
          <table className="table text-sm" style={{minWidth: 900}}>
            <thead>
              <tr className="text-left">
                <th>Jour</th>
                <th>Heure</th>
                <th>WOD</th>
                <th>#</th>
                {Array.from({length: cols}).map((_,i)=><th key={i}>Lane {i+1}</th>)}
                <th style={{width:48}}></th>
              </tr>
            </thead>

            <tbody>
              {heats.length===0 ? (
                <tr>
                  <td colSpan={4+cols+1} className="text-neutral-600">Aucun heat. Clique “+ Ajouter”.</td>
                </tr>
              ) : (
                heats.map((h, idx)=>(
                  <tr key={idx}>
                    <td><FieldHeader hIdx={idx} field="day"   value={h.day} /></td>
                    <td><FieldHeader hIdx={idx} field="start" value={h.start} /></td>
                    <td><FieldHeader hIdx={idx} field="wod"   value={h.wod} /></td>
                    <td><FieldHeader hIdx={idx} field="heat"  value={h.heat} /></td>

                    {Array.from({length: cols}).map((_,cIdx)=>{
                      const cell = h.lignes[cIdx] || {equipe:'',juge:'',build:''}
                      const hasTeam = !!(cell.equipe && cell.equipe.trim())
                      const missingRole = hasTeam && !(cell.juge && cell.juge.trim()) || !(cell.build && cell.build.trim())
                      const extraClass = hasTeam && ( !(cell.juge?.trim()) || !(cell.build?.trim()) ) ? 'slot--incomplete' : ''
                      return (
                        <td key={cIdx}>
                          <div className={['slot-card','cell-compact', extraClass].join(' ').trim()}>
                            <div className="line-compact" style={{marginBottom:2}}>
                              <FieldTeam hIdx={idx} cIdx={cIdx} value={cell.equipe} />
                            </div>
                            <div className="line-compact" style={{marginBottom:2}}>
                              <FieldRole hIdx={idx} cIdx={cIdx} field="juge"  value={cell.juge}  chipClass="chip chip-juge" />
                            </div>
                            <div className="line-compact">
                              <FieldRole hIdx={idx} cIdx={cIdx} field="build" value={cell.build} chipClass="chip chip-build" />
                            </div>
                          </div>
                        </td>
                      )
                    })}

                    <td>
                      <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                        {/* Répartir ce heat uniquement (garde les autres colonnes comme avant) */}
                        <button
                          onClick={async ()=>{
                            try{
                              setSaving(true); setError(null); setInfo(null)
                              const hh = heats[idx]
                              await fetch('/la-hache-contest/api/repartir_one.php', {
                                method: 'POST',
                                credentials: 'include',
                                cache: 'no-store',
                                headers: { 'Content-Type':'application/json', 'Cache-Control':'no-store' },
                                body: JSON.stringify({ day: hh.day, start: hh.start, wod: hh.wod, heat: hh.heat })
                              })
                              const fresh = await getHeats()
                              setHeats(sanitizeUniquePerHeat(fresh))
                              setInfo(`Répartition du heat #${hh.heat} OK ✅`)
                            }catch(e:any){
                              setError(e?.message || 'Erreur sur la répartition du heat')
                            }finally{
                              setSaving(false)
                            }
                          }}
                          className="btn"
                          aria-label="Répartir uniquement ce heat"
                          title="Répartir uniquement ce heat"
                          style={{ display:'inline-flex', alignItems:'center', justifyContent:'center', width:32, height:32 }}
                        >
                          🚧
                        </button>

                        <button
                          onClick={()=> setHeats(v=>v.filter((_,i)=>i!==idx))}
                          className="btn btn--danger"
                          aria-label="Supprimer ce heat"
                          title="Supprimer ce heat"
                          style={{ display:'inline-flex', alignItems:'center', justifyContent:'center', width:32, height:32 }}
                        >
                          🗑️
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}
