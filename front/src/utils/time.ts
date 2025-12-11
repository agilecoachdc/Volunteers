// src/utils/time.ts
// Créneaux de 07:00 à 20:00 (inclus) toutes les 30 minutes.
const START_H = 7
const END_H   = 20
const STEP_MIN = 30

function pad(n: number): string { return n < 10 ? '0'+n : ''+n }

export const SLOTS: string[] = (() => {
  const arr: string[] = []
  for (let h = START_H; h <= END_H; h++) {
    for (let m = 0; m < 60; m += STEP_MIN) {
      if (h === END_H && m > 0) break // ne pas dépasser 20:00
      arr.push(`${pad(h)}:${pad(m)}`)
    }
  }
  return arr
})()
