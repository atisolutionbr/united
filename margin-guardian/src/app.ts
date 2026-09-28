import { randomUUID } from "node:crypto";
import Fastify, { type FastifyRequest } from "fastify";
import cookie from "@fastify/cookie";
import formbody from "@fastify/formbody";
import rateLimit from "@fastify/rate-limit";
import type { Config } from "./config.js";
import type { Db } from "./db.js";
import { createLogger } from "./logging.js";
import { DashboardRepository, InstallationRepository, JobRepository, OAuthAttemptRepository, SessionRepository } from "./repositories.js";
import { opaqueToken, safeEqualText, sha256, TokenCipher } from "./security/crypto.js";
import { createPkceAttempt } from "./security/pkce.js";
import { TakeatClient } from "./takeat/client.js";
import { dashboard, demoPage, errorPage, landing } from "./web/html.js";

type SessionContext = { installationId: string; csrfHash: string; sessionHash: string };

export function buildApp(config: Config, db: Db, takeat = new TakeatClient(config)) {
  const logger = createLogger();
  const app = Fastify({ loggerInstance: logger, genReqId: () => randomUUID() });
  const cipher = new TokenCipher(config.encryptionKey);
  const attempts = new OAuthAttemptRepository(db);
  const installations = new InstallationRepository(db);
  const sessions = new SessionRepository(db);
  const jobs = new JobRepository(db);
  const dashboardRepo = new DashboardRepository(db);
  const path = (route: string) => `${config.BASE_PATH}${route}`;

  app.register(cookie);
  app.register(formbody);
  app.register(rateLimit, { max: 120, timeWindow: "1 minute" });
  app.addHook("onSend", async (_request, reply, payload) => {
    reply.header("content-security-policy", "default-src 'self'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
    reply.header("x-content-type-options", "nosniff");
    reply.header("referrer-policy", "no-referrer");
    if (config.NODE_ENV === "production") reply.header("strict-transport-security", "max-age=31536000; includeSubDomains");
    return payload;
  });

  async function sessionContext(request: FastifyRequest): Promise<SessionContext | null> {
    const raw = request.cookies.mg_session;
    if (!raw) return null;
    const sessionHash = sha256(raw);
    const row = await sessions.resolve(sessionHash);
    return row ? { ...row, sessionHash } : null;
  }

  function validCsrf(context: SessionContext, body: unknown): boolean {
    const token = typeof body === "object" && body !== null && "csrf" in body ? String((body as { csrf: unknown }).csrf) : "";
    return safeEqualText(context.csrfHash, sha256(token));
  }

  app.get(path("/"), async (_request, reply) => reply.type("text/html; charset=utf-8").send(landing({ demoMode: config.DEMO_MODE, oauthEnabled: config.OAUTH_ENABLED, basePath: config.BASE_PATH })));
  app.get(path("/health/live"), async () => ({ status: "ok" }));
  app.get(path("/health/ready"), async (_request, reply) => {
    try { await db.query("SELECT 1"); return { status: "ready" }; }
    catch { return reply.code(503).send({ status: "not_ready" }); }
  });
  app.get(path("/demo"), async (_request, reply) => config.DEMO_MODE ? reply.type("text/html; charset=utf-8").send(demoPage(config.BASE_PATH)) : reply.code(404).send());
  app.post(path("/demo"), async (_request, reply) => config.DEMO_MODE ? reply.redirect(path("/demo")) : reply.code(404).send());

  app.get(path("/auth/takeat/start"), { config: { rateLimit: { max: 10, timeWindow: "1 minute" } } }, async (_request, reply) => {
    if (!config.OAUTH_ENABLED) return reply.code(503).type("text/html; charset=utf-8").send(errorPage("OAuth ainda nao configurado", "A Takeat ainda precisa fornecer o Client ID e a URL oficial do portal.", config.BASE_PATH));
    const browserSession = opaqueToken();
    const pkce = createPkceAttempt();
    await attempts.create({ stateHash: sha256(pkce.state), browserHash: sha256(browserSession), verifierCiphertext: cipher.encrypt(pkce.verifier), redirectUri: config.TAKEAT_REDIRECT_URI, expiresAt: new Date(Date.now() + config.OAUTH_STATE_TTL_SECONDS * 1000) });
    reply.setCookie("mg_browser", browserSession, { httpOnly: true, sameSite: "lax", secure: config.NODE_ENV === "production", path: path("/auth/takeat/callback"), maxAge: config.OAUTH_STATE_TTL_SECONDS });
    const url = new URL(config.TAKEAT_OAUTH_PORTAL_URL);
    url.searchParams.set("client_id", config.TAKEAT_CLIENT_ID);
    url.searchParams.set("redirect_uri", config.TAKEAT_REDIRECT_URI);
    url.searchParams.set("code_challenge", pkce.challenge);
    url.searchParams.set("code_challenge_method", "S256");
    url.searchParams.set("state", pkce.state);
    url.searchParams.set("scope", config.takeatScopes.join(" "));
    return reply.redirect(url.toString());
  });

  app.get<{ Querystring: { code?: string; state?: string; error?: string } }>(path("/auth/takeat/callback"), async (request, reply) => {
    if (request.query.error === "access_denied") return reply.code(403).type("text/html; charset=utf-8").send(errorPage("Acesso nao autorizado", "Voce recusou a instalacao. Nenhum dado foi acessado.", config.BASE_PATH));
    const browser = request.cookies.mg_browser;
    const { code, state } = request.query;
    if (!browser || !code || !state) return reply.code(400).type("text/html; charset=utf-8").send(errorPage("Callback invalido", "A tentativa de instalacao esta incompleta. Inicie novamente.", config.BASE_PATH));
    const candidate = await attempts.findActive(sha256(browser));
    if (!candidate || !safeEqualText(candidate.stateHash, sha256(state))) return reply.code(400).type("text/html; charset=utf-8").send(errorPage("State invalido", "A tentativa expirou, ja foi usada ou nao corresponde a este navegador.", config.BASE_PATH));
    const consumed = await attempts.consume(candidate.stateHash, sha256(browser));
    if (!consumed) return reply.code(400).type("text/html; charset=utf-8").send(errorPage("Tentativa consumida", "Inicie uma nova instalacao.", config.BASE_PATH));
    const tokenSet = await takeat.exchangeCode({ code, verifier: cipher.decrypt(consumed.verifierCiphertext), redirectUri: consumed.redirectUri });
    if (!tokenSet.restaurant) throw new Error("authorization_code response did not include restaurant");
    const installation = await installations.upsertFromOAuth({ restaurantId: tokenSet.restaurant.id, fantasyName: tokenSet.restaurant.fantasy_name, scopes: tokenSet.scope.trim().split(/\s+/).filter(Boolean), accessCiphertext: cipher.encrypt(tokenSet.access_token), accessExpiresAt: new Date(Date.now() + tokenSet.expires_in * 1000), refreshCiphertext: cipher.encrypt(tokenSet.refresh_token) });
    const sessionToken = opaqueToken();
    const csrf = opaqueToken();
    await sessions.create(installation.id, sha256(sessionToken), sha256(csrf), new Date(Date.now() + config.APP_SESSION_TTL_SECONDS * 1000));
    await jobs.enqueue(installation.id);
    reply.clearCookie("mg_browser", { path: path("/auth/takeat/callback") });
    reply.setCookie("mg_session", sessionToken, { httpOnly: true, sameSite: "lax", secure: config.NODE_ENV === "production", path: path("/"), maxAge: config.APP_SESSION_TTL_SECONDS });
    reply.setCookie("mg_csrf", csrf, { httpOnly: false, sameSite: "strict", secure: config.NODE_ENV === "production", path: path("/"), maxAge: config.APP_SESSION_TTL_SECONDS });
    return reply.redirect(path("/app"));
  });

  app.get(path("/app"), async (request, reply) => {
    const context = await sessionContext(request);
    if (!context) return reply.redirect(path("/"));
    const installation = await installations.get(context.installationId);
    if (!installation) return reply.redirect(path("/"));
    const data = await dashboardRepo.latest(installation.id);
    return reply.type("text/html; charset=utf-8").send(dashboard({ installation, ...data, csrf: request.cookies.mg_csrf ?? "", basePath: config.BASE_PATH }));
  });

  app.post(path("/sync"), async (request, reply) => {
    const context = await sessionContext(request);
    if (!context || !validCsrf(context, request.body)) return reply.code(403).type("text/html; charset=utf-8").send(errorPage("Requisicao recusada", "Sessao ou CSRF invalido.", config.BASE_PATH));
    await jobs.enqueue(context.installationId);
    return reply.redirect(path("/app"));
  });

  app.post<{ Body: { csrf?: string; alert_id?: string; useful?: string } }>(path("/feedback"), async (request, reply) => {
    const context = await sessionContext(request);
    if (!context || !validCsrf(context, request.body) || !request.body.alert_id) return reply.code(403).send();
    const ok = await dashboardRepo.feedback(context.installationId, request.body.alert_id, request.body.useful === "true");
    return ok ? reply.redirect(path("/app")) : reply.code(404).send();
  });

  app.post(path("/disconnect"), async (request, reply) => {
    const context = await sessionContext(request);
    if (!context || !validCsrf(context, request.body)) return reply.code(403).send();
    const installation = await installations.get(context.installationId);
    if (installation) {
      await takeat.revoke(cipher.decrypt(installation.refresh_token_ciphertext));
      await installations.setStatus(installation.id, "revoked");
      await jobs.cancelForInstallation(installation.id);
    }
    await sessions.delete(context.sessionHash);
    reply.clearCookie("mg_session", { path: path("/") });
    reply.clearCookie("mg_csrf", { path: path("/") });
    return reply.redirect(path("/"));
  });

  app.setErrorHandler((error, request, reply) => {
    const safeError = error instanceof Error ? { name: error.name, message: error.message } : { name: "UnknownError", message: "unknown" };
    request.log.error({ err: safeError }, "request_failed");
    if (request.url.startsWith(path("/health/"))) return reply.code(500).send({ status: "error" });
    return reply.code(500).type("text/html; charset=utf-8").send(errorPage("Nao foi possivel concluir", "Tente novamente. Se o problema continuar, entre em contato com o suporte.", config.BASE_PATH));
  });
  return app;
}
