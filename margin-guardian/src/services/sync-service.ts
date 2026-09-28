import type { Config } from "../config.js";
import type { Db } from "../db.js";
import { buildAlerts } from "../metrics/alerts.js";
import { calculateMetrics } from "../metrics/engine.js";
import { DashboardRepository, InstallationRepository } from "../repositories.js";
import { TakeatClient, TakeatHttpError } from "../takeat/client.js";
import { TokenService } from "./token-service.js";

const DAY = 86_400_000;

function isoDate(date: Date): string { return date.toISOString().slice(0, 10); }

export function tableSessionWindows(start: Date, end: Date): Array<{ start: Date; end: Date }> {
  const windows: Array<{ start: Date; end: Date }> = [];
  let cursor = start.getTime();
  while (cursor <= end.getTime()) {
    const windowEnd = Math.min(end.getTime(), cursor + 3 * DAY - 1);
    windows.push({ start: new Date(cursor), end: new Date(windowEnd) });
    cursor = windowEnd + 1;
  }
  return windows;
}

export class SyncService {
  private readonly installations: InstallationRepository;
  private readonly dashboard: DashboardRepository;
  constructor(private readonly db: Db, private readonly config: Config, private readonly takeat: TakeatClient, private readonly tokens: TokenService) {
    this.installations = new InstallationRepository(db);
    this.dashboard = new DashboardRepository(db);
  }

  async run(installationId: string): Promise<void> {
    const installation = await this.installations.get(installationId);
    if (!installation || installation.status !== "active") throw new Error("installation_not_active");
    const periodEnd = new Date();
    const periodStart = new Date(periodEnd.getTime() - this.config.SYNC_LOOKBACK_DAYS * DAY);
    let accessToken = await this.tokens.accessToken(installationId);
    const execute = async () => {
      const sessionsById = new Map<number, Awaited<ReturnType<TakeatClient["tableSessions"]>>[number]>();
      for (const window of tableSessionWindows(periodStart, periodEnd)) {
        const sessions = await this.takeat.tableSessions(accessToken, installation.restaurant_id, window.start.toISOString(), window.end.toISOString());
        for (const session of sessions) sessionsById.set(session.id, session);
      }
      const cashFlows = await this.takeat.cashFlows(accessToken, installation.restaurant_id, isoDate(periodStart), isoDate(periodEnd));
      const [inputs, intermediaries] = await Promise.all([
        this.takeat.stock(accessToken, installation.restaurant_id, "inputs"),
        this.takeat.stock(accessToken, installation.restaurant_id, "intermediaries")
      ]);
      return { tableSessions: [...sessionsById.values()], cashFlows, inputs, intermediaries };
    };
    let source;
    try { source = await execute(); }
    catch (error) {
      if (!(error instanceof TakeatHttpError) || error.status !== 401) throw error;
      accessToken = await this.tokens.accessToken(installationId, true);
      source = await execute();
    }
    const result = calculateMetrics(source);
    const alerts = buildAlerts(result.metrics);
    await this.dashboard.save({ installationId, periodStart, periodEnd, metrics: result.metrics, evidence: result.evidence, alerts });
  }
}
