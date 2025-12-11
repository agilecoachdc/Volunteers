// src/hooks/useUsers.ts
import React from 'react'
import client from '@/api/client'

export type User = {
  id?: number
  name?: string
  email: string
  role?: string
  is_active?: number
  poste?: string | null
}

export function useUsers() {
  const [users, setUsers] = React.useState<User[]>([])
  React.useEffect(() => {
    (async () => {
      try {
        const r = await client.request('/users_list.php')
        const arr = (r?.users ?? []) as any[]
        const norm = arr.map(u => ({
          ...u,
          email: String(u.email || '').toLowerCase(),
          name: String(u.name || '').trim(),
        }))
        setUsers(norm)
      } catch {
        setUsers([])
      }
    })()
  }, [])
  return { users }
}

export function useUserMap() {
  const { users } = useUsers()
  const map = React.useMemo(() => {
    const m = new Map<string, User>()
    users.forEach(u => m.set((u.email || '').toLowerCase(), u))
    return m
  }, [users])

  const resolve = (email?: string, short = false): string => {
    const e = (email || '').toLowerCase()
    const u = map.get(e)
    if (!u) return email || ''
    const display = (u.name && u.name.trim()) ? u.name.trim() : (u.email || '')
    if (!short) return display

    // Prénom (+ initiale si homonyme)
    const parts = display.split(/\s+/)
    let label = parts[0] || display
    const sameFirst = users.filter(x => (x.name || '').split(/\s+/)[0] === parts[0]).length > 1
    if (sameFirst && parts[1]) label += ' ' + parts[1][0].toUpperCase() + '.'
    return label
  }

  return { users, map, resolve }
}
