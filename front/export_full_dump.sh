#!/usr/bin/env bash
# Export project structure and source code into timestamped text files.
# Usage:
#   1) Place this script at the ROOT of your project.
#   2) Run:   bash export_full_dump.sh
#   3) Results will be in ./exports/export_YYYY-MM-DD_HH-MM-SS/
#
# Works on macOS and Linux. Requires only POSIX tools.
# Optional: 'tree' and 'gzip' if available (falls back gracefully).

set -euo pipefail

# ------------ Config (edit if needed) ------------
# Directories to exclude (add/remove as you like)
EXCLUDES=(
  ".git"
  "node_modules"
  "dist"
  "build"
  ".next"
  ".cache"
  "coverage"
  ".turbo"
  ".parcel-cache"
  ".vite"
  ".idea"
  ".vscode"
  ".DS_Store"
)

# File extensions to include in the code dump (case-insensitive)
EXTENSIONS=(
  "js" "jsx" "ts" "tsx" "php" "json" "css" "html" "md"
  "env" "env.example" "sh" "bash" "yml" "yaml" "sql"
  "ini" "conf" "toml" "xml" "csv"
)

# Max number of header lines per file in preview
PREVIEW_LINES=40
# -------------------------------------------------

timestamp() { date "+%Y-%m-%d_%H-%M-%S"; }

ROOT="$(pwd)"
OUTBASE="${ROOT}/exports"
OUTDIR="${OUTBASE}/export_$(timestamp)"
mkdir -p "${OUTDIR}"

# Build a combined regex for extensions (escaped dots)
# Example: \.(js|jsx|ts|tsx)$ (case-insensitive via grep -Ei)
EXT_REGEX='\.('
for i in "${!EXTENSIONS[@]}"; do
  ext="${EXTENSIONS[$i]}"
  # escape dots in extensions like "env.example"
  ext_escaped="$(printf '%s' "$ext" | sed 's/\./\\./g')"
  if [[ $i -gt 0 ]]; then EXT_REGEX+="|"; fi
  EXT_REGEX+="${ext_escaped}"
done
EXT_REGEX+=')$'

# Build prune expression for find
# -path "./dir" -prune -o ... so that excluded dirs are skipped entirely
PRUNE_EXPR=()
for d in "${EXCLUDES[@]}"; do
  PRUNE_EXPR+=( -path "./${d}" -prune -o )
done

# ---------- 1) Structure (tree or find fallback) ----------
STRUCT_OUT="${OUTDIR}/structure.txt"
if command -v tree >/dev/null 2>&1; then
  # Build tree ignore pattern (dir1|dir2|...)
  IGNORE_PATTERN="$(IFS='|'; echo "${EXCLUDES[*]}")"
  echo "[i] Generating structure with 'tree' ..."
  tree -a -I "${IGNORE_PATTERN}" > "${STRUCT_OUT}" || true
else
  echo "[i] 'tree' not found, using 'find' fallback for structure ..."
  # 'find' structure (directories first, then files), skipping excludes
  # Print relative paths without leading './'
  find . "${PRUNE_EXPR[@]}" -print | sed 's|^\./||' | sort > "${STRUCT_OUT}"
fi

# ---------- 2) File list of source files ----------
FILELIST="${OUTDIR}/filelist.txt"
echo "[i] Building filtered file list ..."
# shellcheck disable=SC2016
find . "${PRUNE_EXPR[@]}" -type f -print \
  | sed 's|^\./||' \
  | grep -Ei "${EXT_REGEX}" \
  | sort > "${FILELIST}" || true

# ---------- 3) Full code dump ----------
CODE_DUMP="${OUTDIR}/fullcode_dump.txt"
echo "[i] Creating full code dump at ${CODE_DUMP} ..."

# Ensure empty file
: > "${CODE_DUMP}"
while IFS= read -r f; do
  {
    echo "===== ${f} ====="
    # Use cat with safety for binary-ish files (in case) by limiting to text detection
    # We assume configured extensions are text; if not, still cat but it's user-controlled.
    cat -- "$f" 2>/dev/null || echo "[warn] Unable to read $f"
    echo ""
  } >> "${CODE_DUMP}"
done < "${FILELIST}"

# ---------- 4) Preview dump (first N lines per file) ----------
PREVIEW="${OUTDIR}/code_preview.txt"
echo "[i] Creating preview (${PREVIEW_LINES} lines per file) at ${PREVIEW} ..."
: > "${PREVIEW}"
while IFS= read -r f; do
  {
    echo "===== ${f} ====="
    head -n "${PREVIEW_LINES}" -- "$f" 2>/dev/null || echo "[warn] Unable to read $f"
    echo ""
  } >> "${PREVIEW}"
done < "${FILELIST}"

# ---------- 5) Compressed versions (if gzip available) ----------
if command -v gzip >/dev/null 2>&1; then
  echo "[i] Creating compressed archives ..."
  gzip -c "${CODE_DUMP}" > "${CODE_DUMP}.gz" || true
  gzip -c "${PREVIEW}" > "${PREVIEW}.gz" || true
fi

# ---------- 6) Summary ----------
count_files=$(wc -l < "${FILELIST}" | tr -d ' ')
size_dump=$(wc -c < "${CODE_DUMP}" | tr -d ' ')
size_preview=$(wc -c < "${PREVIEW}" | tr -d ' ')
human() {
  # simple human-size formatter
  awk 'function human(x){ s="B KMGTPEZY"; while (x>=1024 && length(s)>1){x/=1024; s=substr(s,3)} return sprintf("%.1f %s", x, substr(s,1,1)) } {print human($1)}'
}
echo
echo "======== Export Summary ========"
echo "Output folder : ${OUTDIR}"
echo "Structure     : $(basename "${STRUCT_OUT}")"
echo "Files matched : ${count_files}"
echo "Dump size     : $(printf "%s\n" "${size_dump}" | human)  ($(basename "${CODE_DUMP}"))"
echo "Preview size  : $(printf "%s\n" "${size_preview}" | human)  ($(basename "${PREVIEW}"))"
if [[ -f "${CODE_DUMP}.gz" ]]; then
  echo "Compressed    : $(basename "${CODE_DUMP}.gz"), $(basename "${PREVIEW}.gz")"
fi
echo "================================"
echo "[OK] Done."
