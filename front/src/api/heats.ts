// src/api/heats.ts
import client from '@/api/client'

export type Line = {
  equipe: string
  juge: string
  build: string
}

export type Heat = {
  day: 'samedi'|'dimanche'|string
  start: string
  wod: string
  heat: number
  lignes: Line[]
}

/** Charge tous les heats (JSON normalisé) */
export async function getHeats(): Promise<Heat[]> {
  const data: any = await client.request('/heats_get.php')
  const arr = Array.isArray(data?.heats) ? data.heats : (Array.isArray(data) ? data : [])
  return arr.map((h: any) => ({
    day: String(h.day ?? h.jour ?? '').toLowerCase() || 'samedi',
    start: String(h.start ?? h.heure ?? ''),
    wod: String(h.wod ?? ''),
    heat: Number(h.heat ?? h.numero ?? 0),
    lignes: Array.isArray(h.lignes)
      ? h.lignes.map((ln: any) => ({
          equipe: String(ln?.equipe ?? ''),
          juge: String(ln?.juge ?? ''),
          build: String(ln?.build ?? ''),
        }))
      : [],
  })) as Heat[]
}

/** Sauvegarde des heats (même structure que getHeats) */
export async function saveHeats(heats: Heat[]): Promise<void> {
  await client.request('/heats_save.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ heats }),
  })
}

/** Répartition globale (tous les heats) */
export async function repartir(): Promise<Heat[]> {
  const data: any = await client.request('/repartir_heats.php', {
    method: 'POST',
    headers: { 'Cache-Control': 'no-store' },
  })
  const arr = Array.isArray(data?.heats) ? data.heats : (Array.isArray(data) ? data : [])
  return arr as Heat[]
}
