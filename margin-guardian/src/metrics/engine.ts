import { Decimal } from "decimal.js";
import type { CashFlowResponse, StockItem, TableSession } from "../takeat/schemas.js";

export type Quality = "exact" | "mapped" | "proxy" | "insufficient";

export type Metric = {
  key: string;
  value: string | null;
  unit: "BRL" | "ratio" | "count";
  quality: Quality;
  ruleVersion: string;
  evidenceRefs: string[];
  coverage?: string;
};

export type MetricsResult = { metrics: Metric[]; evidence: Record<string, unknown> };

function decimalOrNull(value: string | null | undefined): Decimal | null {
  if (!value || value === "NaN") return null;
  try { return new Decimal(value); } catch { return null; }
}

function fixed(value: Decimal, places = 2): string {
  return value.toDecimalPlaces(places, Decimal.ROUND_HALF_UP).toFixed(places);
}

export function calculateMetrics(input: {
  tableSessions: TableSession[];
  cashFlows: CashFlowResponse;
  inputs: StockItem[];
  intermediaries: StockItem[];
}): MetricsResult {
  const completed = input.tableSessions.filter((session) => session.status === "completed" && !session.delivery_canceled_at && !session.status_timings.delivery_canceled_at);
  let netRevenue = new Decimal(0);
  let beforeDiscount = new Decimal(0);
  let paymentCost = new Decimal(0);
  let orders = 0;
  let cancelledOrders = 0;
  const sessionEvidence: string[] = [];
  const paymentEvidence: string[] = [];

  for (const session of completed) {
    const net = decimalOrNull(session.total_price);
    if (net) netRevenue = netRevenue.plus(net);
    const old = decimalOrNull(session.old_total_price) ?? net;
    if (old) beforeDiscount = beforeDiscount.plus(old);
    sessionEvidence.push(`table_session:${session.id}`);
    for (const payment of session.payments) {
      const paid = decimalOrNull(payment.payment_value);
      const predicted = decimalOrNull(payment.predicted_received_value);
      if (paid && predicted) {
        paymentCost = paymentCost.plus(paid.minus(predicted));
        paymentEvidence.push(`payment:${payment.id}`);
      }
    }
    for (const bill of session.bills) for (const basket of bill.order_baskets) for (const order of basket.orders) {
      orders += 1;
      if (order.canceled_at || basket.canceled_at) cancelledOrders += 1;
    }
  }

  const discountRate = beforeDiscount.gt(0) ? beforeDiscount.minus(netRevenue).div(beforeDiscount) : null;
  const averageTicket = completed.length ? netRevenue.div(completed.length) : null;
  const cancellationRate = orders ? new Decimal(cancelledOrders).div(orders) : null;
  const totalEarnings = decimalOrNull(input.cashFlows.totals.total_earnings) ?? new Decimal(0);
  const totalExpenses = decimalOrNull(input.cashFlows.totals.total_expenses) ?? new Decimal(0);
  const totalExpensesPaid = decimalOrNull(input.cashFlows.totals.total_expenses_paid) ?? new Decimal(0);
  const openExpenses = Decimal.max(0, totalExpenses.minus(totalExpensesPaid));
  const cashPressure = totalEarnings.gt(0) ? openExpenses.div(totalEarnings) : null;

  const allStock = [
    ...input.inputs.map((item, index) => ({ item, ref: `input:${index}:${item.name.normalize("NFKC")}` })),
    ...input.intermediaries.map((item, index) => ({ item, ref: `intermediary:${index}:${item.name.normalize("NFKC")}` }))
  ];
  const belowMinimum: string[] = [];
  let stockValue = new Decimal(0);
  let validValues = 0;
  for (const { item, ref } of allStock) {
    const quantity = decimalOrNull(item.quantidade);
    const minimum = decimalOrNull(item.minimum_stock);
    const totalValue = decimalOrNull(item.total_value);
    if (quantity && minimum && quantity.lt(minimum)) belowMinimum.push(ref);
    if (totalValue) { stockValue = stockValue.plus(totalValue); validValues += 1; }
  }

  const metrics: Metric[] = [
    { key: "net_session_revenue", value: fixed(netRevenue), unit: "BRL", quality: "exact", ruleVersion: "net-session-revenue/v1", evidenceRefs: sessionEvidence },
    { key: "discount_rate", value: discountRate ? fixed(discountRate, 4) : null, unit: "ratio", quality: discountRate ? "exact" : "insufficient", ruleVersion: "discount-rate/v1", evidenceRefs: sessionEvidence },
    { key: "average_ticket", value: averageTicket ? fixed(averageTicket) : null, unit: "BRL", quality: averageTicket ? "exact" : "insufficient", ruleVersion: "average-ticket/v1", evidenceRefs: sessionEvidence },
    { key: "order_cancellation_rate", value: cancellationRate ? fixed(cancellationRate, 4) : null, unit: "ratio", quality: cancellationRate ? "exact" : "insufficient", ruleVersion: "order-cancellation-rate/v1", evidenceRefs: sessionEvidence },
    { key: "predicted_payment_cost", value: fixed(paymentCost), unit: "BRL", quality: "exact", ruleVersion: "predicted-payment-cost/v1", evidenceRefs: paymentEvidence },
    { key: "cash_pressure", value: cashPressure ? fixed(cashPressure, 4) : null, unit: "ratio", quality: cashPressure ? "exact" : "insufficient", ruleVersion: "cash-pressure/v1", evidenceRefs: input.cashFlows.cash_flows.map((entry) => `cash_flow:${entry.id}`) },
    { key: "stock_below_minimum_count", value: String(belowMinimum.length), unit: "count", quality: "exact", ruleVersion: "stock-below-minimum/v1", evidenceRefs: belowMinimum },
    { key: "current_stock_value", value: fixed(stockValue), unit: "BRL", quality: validValues === allStock.length ? "exact" : validValues ? "mapped" : "insufficient", ruleVersion: "current-stock-value/v1", evidenceRefs: allStock.map(({ ref }) => ref), coverage: allStock.length ? `${validValues}/${allStock.length}` : "0/0" },
    { key: "unit_margin", value: null, unit: "BRL", quality: "insufficient", ruleVersion: "unit-margin/v1", evidenceRefs: [] }
  ];
  return { metrics, evidence: { completedSessionCount: completed.length, orderCount: orders, cancelledOrderCount: cancelledOrders, stockItemCount: allStock.length } };
}
