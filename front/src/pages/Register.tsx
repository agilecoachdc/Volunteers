import React from 'react'
import { useNavigate } from 'react-router-dom'
import { useMe, useAuthActions } from '@/api/auth'

export default function Register(){
  const nav = useNavigate()
  const { doRegister } = useAuthActions()
  const [first, setFirst] = React.useState('')
  const [last, setLast] = React.useState('')
  const [email, setEmail] = React.useState('')
  const [password, setPassword] = React.useState('')
  const [optin, setOptin] = React.useState(false)
  const [err, setErr] = React.useState<string|null>(null)

  const submit = async (e:React.FormEvent)=>{
    e.preventDefault()
    setErr(null)
    if (!first.trim() || !last.trim() || !email.trim() || !password || !optin) {
      setErr('Veuillez remplir Prénom, Nom, Email, Mot de passe et accepter la protection des données.')
      return
    }
    const res = await doRegister({ first_name:first.trim(), last_name:last.trim(), email: email.trim().toLowerCase(), password, optin: true } as any)
    if (!res.ok) { setErr('Inscription impossible'); return }
    nav('/', { replace:true })
  }

  return (
    <div className="pastel-card" style={{maxWidth:460, margin:'40px auto'}}>
      <h1 className="text-xl font-semibold mb-3">Créer un compte</h1>
      {err && <div className="text-red-600 mb-2">{err}</div>}
      <form onSubmit={submit} className="space-y-2">
        <div className="grid grid-cols-2 gap-2">
          <div>
            <label className="text-sm">Prénom *</label>
            <input className="input" value={first} onChange={e=>setFirst(e.target.value)} />
          </div>
          <div>
            <label className="text-sm">Nom *</label>
            <input className="input" value={last} onChange={e=>setLast(e.target.value)} />
          </div>
        </div>
        <div>
          <label className="text-sm">Email *</label>
          <input className="input" value={email} onChange={e=>setEmail(e.target.value)} />
        </div>
        <div>
          <label className="text-sm">Mot de passe *</label>
          <input className="input" type="password" value={password} onChange={e=>setPassword(e.target.value)} />
        </div>
        <label className="flex items-start gap-2 text-sm">
          <input type="checkbox" checked={optin} onChange={e=>setOptin(e.target.checked)} />
          <span>J’accepte la protection des données (opt-in) *</span>
        </label>
        <button className="btn btn--primary" type="submit">Créer mon compte</button>
      </form>
    </div>
  )
}
