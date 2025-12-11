// src/api/teams.ts
import client from '@/api/client'

export type TeamCat = ''|'Régular'|'Inter'|'RX'
export type Team = { id:string; name:string; cat:TeamCat }

export async function loadTeams(): Promise<Team[]>{
  const r = await client.request('/teams_get.php')
  return (r?.teams ?? []) as Team[]
}

export async function saveTeams(teams: Team[]): Promise<void>{
  await client.request('/teams_save.php', { method:'POST', body: JSON.stringify({teams}) })
}
