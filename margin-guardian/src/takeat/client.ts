import type { ZodType } from "zod";
import type { Config } from "../config.js";
import {
  CashFlowResponseSchema,
  InputsResponseSchema,
  IntermediariesResponseSchema,
  TableSessionsSchema,
  TokenResponseSchema,
  type CashFlowResponse,
  type StockItem,
  type TableSession,
  type TokenResponse
} from "./schemas.js";

export class TakeatHttpError extends Error {
  constructor(public readonly status: number, public readonly code: string, message: string, public readonly retryAfterMs?: number) {
    super(message);
  }
}

function retryAfterMs(value: string | null): number | undefined {
  if (!value) return undefined;
  if (/^\d+$/.test(value)) return Number(value) * 1000;
  const date = Date.parse(value);
  return Number.isNaN(date) ? undefined : Math.max(0, date - Date.now());
}

async function sleep(ms: number): Promise<void> {
  await new Promise((resolve) => setTimeout(resolve, ms));
}

export class TakeatClient {
  constructor(private readonly config: Config, private readonly fetchImpl: typeof fetch = fetch) {}

  private async form(path: string, body: URLSearchParams): Promise<TokenResponse | undefined> {
    const response = await this.fetchImpl(new URL(path, this.config.TAKEAT_API_URL), {
      method: "POST",
      headers: { "content-type": "application/x-www-form-urlencoded" },
      body,
      signal: AbortSignal.timeout(this.config.TAKEAT_TIMEOUT_MS)
    });
    if (path === "/oauth/revoke" && response.ok) return undefined;
    const json = await response.json().catch(() => ({})) as Record<string, unknown>;
    if (!response.ok) {
      throw new TakeatHttpError(response.status, String(json.error ?? `http_${response.status}`), String(json.error_description ?? "Takeat OAuth request failed"));
    }
    return TokenResponseSchema.parse(json);
  }

  exchangeCode(input: { code: string; verifier: string; redirectUri: string }): Promise<TokenResponse> {
    return this.form("/oauth/token", new URLSearchParams({
      grant_type: "authorization_code",
      client_id: this.config.TAKEAT_CLIENT_ID,
      code: input.code,
      redirect_uri: input.redirectUri,
      code_verifier: input.verifier
    })) as Promise<TokenResponse>;
  }

  refresh(refreshToken: string): Promise<TokenResponse> {
    return this.form("/oauth/token", new URLSearchParams({
      grant_type: "refresh_token",
      client_id: this.config.TAKEAT_CLIENT_ID,
      refresh_token: refreshToken
    })) as Promise<TokenResponse>;
  }

  async revoke(refreshToken: string): Promise<void> {
    await this.form("/oauth/revoke", new URLSearchParams({
      client_id: this.config.TAKEAT_CLIENT_ID,
      token: refreshToken,
      token_type_hint: "refresh_token"
    }));
  }

  private async get<T>(path: string, accessToken: string, schema: ZodType<T>): Promise<T> {
    let lastError: unknown;
    for (let attempt = 0; attempt <= this.config.TAKEAT_MAX_GET_RETRIES; attempt += 1) {
      try {
        const response = await this.fetchImpl(new URL(path, this.config.TAKEAT_API_URL), {
          headers: { authorization: `Bearer ${accessToken}`, accept: "application/json" },
          signal: AbortSignal.timeout(this.config.TAKEAT_TIMEOUT_MS)
        });
        if (response.ok) return schema.parse(await response.json());
        const wait = retryAfterMs(response.headers.get("retry-after"));
        const error = new TakeatHttpError(response.status, `http_${response.status}`, "Takeat data request failed", wait);
        if (response.status === 401 || response.status === 403 || (response.status !== 429 && response.status < 500)) throw error;
        lastError = error;
        if (attempt === this.config.TAKEAT_MAX_GET_RETRIES) throw error;
        await sleep(wait ?? Math.min(5000, 250 * 2 ** attempt) + Math.floor(Math.random() * 150));
      } catch (error) {
        if (error instanceof TakeatHttpError) throw error;
        lastError = error;
        if (attempt === this.config.TAKEAT_MAX_GET_RETRIES) throw error;
        await sleep(Math.min(5000, 250 * 2 ** attempt) + Math.floor(Math.random() * 150));
      }
    }
    throw lastError;
  }

  tableSessions(accessToken: string, restaurantId: string, start: string, end: string): Promise<TableSession[]> {
    const query = new URLSearchParams({ restaurant_id: restaurantId, start_date: start, end_date: end });
    return this.get(`/v1/table-sessions?${query}`, accessToken, TableSessionsSchema);
  }

  async cashFlows(accessToken: string, restaurantId: string, start: string, end: string): Promise<CashFlowResponse> {
    let offset = 0;
    let first: CashFlowResponse | undefined;
    const entries: CashFlowResponse["cash_flows"] = [];
    do {
      const query = new URLSearchParams({ restaurant_id: restaurantId, start_date: start, end_date: end, date_type: "due_date", offset: String(offset) });
      const page = await this.get(`/v1/financial/cash-flows?${query}`, accessToken, CashFlowResponseSchema);
      first ??= page;
      entries.push(...page.cash_flows);
      offset += page.limit;
      if (page.remaining === 0) break;
    } while (true);
    if (!first) throw new Error("Cash flow pagination returned no first page");
    return { ...first, count: entries.length, remaining: 0, cash_flows: entries };
  }

  async stock(accessToken: string, restaurantId: string, kind: "inputs" | "intermediaries"): Promise<StockItem[]> {
    let offset = 0;
    const items: StockItem[] = [];
    do {
      const query = new URLSearchParams({ restaurant_id: restaurantId, offset: String(offset) });
      if (kind === "inputs") {
        const page = await this.get(`/v1/inputs?${query}`, accessToken, InputsResponseSchema);
        items.push(...page.inputs);
        if (page.remaining === 0) break;
        offset += page.limit;
      } else {
        const page = await this.get(`/v1/intermediaries?${query}`, accessToken, IntermediariesResponseSchema);
        items.push(...page.intermediaries);
        if (page.remaining === 0) break;
        offset += page.limit;
      }
    } while (true);
    return items;
  }
}
