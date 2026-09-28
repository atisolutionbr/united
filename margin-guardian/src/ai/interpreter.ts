import { z } from "zod";
import type { Alert } from "../metrics/alerts.js";

const InterpretationSchema = z.object({
  summaries: z.array(z.object({
    type: z.string(),
    explanation: z.string().max(600),
    action: z.string().max(400)
  }).strict())
}).strict();

export type Interpretation = z.infer<typeof InterpretationSchema>;

export interface Interpreter {
  interpret(alerts: Alert[]): Promise<Interpretation>;
}

export class DeterministicInterpreter implements Interpreter {
  async interpret(alerts: Alert[]): Promise<Interpretation> {
    return { summaries: alerts.map((alert) => ({ type: alert.type, explanation: alert.explanation, action: alert.action })) };
  }
}

export class HttpJsonInterpreter implements Interpreter {
  constructor(private readonly url: string, private readonly apiKey: string | undefined, private readonly model: string, private readonly fetchImpl: typeof fetch = fetch) {}

  async interpret(alerts: Alert[]): Promise<Interpretation> {
    const allowedNumbers = new Set(alerts.flatMap((alert) => [alert.metric.value].filter((value): value is string => value !== null)));
    const payload = alerts.map((alert) => ({ type: alert.type, severity: alert.severity, quality: alert.quality, metric: { key: alert.metric.key, value: alert.metric.value, unit: alert.metric.unit }, evidence_count: alert.evidenceRefs.length }));
    const response = await this.fetchImpl(this.url, {
      method: "POST",
      headers: { "content-type": "application/json", ...(this.apiKey ? { authorization: `Bearer ${this.apiKey}` } : {}) },
      body: JSON.stringify({ model: this.model, instruction: "Explique somente os numeros fornecidos. Nao calcule nem introduza novos numeros. Preserve quality e proponha apenas acoes de baixo risco.", input: payload }),
      signal: AbortSignal.timeout(15_000)
    });
    if (!response.ok) throw new Error(`LLM provider failed with ${response.status}`);
    const parsed = InterpretationSchema.parse(await response.json());
    const numberPattern = /\b\d+(?:[.,]\d+)?\b/g;
    for (const summary of parsed.summaries) {
      const numbers = `${summary.explanation} ${summary.action}`.match(numberPattern) ?? [];
      if (numbers.some((value) => !allowedNumbers.has(value.replace(",", ".")))) throw new Error("LLM output introduced an ungrounded number");
    }
    return parsed;
  }
}
