import React from 'react'
import { login } from '@/api/auth'
import { useNavigate } from 'react-router-dom'

export default function Login(){
  const nav = useNavigate()
  const [email, setEmail] = React.useState('')
  const [password, setPassword] = React.useState('')
  const [error, setError] = React.useState<string| null>(null)
  const [loading, setLoading] = React.useState(false)

  const submit = async (e: React.FormEvent)=>{
    e.preventDefault()
    setLoading(true); setError(null)
    try{
      await login(email, password)
      // IMPORTANT: reload pour que App/useMe relise /me.php avec la session fraîche
      window.location.href = '/la-hache-contest/'
    }catch{
      setError('Identifiants invalides')
    }finally{
      setLoading(false)
    }
  }

  return (
    <form onSubmit={submit} className="pastel-card max-w-md mx-auto">
      <h1 className="text-xl font-semibold mb-3">Connexion</h1>
      <label className="block mb-2">Email
        <input className="w-full border rounded p-2" value={email} onChange={e=>setEmail(e.target.value)} />
      </label>
      <label className="block mb-2">Mot de passe
        <input type="password" className="w-full border rounded p-2" value={password} onChange={e=>setPassword(e.target.value)} />
      </label>
      {error && <div className="text-red-600 mb-2">{error}</div>}
      <button disabled={loading} className="px-3 py-1 rounded bg-neutral-200">{loading?'Connexion…':'Se connecter'}</button>
      <div className="mt-3 text-sm">
        Pas de compte ? <a className="underline" onClick={(e)=>{e.preventDefault(); nav('/register')}} href="/register">Créer un compte</a>
      </div>
    </form>
  )
}
