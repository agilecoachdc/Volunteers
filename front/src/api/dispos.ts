// src/api/dispos.ts
import api from './client'
export type AvailMap = Record<string, 'none'|'dispo'|'juge'|'build'>

export async function loadAvail(email?: string): Promise<{avail:AvailMap}> {
  const qs = email ? `?email=${encodeURIComponent(email)}` : ''
  return api.request(`/avail_get.php${qs}`)
}

export async function saveAvail(avail: AvailMap, email?: string): Promise<void> {
  const body: any = { avail }
  if (email) body.email = email
  await api.request('/avail_save.php', {
    method: 'POST',
    body: JSON.stringify(body),
  })
}
