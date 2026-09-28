import { z } from "zod";

const truthy = new Set(["1", "true", "yes", "on"]);

const EnvSchema = z.object({
  NODE_ENV: z.enum(["development", "test", "production"]).default("development"),
  HOST: z.string().default("0.0.0.0"),
  PORT: z.coerce.number().int().min(1).max(65535).default(3000),
  BASE_PATH: z.string().default("").transform((value) => {
    const trimmed = value.trim();
    if (!trimmed || trimmed === "/") return "";
    const normalized = `/${trimmed.replace(/^\/+|\/+$/g, "")}`;
    if (!/^\/[A-Za-z0-9/_-]+$/.test(normalized)) throw new Error("BASE_PATH contains invalid characters");
    return normalized;
  }),
  PUBLIC_BASE_URL: z.string().url().default("http://localhost:3000"),
  DATABASE_URL: z.string().min(1),
  TAKEAT_CLIENT_ID: z.string().regex(/^tk_app_[A-Za-z0-9_-]+$/),
  TAKEAT_OAUTH_PORTAL_URL: z.string().url(),
  TAKEAT_API_URL: z.string().url().default("https://public-api.takeat.app"),
  TAKEAT_REDIRECT_URI: z.string().url(),
  TAKEAT_SCOPES: z.string().default("table-sessions:read financial:read inputs:read intermediaries:read"),
  TOKEN_ENCRYPTION_KEY_BASE64: z.string().min(1),
  OAUTH_STATE_TTL_SECONDS: z.coerce.number().int().min(60).max(1800).default(600),
  APP_SESSION_TTL_SECONDS: z.coerce.number().int().min(300).max(86400).default(28800),
  TAKEAT_TIMEOUT_MS: z.coerce.number().int().min(1000).max(60000).default(10000),
  TAKEAT_MAX_GET_RETRIES: z.coerce.number().int().min(0).max(5).default(3),
  SYNC_LOOKBACK_DAYS: z.coerce.number().int().min(1).max(92).default(30),
  WORKER_POLL_MS: z.coerce.number().int().min(1000).max(60000).default(5000),
  SYNC_INTERVAL_MINUTES: z.coerce.number().int().min(5).max(1440).default(30),
  DEMO_MODE: z.string().default("false").transform((value) => truthy.has(value.toLowerCase())),
  ALLOW_PUBLIC_DEMO: z.string().default("false").transform((value) => truthy.has(value.toLowerCase())),
  OAUTH_ENABLED: z.string().default("true").transform((value) => truthy.has(value.toLowerCase())),
  LLM_PROVIDER: z.enum(["disabled", "http-json"]).default("disabled"),
  LLM_API_URL: z.string().optional(),
  LLM_API_KEY: z.string().optional(),
  LLM_MODEL: z.string().optional()
});

export type Config = ReturnType<typeof loadConfig>;

export function loadConfig(env: NodeJS.ProcessEnv = process.env) {
  const parsed = EnvSchema.parse(env);
  const key = Buffer.from(parsed.TOKEN_ENCRYPTION_KEY_BASE64, "base64");
  if (key.length !== 32) throw new Error("TOKEN_ENCRYPTION_KEY_BASE64 must decode to 32 bytes");
  if (parsed.NODE_ENV === "production" && parsed.DEMO_MODE && !parsed.ALLOW_PUBLIC_DEMO) {
    throw new Error("DEMO_MODE in production requires ALLOW_PUBLIC_DEMO=true");
  }
  if (parsed.NODE_ENV === "production") {
    for (const [name, value] of [["PUBLIC_BASE_URL", parsed.PUBLIC_BASE_URL], ["TAKEAT_REDIRECT_URI", parsed.TAKEAT_REDIRECT_URI], ["TAKEAT_OAUTH_PORTAL_URL", parsed.TAKEAT_OAUTH_PORTAL_URL], ["TAKEAT_API_URL", parsed.TAKEAT_API_URL]] as const) {
      if (!value.startsWith("https://")) throw new Error(`${name} must use HTTPS in production`);
    }
  }
  const scopes = parsed.TAKEAT_SCOPES.trim().split(/\s+/).filter(Boolean);
  const allowed = new Set(["table-sessions:read", "financial:read", "inputs:read", "intermediaries:read"]);
  if (scopes.some((scope) => !allowed.has(scope))) throw new Error("TAKEAT_SCOPES contains a scope outside the approved MVP specification");
  if (parsed.LLM_PROVIDER === "http-json" && (!parsed.LLM_API_URL || !parsed.LLM_MODEL)) {
    throw new Error("LLM_API_URL and LLM_MODEL are required for http-json provider");
  }
  return { ...parsed, encryptionKey: key, takeatScopes: scopes };
}
