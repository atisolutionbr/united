import "dotenv/config.js";
import { randomUUID } from "node:crypto";
import { loadConfig } from "./config.js";
import { createDb } from "./db.js";
import { createLogger } from "./logging.js";
import { JobRepository } from "./repositories.js";
import { TokenCipher } from "./security/crypto.js";
import { SyncService } from "./services/sync-service.js";
import { TokenService } from "./services/token-service.js";
import { TakeatClient, TakeatHttpError } from "./takeat/client.js";

const config = loadConfig();
const db = createDb(config.DATABASE_URL);
const logger = createLogger();
const takeat = new TakeatClient(config);
const cipher = new TokenCipher(config.encryptionKey);
const tokens = new TokenService(db, config, cipher, takeat);
const sync = new SyncService(db, config, takeat, tokens);
const jobs = new JobRepository(db);
const workerId = randomUUID();
let running = true;

process.on("SIGTERM", () => { running = false; });
process.on("SIGINT", () => { running = false; });

while (running) {
  const job = await jobs.claim(workerId);
  if (!job) {
    await new Promise((resolve) => setTimeout(resolve, config.WORKER_POLL_MS));
    continue;
  }
  try {
    await sync.run(job.installationId);
    await jobs.complete(job.id);
    await jobs.enqueue(job.installationId, new Date(Date.now() + config.SYNC_INTERVAL_MINUTES * 60_000));
    logger.info({ installation_id: job.installationId, job_id: job.id }, "sync_completed");
  } catch (error) {
    const code = error instanceof TakeatHttpError ? error.code : error instanceof Error ? error.message : "unknown_error";
    await jobs.fail(job.id, code.slice(0, 120), job.attempts);
    logger.error({ installation_id: job.installationId, job_id: job.id, error_code: code }, "sync_failed");
  }
}

await db.end();
