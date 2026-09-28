import type { Config } from "../config.js";
import { inTransaction, type Db } from "../db.js";
import { InstallationRepository } from "../repositories.js";
import { TokenCipher } from "../security/crypto.js";
import { TakeatClient, TakeatHttpError } from "../takeat/client.js";

export class TokenService {
  private readonly repo: InstallationRepository;
  constructor(private readonly db: Db, private readonly config: Config, private readonly cipher: TokenCipher, private readonly takeat: TakeatClient) {
    this.repo = new InstallationRepository(db);
  }

  async accessToken(installationId: string, force = false): Promise<string> {
    const installation = await this.repo.get(installationId);
    if (!installation || installation.status !== "active") throw new Error("installation_not_active");
    if (!force && installation.access_expires_at.getTime() > Date.now() + 60_000) return this.cipher.decrypt(installation.access_token_ciphertext);
    const refreshed = await inTransaction<string | null>(this.db, async (client) => {
      await client.query("SELECT pg_advisory_xact_lock(hashtextextended($1,0))", [installationId]);
      const locked = await this.repo.getForUpdate(installationId, client);
      if (!locked || locked.status !== "active") throw new Error("installation_not_active");
      if (!force && locked.access_expires_at.getTime() > Date.now() + 60_000) return this.cipher.decrypt(locked.access_token_ciphertext);
      try {
        const tokens = await this.takeat.refresh(this.cipher.decrypt(locked.refresh_token_ciphertext));
        await this.repo.updateTokens(client, {
          id: installationId,
          accessCiphertext: this.cipher.encrypt(tokens.access_token),
          accessExpiresAt: new Date(Date.now() + tokens.expires_in * 1000),
          refreshCiphertext: this.cipher.encrypt(tokens.refresh_token),
          ...(tokens.restaurant ? { fantasyName: tokens.restaurant.fantasy_name } : {}),
          scopes: tokens.scope.trim().split(/\s+/).filter(Boolean)
        });
        return tokens.access_token;
      } catch (error) {
        if (error instanceof TakeatHttpError && error.code === "invalid_grant") {
          await client.query("UPDATE installations SET status='reconnect_required',last_error_code='invalid_grant',updated_at=now() WHERE id=$1", [installationId]);
          return null;
        }
        throw error;
      }
    });
    if (refreshed === null) throw new Error("reconnect_required");
    return refreshed;
  }
}
