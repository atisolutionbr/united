import "dotenv/config.js";
import { readdir, readFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { createDb, inTransaction } from "./db.js";
import { loadConfig } from "./config.js";

export async function migrate(connectionString: string): Promise<void> {
  const db = createDb(connectionString);
  const root = path.resolve(process.cwd(), "migrations");
  const files = (await readdir(root)).filter((name) => name.endsWith(".sql")).sort();
  try {
    await db.query("CREATE TABLE IF NOT EXISTS schema_migrations (version text PRIMARY KEY, applied_at timestamptz NOT NULL DEFAULT now())");
    for (const file of files) {
      const exists = await db.query<{ version: string }>("SELECT version FROM schema_migrations WHERE version=$1", [file]);
      if (exists.rowCount) continue;
      const sql = await readFile(path.join(root, file), "utf8");
      await inTransaction(db, async (client) => {
        await client.query(sql);
        await client.query("INSERT INTO schema_migrations(version) VALUES($1) ON CONFLICT DO NOTHING", [file]);
      });
    }
  } finally {
    await db.end();
  }
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const config = loadConfig();
  await migrate(config.DATABASE_URL);
}
