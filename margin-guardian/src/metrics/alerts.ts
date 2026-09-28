import { Decimal } from "decimal.js";
import type { Metric, Quality } from "./engine.js";

export type Alert = {
  type: string;
  severity: "info" | "low" | "medium" | "high";
  quality: Quality;
  title: string;
  explanation: string;
  action: string;
  metric: Metric;
  evidenceRefs: string[];
};

function metricByKey(metrics: Metric[], key: string): Metric {
  const metric = metrics.find((item) => item.key === key);
  if (!metric) throw new Error(`Missing metric ${key}`);
  return metric;
}

export function buildAlerts(metrics: Metric[]): Alert[] {
  const alerts: Alert[] = [];
  const discount = metricByKey(metrics, "discount_rate");
  if (discount.value && new Decimal(discount.value).gte("0.08")) alerts.push({ type: "discount_pressure", severity: "high", quality: discount.quality, title: "Descontos pressionam a receita", explanation: `A taxa de desconto no periodo foi ${(new Decimal(discount.value).mul(100)).toFixed(1)}%.`, action: "Revise os pedidos e canais com maior desconto antes de mudar a politica comercial.", metric: discount, evidenceRefs: discount.evidenceRefs });
  const cancellation = metricByKey(metrics, "order_cancellation_rate");
  if (cancellation.value && new Decimal(cancellation.value).gte("0.05")) alerts.push({ type: "cancellation_pressure", severity: "medium", quality: cancellation.quality, title: "Cancelamentos acima do limiar inicial", explanation: `A taxa de cancelamento de itens foi ${(new Decimal(cancellation.value).mul(100)).toFixed(1)}%.`, action: "Abra as evidencias e procure concentracao por produto, canal ou horario.", metric: cancellation, evidenceRefs: cancellation.evidenceRefs });
  const payment = metricByKey(metrics, "predicted_payment_cost");
  if (payment.value && new Decimal(payment.value).gt(0)) alerts.push({ type: "payment_cost", severity: "low", quality: payment.quality, title: "Diferenca prevista nos recebimentos", explanation: `A diferenca entre pagamentos e recebimento previsto soma R$ ${new Decimal(payment.value).toFixed(2)}.`, action: "Compare o mix de meios de pagamento e as condicoes contratadas.", metric: payment, evidenceRefs: payment.evidenceRefs });
  const cash = metricByKey(metrics, "cash_pressure");
  if (cash.value && new Decimal(cash.value).gte("0.5")) alerts.push({ type: "cash_pressure", severity: "high", quality: cash.quality, title: "Despesas abertas exigem atencao", explanation: `As despesas abertas equivalem a ${(new Decimal(cash.value).mul(100)).toFixed(1)}% das receitas do periodo.`, action: "Revise vencimentos e as maiores categorias antes de assumir novos compromissos.", metric: cash, evidenceRefs: cash.evidenceRefs });
  const stock = metricByKey(metrics, "stock_below_minimum_count");
  if (stock.value && new Decimal(stock.value).gt(0)) alerts.push({ type: "stock_below_minimum", severity: "medium", quality: stock.quality, title: "Itens abaixo do estoque minimo", explanation: `${stock.value} itens possuem quantidade valida abaixo do minimo cadastrado.`, action: "Priorize a conferencia fisica e a reposicao dos itens listados.", metric: stock, evidenceRefs: stock.evidenceRefs });
  const margin = metricByKey(metrics, "unit_margin");
  alerts.push({ type: "margin_data_gap", severity: "info", quality: margin.quality, title: "Margem por item ainda nao e calculavel", explanation: "A API revisada nao relaciona venda, ficha tecnica e consumo efetivo em um unico contrato.", action: "Use os alertas de receita, caixa e estoque. Cadastre uma fonte de custo auditavel antes de calcular margem unitaria.", metric: margin, evidenceRefs: [] });
  return alerts;
}
