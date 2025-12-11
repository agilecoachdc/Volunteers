# La Hache Contest — Hotfix intégral
Build avec Vite + React + TS. Base déjà configurée pour /la-hache-contest/.

## Déploiement
1. `npm i && npm run build`
2. Copier le dossier `dist/` vers `/volume2/web/la-hache-contest/` (remplace les assets frontend)
3. Garder /adapter le dossier `api/` côté serveur si tu as déjà une base SQLite existante.

## Notes
- Alias `@` → `src/`
- `index.html` pointe vers `/src/main.tsx` (OK en dev; en prod Vite injecte les bundles).