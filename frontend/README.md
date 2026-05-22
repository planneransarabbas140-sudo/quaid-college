# Quaid College — React Hero (shadcn + Tailwind)

This folder adds a **React + TypeScript + Tailwind v4 + shadcn-style** frontend alongside the existing PHP ERP.

## Stack status

| Requirement | Status | Location |
|-------------|--------|----------|
| TypeScript | Yes | `tsconfig.json`, `tsconfig.app.json` |
| Tailwind CSS v4 | Yes | `@tailwindcss/vite`, `src/index.css` |
| shadcn structure | Yes | `components.json`, `components/ui/`, `lib/utils.ts` |
| Path alias `@/*` | Yes | Maps to `frontend/` root |

### Default paths

- **UI components:** `frontend/components/ui/` (shadcn convention: `@/components/ui`)
- **App components:** `frontend/components/`
- **Utilities:** `frontend/lib/utils.ts`
- **Global styles:** `frontend/src/index.css`
- **Entry:** `frontend/src/main.tsx` → `App.tsx`

### Why `components/ui` matters

shadcn CLI installs primitives (Button, Card, Dialog, etc.) into `components/ui`. Keeping this path lets you run `npx shadcn add button` without reconfiguring aliases, and matches documentation/examples that import from `@/components/ui/...`.

## Components

- `components/ui/hero-shader.tsx` — animated mesh gradient background (`ShaderBackground`)
- `components/hero-landing.tsx` — college-branded hero (programs, admissions, portal links)

## Commands

```bash
cd frontend
npm install
npm run dev      # http://localhost:5173
npm run build    # output in frontend/dist/
```

## PHP integration (optional)

After `npm run build`, embed the bundle on a PHP page:

```html
<div id="root"></div>
<script type="module" src="/frontend/dist/assets/index-*.js"></script>
<link rel="stylesheet" href="/frontend/dist/assets/index-*.css" />
```

Or link to the Vite dev server during development. For production, copy `dist/` assets into `assets/hero-react/` and reference them from `index.php`.

## Add more shadcn components

```bash
cd frontend
npx shadcn@latest add button
```

## Dependencies

- `@paper-design/shaders-react` — WebGL mesh gradients
- `lucide-react` — icons (logo, arrow)
- `clsx`, `tailwind-merge`, `class-variance-authority` — shadcn utilities
