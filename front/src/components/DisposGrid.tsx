import React from 'react'
import { SLOTS } from '@/utils/time'

export type Cell = 'none' | 'dispo'
const DAYS = ['samedi','dimanche'] as const
type Day = typeof DAYS[number]

export default function DisposGrid({
  value, onChange, assigned
}:{
  value: Record<string, Cell>,
  onChange: (next: Record<string, Cell>) => void,
  // clé = "samedi 09:00" etc. valeur = 'juge' | 'build' | 'staff'
  assigned?: Record<string, 'juge'|'build'|'staff'>
}){
  const [brush, setBrush] = React.useState<Cell>('dispo')

  const cls = (c:Cell) => c==='dispo' ? 'role-dispo' : 'role-none'
  const keyOf = (day:Day, slot:string)=> `${day} ${slot}`

  const applyBrush = (k: string) => {
    if ((value[k] as Cell) === brush) return
    onChange({ ...value, [k]: brush })
  }
  const cycle = (c: Cell): Cell => (c==='none' ? 'dispo' : 'none')

  const draggingRef = React.useRef(false)
  const onStart = () => { draggingRef.current = true }
  const onEnd = () => { draggingRef.current = false }

  React.useEffect(()=>{
    const up = () => onEnd()
    document.addEventListener('pointerup', up, { passive: true })
    document.addEventListener('pointercancel', up, { passive: true })
    return ()=> {
      document.removeEventListener('pointerup', up)
      document.removeEventListener('pointercancel', up)
    }
  }, [])

  const onContainerPointerMove = (e: React.PointerEvent<HTMLDivElement>)=>{
    if (!draggingRef.current) return
    const el = document.elementFromPoint(e.clientX, e.clientY) as HTMLElement | null
    if (!el) return
    const target = el.closest<HTMLElement>('button[data-key]')
    if (!target) return
    const k = target.dataset.key
    if (k) applyBrush(k)
    e.preventDefault()
  }

  const onCellPointerDown = ()=>{ onStart() }
  const onCellPointerUp = (k: string, current: Cell)=>{
    const wasDragging = draggingRef.current
    draggingRef.current = false
    if (!wasDragging) onChange({ ...value, [k]: cycle(current) })
  }

  const showLabel = (slot:string)=> slot.slice(-2) === '00'

  return (
    <div className="dispos-grid">
      <div className="flex items-center gap-2 mb-2">
        <span>Peindre :</span>
        <button className="btn" aria-pressed={brush==='dispo'} style={{ fontWeight: brush==='dispo' ? 600 : 400 }} onClick={()=>setBrush('dispo')}>Disponible</button>
        <button className="btn" aria-pressed={brush==='none'}  style={{ fontWeight: brush==='none'  ? 600 : 400 }} onClick={()=>setBrush('none')}>Pas dispo</button>
      </div>

      <div onPointerMove={onContainerPointerMove}>
        <div className="dispos-hz">
          {(['samedi','dimanche'] as Day[]).map(day=>(
            <div className="dispos-row" key={day}>
              <div className="dispos-day">{day}</div>
              <div className="dispos-scroller">
                <div className="dispos-line">
                  {SLOTS.map(slot=>{
                    const k = keyOf(day, slot)
                    const c = (value[k] ?? 'none') as Cell

                    // Surcouche couleur selon rôle réellement attribué
                    const role = assigned?.[k]
                    const roleClass =
                      role==='juge'  ? 'as-juge'  :
                      role==='build' ? 'as-build' :
                      role==='staff' ? 'as-staff' : ''

                    return (
                      <button
                        key={slot}
                        className={`dispos-cell ${cls(c)} ${roleClass}`}
                        title={`${day} ${slot}`}
                        data-key={k}
                        style={{ touchAction: 'none' }}
                        onPointerDown={onCellPointerDown}
                        onPointerUp={()=>onCellPointerUp(k,c)}
                      >
                        {showLabel(slot) && <span className="hour-label">{slot}</span>}
                      </button>
                    )
                  })}
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}
