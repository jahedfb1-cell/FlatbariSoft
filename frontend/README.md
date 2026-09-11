# Green Valley BMS — Frontend

React 18 + TypeScript + Tailwind v4 SPA for the Green Valley Building Management System.
Talks to the Laravel API in the parent directory over Sanctum bearer tokens.

**Dev port: `4000`** (fixed, `strictPort: true`) — deliberately different from WBL Soft's
`becha-kena-erp/frontend`, which owns port `3000`. These are two unrelated projects that may
run on the same machine at the same time; never change this port back to 3000.

## Run

```bash
npm install
npm run dev        # http://localhost:4000
```

The API base URL comes from `VITE_API_BASE_URL` in `.env` (defaults to
`http://127.0.0.1:8000/api`, i.e. `php artisan serve` in the parent directory).

## Stack notes

- React is pinned to `^18.3.1` — the PRD locks React 18, not the Vite-default 19.
- Tailwind v4 via `@tailwindcss/vite`, CSS-first theme tokens in `src/index.css`.
- Fonts are self-hosted via `@fontsource` (Hind Siliguri for Bengali, Inter for
  English/numbers) — no CDN dependency, works offline in dev.
