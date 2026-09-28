import type { Installation } from "../repositories.js";

function esc(value: unknown): string {
  return String(value ?? "").replace(/[&<>'"]/g, (char) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" })[char]!);
}

function layout(title: string, content: string): string {
  return `<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${esc(title)} - Takeat Margin Guardian</title><style>
  :root{color-scheme:light;--ink:#17231d;--muted:#53645a;--paper:#f6f4ed;--card:#fff;--accent:#08785b;--accent2:#ffe08a;--danger:#9b2c2c;--line:#d9ded8}*{box-sizing:border-box}body{margin:0;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;background:var(--paper);color:var(--ink);line-height:1.55}a{color:#065f4a}a:focus,button:focus{outline:3px solid #1d75bd;outline-offset:3px}.skip{position:absolute;left:-999px}.skip:focus{left:1rem;top:1rem;background:#fff;padding:.6rem;z-index:10}header,main,footer{max-width:1100px;margin:auto;padding:1.1rem 1.4rem}header{display:flex;justify-content:space-between;align-items:center}.brand{font-weight:800}.hero{padding:5rem 0 3rem;max-width:760px}.eyebrow{text-transform:uppercase;letter-spacing:.12em;font-size:.78rem;color:var(--accent);font-weight:800}h1{font-size:clamp(2.2rem,6vw,4.8rem);line-height:1.02;margin:.3rem 0 1.2rem}h2{margin-top:2.2rem}.lead{font-size:1.2rem;color:var(--muted);max-width:62ch}.actions{display:flex;gap:.8rem;flex-wrap:wrap;margin:1.8rem 0}.button,button{display:inline-block;border:0;border-radius:.55rem;background:var(--accent);color:#fff;padding:.78rem 1rem;font:inherit;font-weight:750;text-decoration:none;cursor:pointer}.button.secondary,button.secondary{background:#e5ebe7;color:var(--ink)}.notice{border-left:5px solid var(--accent2);background:#fff7d8;padding:1rem;margin:1rem 0}.status{display:inline-block;padding:.2rem .55rem;border-radius:1rem;background:#e6f4ef}.summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:1rem;margin:1.3rem 0}.metric{background:var(--card);border:1px solid var(--line);border-radius:.7rem;padding:1rem}.metric strong{display:block;font-size:1.45rem}.alerts{display:grid;gap:1rem}.alert{background:var(--card);border:1px solid var(--line);border-left:7px solid #73867b;border-radius:.7rem;padding:1rem 1.1rem}.alert.high{border-left-color:var(--danger)}.alert.medium{border-left-color:#b96900}.alert h3{margin:.1rem 0}.meta{color:var(--muted);font-size:.9rem}.alert form{display:inline}.footer{color:var(--muted);font-size:.9rem;border-top:1px solid var(--line);margin-top:3rem}table{border-collapse:collapse;width:100%}th,td{text-align:left;padding:.65rem;border-bottom:1px solid var(--line)}@media(max-width:600px){header{align-items:flex-start;gap:1rem}.hero{padding-top:2.5rem}}
  </style></head><body><a class="skip" href="#conteudo">Pular para o conteudo</a><header><div class="brand">Takeat Margin Guardian</div><div class="status">MVP somente leitura</div></header><main id="conteudo">${content}</main><footer class="footer">Metricas calculadas pelo backend. A IA, quando habilitada, apenas explica resultados ja calculados.</footer></body></html>`;
}

export function landing(input: { demoMode: boolean; oauthEnabled: boolean; basePath: string }): string {
  const connect = input.oauthEnabled ? `<a class="button" href="${esc(input.basePath)}/auth/takeat/start">Conectar com a Takeat</a>` : "";
  const demo = input.demoMode ? `<a class="button secondary" href="${esc(input.basePath)}/demo">Abrir demonstracao</a>` : "";
  const pending = input.oauthEnabled ? "" : '<div class="notice"><strong>Ambiente de apresentacao:</strong> a conexao OAuth sera ativada quando a Takeat fornecer o Client ID e a URL oficial do portal.</div>';
  return layout("Inicio", `<section class="hero"><div class="eyebrow">Risco de margem e operacao</div><h1>Veja cedo onde receita, caixa e estoque pedem atencao</h1><p class="lead">Conecte seu restaurante com OAuth da Takeat. O Guardian le somente os dados autorizados, calcula metricas auditaveis e prioriza acoes simples.</p><div class="actions">${connect}${demo}</div>${pending}<div class="notice"><strong>Transparencia:</strong> o MVP nao altera produtos, precos ou estoque. Margem por item aparece como dado insuficiente enquanto nao houver uma fonte de custo confiavel.</div></section>`);
}

function formatMetric(metric: Record<string, unknown>): string {
  const value = metric.value == null ? "Dado insuficiente" : String(metric.value);
  if (metric.unit === "BRL" && metric.value != null) return `R$ ${esc(value)}`;
  if (metric.unit === "ratio" && metric.value != null) return `${(Number(value) * 100).toFixed(1)}%`;
  return esc(value);
}

export function dashboard(input: { installation: Pick<Installation, "fantasy_name" | "status">; snapshot: Record<string, unknown> | null; alerts: Array<Record<string, unknown>>; csrf: string; basePath?: string }): string {
  const basePath = input.basePath ?? "";
  const metrics = Array.isArray(input.snapshot?.metrics) ? input.snapshot.metrics as Array<Record<string, unknown>> : [];
  const keys = new Set(["net_session_revenue", "discount_rate", "average_ticket", "stock_below_minimum_count"]);
  const summary = metrics.filter((metric) => keys.has(String(metric.key))).map((metric) => `<div class="metric"><span>${esc(String(metric.key).replaceAll("_", " "))}</span><strong>${formatMetric(metric)}</strong><span class="meta">qualidade: ${esc(metric.quality)}</span></div>`).join("");
  const alerts = input.alerts.map((alert) => `<article class="alert ${esc(alert.severity)}"><div class="meta">${esc(alert.severity)} · ${esc(alert.quality)}</div><h3>${esc(alert.title)}</h3><p>${esc(alert.explanation)}</p><p><strong>Proxima acao:</strong> ${esc(alert.action)}</p><form method="post" action="${esc(basePath)}/feedback"><input type="hidden" name="csrf" value="${esc(input.csrf)}"><input type="hidden" name="alert_id" value="${esc(alert.id)}"><button name="useful" value="true" type="submit">Util</button> <button class="secondary" name="useful" value="false" type="submit">Nao util</button></form></article>`).join("");
  const empty = `<div class="notice">Ainda nao ha uma analise. Solicite a sincronizacao e aguarde o worker.</div>`;
  return layout("Painel", `<p class="eyebrow">${esc(input.installation.fantasy_name)}</p><h1>Painel de riscos</h1><p>Status da conexao: <strong>${esc(input.installation.status)}</strong></p><div class="actions"><form method="post" action="${esc(basePath)}/sync"><input type="hidden" name="csrf" value="${esc(input.csrf)}"><button type="submit">Atualizar analise</button></form><form method="post" action="${esc(basePath)}/disconnect"><input type="hidden" name="csrf" value="${esc(input.csrf)}"><button class="secondary" type="submit">Desconectar</button></form></div>${input.snapshot ? `<section aria-labelledby="resumo"><h2 id="resumo">Resumo</h2><div class="summary">${summary}</div></section><section aria-labelledby="alertas"><h2 id="alertas">Alertas priorizados</h2><div class="alerts">${alerts}</div></section>` : empty}`);
}

export function demoPage(basePath = ""): string {
  const fakeInstallation = { fantasy_name: "Restaurante Demonstracao", status: "active" as const };
  const snapshot = { metrics: [
    { key: "net_session_revenue", value: "18420.50", unit: "BRL", quality: "exact" },
    { key: "discount_rate", value: "0.0910", unit: "ratio", quality: "exact" },
    { key: "average_ticket", value: "67.72", unit: "BRL", quality: "exact" },
    { key: "stock_below_minimum_count", value: "3", unit: "count", quality: "exact" }
  ] };
  const alerts = [
    { id: "demo-1", severity: "high", quality: "exact", title: "Descontos pressionam a receita", explanation: "A taxa de desconto no periodo foi 9,1%.", action: "Revise os pedidos e canais com maior desconto antes de mudar a politica comercial." },
    { id: "demo-2", severity: "medium", quality: "exact", title: "Itens abaixo do estoque minimo", explanation: "3 itens possuem quantidade valida abaixo do minimo cadastrado.", action: "Priorize a conferencia fisica e a reposicao dos itens listados." },
    { id: "demo-3", severity: "info", quality: "insufficient", title: "Margem por item ainda nao e calculavel", explanation: "A API revisada nao relaciona venda, ficha tecnica e consumo efetivo em um unico contrato.", action: "Cadastre uma fonte de custo auditavel antes de calcular margem unitaria." }
  ];
  return dashboard({ installation: fakeInstallation as Installation, snapshot, alerts, csrf: "demo", basePath })
    .replaceAll(`action="${basePath}/feedback"`, `action="${basePath}/demo"`)
    .replaceAll(`action="${basePath}/sync"`, `action="${basePath}/demo"`)
    .replaceAll(`action="${basePath}/disconnect"`, `action="${basePath}/demo"`);
}

export function errorPage(title: string, message: string, basePath = ""): string {
  return layout(title, `<h1>${esc(title)}</h1><div class="notice">${esc(message)}</div><p><a href="${esc(basePath)}/">Voltar ao inicio</a></p>`);
}
