// src/api/avail.ts
import client from '@/api/client'

export type AvailCell = 'none' | 'dispo' | 'juge' | 'build' | 'staff'
export type AvailMap = Record<string, AvailCell>
export type AvailWriteMap = Record<string, 'none' | 'dispo'>

/** Charge les dispos (sans email = utilisateur courant) */
export async function loadAvail(email?: string): Promise<AvailMap> {
  const path = email ? `/avail_get.php?email=${encodeURIComponent(email)}` : `/avail_get.php`
  const r = await client.request(path)
  return (r?.avail ?? {}) as AvailMap
}

/** Écrase TOUT et réécrit uniquement les créneaux visibles */
export async function saveAvail(avail: AvailWriteMap, email?: string): Promise<void> {
  const body: any = { avail }
  if (email) body.email = email
  await client.request('/overwrite_avail.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Cache-Control': 'no-store' },
    body: JSON.stringify(body),
  })
}
