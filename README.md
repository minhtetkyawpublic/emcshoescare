# EMC Shoes Care Myanmar

Mobile-first React Progressive Web App for EMC Shoes Care Myanmar. The planned production stack is React, plain PHP, and MySQL.

Phase 1 contains the bilingual public landing page and a frontend-only demo order form. See [ROADMAP.md](./ROADMAP.md) for the full delivery plan.

## Run locally

```bash
npm install
npm run dev
```

## Verify a production build

```bash
npm run lint
npm run build
```

The interface text for both English and Myanmar is kept in `src/i18n/translations.js`. Phase 1 package names and demonstration prices are also defined there for easy replacement.
