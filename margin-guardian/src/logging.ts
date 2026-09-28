import pino from "pino";

export function createLogger(level = process.env.LOG_LEVEL ?? "info") {
  return pino({
    level,
    redact: {
      paths: [
        "req.headers.authorization",
        "req.headers.cookie",
        "res.headers.set-cookie",
        "*.access_token",
        "*.refresh_token",
        "*.code",
        "*.code_verifier",
        "*.state",
        "*.api_key",
        "*.authorization"
      ],
      censor: "[REDACTED]"
    },
    serializers: {
      req(req) {
        const path = typeof req.url === "string" ? req.url.split("?", 1)[0] : undefined;
        return { id: req.id, method: req.method, path, remoteAddress: req.remoteAddress };
      }
    }
  });
}
