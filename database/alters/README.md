# database/alters

Schema changes for the **live** database, applied from the admin **Database**
page (Super admin only) — no shell access needed.

## How it works

1. A developer adds a `.sql` file here, named `NNNN_description.sql`
   (4-digit sequence prefix — they run in filename order).
2. The file contains **additive, safe** SQL only:
   `ALTER TABLE ... ADD COLUMN`, `CREATE TABLE`, `CREATE INDEX`, data backfills.
   **Never** `DROP`, `RENAME`, or destructive `MODIFY`.
3. In the admin panel → **Database** → the file shows under *Pending changes*
   with its full contents visible.
4. Super admin clicks **Apply**. Each statement runs once; the file is recorded
   in the `schema_alters` table so it never runs again.
   "Column already exists" style errors are treated as *already applied* and
   skipped, so re-importing a dump and re-applying is safe.

## Rules for the `.sql` files

- One statement per line, each ending with `;` (the runner splits on that).
- Keep statements idempotent where practical (guard backfills with `WHERE`).
- Test locally first: `php artisan db:alter --pending` then `php artisan db:alter --apply`.

## Applied history

`schema_alters` table: `filename`, `checksum`, `statements`, `applied_at`,
`applied_by`, `ok`, `error`.
