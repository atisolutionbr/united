import { createHash, randomBytes } from "node:crypto";

export type PkceAttempt = { state: string; verifier: string; challenge: string };

export function createPkceAttempt(): PkceAttempt {
  const state = randomBytes(32).toString("base64url");
  const verifier = randomBytes(64).toString("base64url");
  const challenge = createHash("sha256").update(verifier).digest("base64url");
  return { state, verifier, challenge };
}
