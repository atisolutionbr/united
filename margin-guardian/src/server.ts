import "dotenv/config.js";
import { loadConfig } from "./config.js";
import { createDb } from "./db.js";
import { buildApp } from "./app.js";

const config = loadConfig();
const db = createDb(config.DATABASE_URL);
const app = buildApp(config, db);

const close = async () => { await app.close(); await db.end(); };
process.on("SIGTERM", () => void close());
process.on("SIGINT", () => void close());

await app.listen({ host: config.HOST, port: config.PORT });
