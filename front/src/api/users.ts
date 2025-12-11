// src/api/users.ts
import client from '@/api/client'

export type User = {
  id?: number
  name?: string
  email?: string
  role?: 'admin' | 'benevole'
  poste?: 'juge' | 'build' | 'staff' | null
  is_active?: number | boolean
}

export async function listUsers(): Promise<User[]> {
  const r = await client.request('/users_list.php')
  const arr = Array.isArray(r?.users) ? r.users : []
  return arr as User[]
}

export async function updateUser(opts: {
  email: string
  role?: 'admin'|'benevole'
  poste?: 'juge'|'build'|'staff'|null
  is_active?: boolean
}): Promise<void> {
  await client.request('/users_update.php', {
    method: 'POST',
    headers: { 'Content-Type':'application/json' },
    body: JSON.stringify(opts),
  })
}
