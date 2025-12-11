// src/App.tsx
import React from 'react'
import { Routes, Route, Navigate, useLocation } from 'react-router-dom'
import Planning from '@/pages/Planning'
import MesDispos from '@/pages/MesDispos'
import HeatsPage from '@/pages/Heats'
import Admin from '@/pages/Admin'
import Stats from '@/pages/Stats'
import Login from '@/pages/Login'
import Register from '@/pages/Register'
import { useMe } from '@/api/auth'
import Teams from '@/pages/Teams'

function NavBar() {
  const { me, setMe } = useMe()

  const doLogout = async () => {
    setMe(null)
    try {
      await fetch('/la-hache-contest/api/logout.php', {
        method: 'POST',
        credentials: 'include',
        cache: 'no-store',
        headers: { 'Cache-Control': 'no-store' }
      })
    } catch {}
    try { localStorage.removeItem('csrf') } catch {}
    try { sessionStorage.clear() } catch {}
    window.location.replace('/la-hache-contest/#/login')
  }

  const role = me?.role as string | undefined
  const isAdmin = role === 'admin'
  const isBenevole = ['benevole', 'user', 'admin'].includes(role || '')

  return (
    <header className="sticky top-0 z-50 bg-white border-b mb-3">
      <div className="max-w-6xl mx-auto p-3 flex gap-3 items-center">
        <a className="underline" href="/la-hache-contest/#/">Planning</a>

        {isBenevole && (
          <a className="underline" href="/la-hache-contest/#/dispos">Mon espace</a>
        )}

        {isAdmin && <a className="underline" href="/la-hache-contest/#/stats">Stats</a>}
        {isAdmin && <a className="underline" href="/la-hache-contest/#/teams">Teams</a>}
        {isAdmin && <a className="underline" href="/la-hache-contest/#/heats">Heats</a>}
        {isAdmin && <a className="underline" href="/la-hache-contest/#/admin">Admin</a>}

        {!me && (
          <a className="underline ml-auto" href="/la-hache-contest/#/login">
            Login
          </a>
        )}

        {me && (
          <div className="ml-auto flex items-center gap-2">
            <span>
              Connecté: {me.email} ({me.role})
            </span>
            <button onClick={doLogout} className="px-2 py-1 rounded bg-neutral-200">
              Logout
            </button>
          </div>
        )}
      </div>
    </header>
  )
}

function RequireAdmin({ children }: { children: React.ReactNode }) {
  const { me, loading } = useMe()
  const loc = useLocation()
  if (loading) return <div className="p-6">Chargement…</div>
  if (!me) return <Navigate to="/login" replace state={{ from: loc }} />
  if (me.role !== 'admin') return <Navigate to="/" replace />
  return <>{children}</>
}

function RequireBenevole({ children }: { children: React.ReactNode }) {
  const { me, loading } = useMe()
  const loc = useLocation()
  if (loading) return <div className="p-6">Chargement…</div>
  if (!me) return <Navigate to="/login" replace state={{ from: loc }} />
  const allowed = ['benevole', 'user', 'admin']
  if (!allowed.includes(me.role as string)) {
    return <Navigate to="/" replace />
  }
  return <>{children}</>
}

export default function App() {
  const { loading } = useMe()
  if (loading) return <div className="p-6">Chargement…</div>

  return (
    <div className="max-w-6xl mx-auto p-4 space-y-4">
      <NavBar />
      <Routes>
        <Route path="/" element={<Planning />} />
        <Route
          path="/dispos"
          element={
            <RequireBenevole>
              <MesDispos />
            </RequireBenevole>
          }
        />
        <Route
          path="/stats"
          element={
            <RequireAdmin>
              <Stats />
            </RequireAdmin>
          }
        />
        <Route
          path="/teams"
          element={
            <RequireAdmin>
              <Teams />
            </RequireAdmin>
          }
        />
        <Route
          path="/heats"
          element={
            <RequireAdmin>
              <HeatsPage />
            </RequireAdmin>
          }
        />
        <Route
          path="/admin"
          element={
            <RequireAdmin>
              <Admin />
            </RequireAdmin>
          }
        />
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </div>
  )
}
