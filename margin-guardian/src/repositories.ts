import { randomUUID } from "node:crypto";
import type { Db, DbClient } from "./db.js";

export type Installation = {
  id: string;
  restaurant_id: string;
  fantasy_name: string;
  granted_scopes: string[];
  status: "active" | "reconnect_required" | "revoked";
  access_token_ciphertext: string;
  access_expires_at: Date;
  refresh_token_ciphertext: string;
  token_version: number;
};

export class OAuthAttemptRepository {
  constructor(private readonly db: Db) {}

  async create(input: { stateHash: string; browserHash: string; verifierCiphertext: string; redirectUri: string; expiresAt: Date }): Promise<void> {
    await this.db.query(`INSERT INTO oauth_attempts(state_hash,browser_session_hash,code_verifier_ciphertext,redirect_uri,expires_at)
      VALUES($1,$2,$3,$4,$5)`, [input.stateHash, input.browserHash, input.verifierCiphertext, input.redirectUri, input.expiresAt]);
  }

  async findActive(browserHash: string): Promise<{ stateHash: string; verifierCiphertext: string; redirectUri: string } | null> {
    const result = await this.db.query<{ state_hash: string; code_verifier_ciphertext: string; redirect_uri: string }>(`SELECT state_hash,code_verifier_ciphertext,redirect_uri FROM oauth_attempts
      WHERE browser_session_hash=$1 AND consumed_at IS NULL AND expires_at>now() ORDER BY created_at DESC LIMIT 1`, [browserHash]);
    const row = result.rows[0];
    return row ? { stateHash: row.state_hash, verifierCiphertext: row.code_verifier_ciphertext, redirectUri: row.redirect_uri } : null;
  }

  async consume(stateHash: string, browserHash: string): Promise<{ verifierCiphertext: string; redirectUri: string } | null> {
    const result = await this.db.query<{ code_verifier_ciphertext: string; redirect_uri: string }>(`UPDATE oauth_attempts SET consumed_at=now()
      WHERE state_hash=$1 AND browser_session_hash=$2 AND consumed_at IS NULL AND expires_at > now()
      RETURNING code_verifier_ciphertext, redirect_uri`, [stateHash, browserHash]);
    const row = result.rows[0];
    return row ? { verifierCiphertext: row.code_verifier_ciphertext, redirectUri: row.redirect_uri } : null;
  }
}

export class InstallationRepository {
  constructor(private readonly db: Db) {}

  async upsertFromOAuth(input: { restaurantId: string; fantasyName: string; scopes: string[]; accessCiphertext: string; accessExpiresAt: Date; refreshCiphertext: string }): Promise<Installation> {
    const id = randomUUID();
    const result = await this.db.query<Installation>(`INSERT INTO installations(id,restaurant_id,fantasy_name,granted_scopes,status,access_token_ciphertext,access_expires_at,refresh_token_ciphertext)
      VALUES($1,$2,$3,$4,'active',$5,$6,$7)
      ON CONFLICT(restaurant_id) DO UPDATE SET fantasy_name=excluded.fantasy_name, granted_scopes=excluded.granted_scopes,
        status='active', access_token_ciphertext=excluded.access_token_ciphertext, access_expires_at=excluded.access_expires_at,
        refresh_token_ciphertext=excluded.refresh_token_ciphertext, token_version=installations.token_version+1, updated_at=now()
      RETURNING *`, [id, input.restaurantId, input.fantasyName, input.scopes, input.accessCiphertext, input.accessExpiresAt, input.refreshCiphertext]);
    return result.rows[0]!;
  }

  async get(id: string, client: Db | DbClient = this.db): Promise<Installation | null> {
    const result = await client.query<Installation>("SELECT * FROM installations WHERE id=$1", [id]);
    return result.rows[0] ?? null;
  }

  async getForUpdate(id: string, client: DbClient): Promise<Installation | null> {
    const result = await client.query<Installation>("SELECT * FROM installations WHERE id=$1 FOR UPDATE", [id]);
    return result.rows[0] ?? null;
  }

  async updateTokens(client: DbClient, input: { id: string; accessCiphertext: string; accessExpiresAt: Date; refreshCiphertext: string; fantasyName?: string; scopes: string[] }): Promise<void> {
    await client.query(`UPDATE installations SET access_token_ciphertext=$2, access_expires_at=$3, refresh_token_ciphertext=$4,
      fantasy_name=COALESCE($5,fantasy_name), granted_scopes=$6, token_version=token_version+1, status='active', last_error_code=NULL, updated_at=now()
      WHERE id=$1`, [input.id, input.accessCiphertext, input.accessExpiresAt, input.refreshCiphertext, input.fantasyName ?? null, input.scopes]);
  }

  async setStatus(id: string, status: Installation["status"], errorCode?: string): Promise<void> {
    await this.db.query("UPDATE installations SET status=$2,last_error_code=$3,updated_at=now() WHERE id=$1", [id, status, errorCode ?? null]);
  }
}

export class SessionRepository {
  constructor(private readonly db: Db) {}

  async create(installationId: string, sessionHash: string, csrfHash: string, expiresAt: Date): Promise<void> {
    await this.db.query("INSERT INTO app_sessions(session_hash,installation_id,csrf_hash,expires_at) VALUES($1,$2,$3,$4)", [sessionHash, installationId, csrfHash, expiresAt]);
  }

  async resolve(sessionHash: string): Promise<{ installationId: string; csrfHash: string } | null> {
    const result = await this.db.query<{ installation_id: string; csrf_hash: string }>("SELECT installation_id,csrf_hash FROM app_sessions WHERE session_hash=$1 AND expires_at>now()", [sessionHash]);
    const row = result.rows[0];
    return row ? { installationId: row.installation_id, csrfHash: row.csrf_hash } : null;
  }

  async delete(sessionHash: string): Promise<void> {
    await this.db.query("DELETE FROM app_sessions WHERE session_hash=$1", [sessionHash]);
  }
}

export class JobRepository {
  constructor(private readonly db: Db) {}

  async enqueue(installationId: string, runAfter = new Date()): Promise<void> {
    await this.db.query("INSERT INTO sync_jobs(installation_id,status,run_after) VALUES($1,'pending',$2)", [installationId, runAfter]);
  }

  async claim(workerId: string): Promise<{ id: string; installationId: string; attempts: number } | null> {
    const result = await this.db.query<{ id: string; installation_id: string; attempts: number }>(`WITH candidate AS (
      SELECT id FROM sync_jobs WHERE status='pending' AND run_after<=now() ORDER BY run_after,id FOR UPDATE SKIP LOCKED LIMIT 1
    ) UPDATE sync_jobs j SET status='running',locked_at=now(),locked_by=$1,attempts=attempts+1,updated_at=now()
      FROM candidate WHERE j.id=candidate.id RETURNING j.id,j.installation_id,j.attempts`, [workerId]);
    const row = result.rows[0];
    return row ? { id: row.id, installationId: row.installation_id, attempts: row.attempts } : null;
  }

  async complete(id: string): Promise<void> {
    await this.db.query("UPDATE sync_jobs SET status='done',updated_at=now() WHERE id=$1", [id]);
  }

  async fail(id: string, errorCode: string, attempts: number): Promise<void> {
    const retry = attempts < 5;
    await this.db.query(`UPDATE sync_jobs SET status=$2,last_error_code=$3,run_after=now() + ($4 || ' seconds')::interval,locked_at=NULL,locked_by=NULL,updated_at=now() WHERE id=$1`, [id, retry ? "pending" : "failed", errorCode, Math.min(900, 15 * 2 ** attempts)]);
  }

  async cancelForInstallation(installationId: string): Promise<void> {
    await this.db.query("UPDATE sync_jobs SET status='cancelled',updated_at=now() WHERE installation_id=$1 AND status IN ('pending','running')", [installationId]);
  }
}

export class DashboardRepository {
  constructor(private readonly db: Db) {}

  async save(input: { installationId: string; periodStart: Date; periodEnd: Date; metrics: unknown; evidence: unknown; alerts: Array<{ type: string; severity: string; quality: string; title: string; explanation: string; action: string; metric: unknown; evidenceRefs: string[] }> }): Promise<void> {
    const snapshotId = randomUUID();
    const client = await this.db.connect();
    try {
      await client.query("BEGIN");
      await client.query("INSERT INTO metric_snapshots(id,installation_id,period_start,period_end,metrics,evidence) VALUES($1,$2,$3,$4,$5,$6)", [snapshotId, input.installationId, input.periodStart, input.periodEnd, JSON.stringify(input.metrics), JSON.stringify(input.evidence)]);
      for (const alert of input.alerts) {
        await client.query(`INSERT INTO alerts(id,installation_id,metric_snapshot_id,alert_type,severity,quality,title,explanation,action,metric,evidence_refs)
          VALUES($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11)`, [randomUUID(), input.installationId, snapshotId, alert.type, alert.severity, alert.quality, alert.title, alert.explanation, alert.action, JSON.stringify(alert.metric), JSON.stringify(alert.evidenceRefs)]);
      }
      await client.query("UPDATE installations SET last_sync_at=now(),last_error_code=NULL,updated_at=now() WHERE id=$1", [input.installationId]);
      await client.query("COMMIT");
    } catch (error) {
      await client.query("ROLLBACK");
      throw error;
    } finally { client.release(); }
  }

  async latest(installationId: string): Promise<{ snapshot: Record<string, unknown> | null; alerts: Array<Record<string, unknown>> }> {
    const snapshotResult = await this.db.query<Record<string, unknown>>("SELECT id,period_start,period_end,metrics,evidence,created_at FROM metric_snapshots WHERE installation_id=$1 ORDER BY created_at DESC LIMIT 1", [installationId]);
    const snapshot = snapshotResult.rows[0] ?? null;
    if (!snapshot) return { snapshot: null, alerts: [] };
    const alerts = await this.db.query<Record<string, unknown>>("SELECT id,alert_type,severity,quality,title,explanation,action,metric,evidence_refs,status,created_at FROM alerts WHERE installation_id=$1 AND metric_snapshot_id=$2 ORDER BY CASE severity WHEN 'high' THEN 1 WHEN 'medium' THEN 2 WHEN 'low' THEN 3 ELSE 4 END,created_at DESC", [installationId, snapshot.id]);
    return { snapshot, alerts: alerts.rows };
  }

  async feedback(installationId: string, alertId: string, useful: boolean): Promise<boolean> {
    const owns = await this.db.query("SELECT 1 FROM alerts WHERE id=$1 AND installation_id=$2", [alertId, installationId]);
    if (!owns.rowCount) return false;
    await this.db.query("INSERT INTO alert_feedback(id,installation_id,alert_id,useful) VALUES($1,$2,$3,$4)", [randomUUID(), installationId, alertId, useful]);
    return true;
  }
}
