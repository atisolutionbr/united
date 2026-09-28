import { z } from "zod";

const Money = z.string().regex(/^-?\d+(?:\.\d+)?$/);
const NullableMoney = Money.nullable().optional();

export const TokenResponseSchema = z.object({
  access_token: z.string().min(1),
  refresh_token: z.string().min(1),
  token_type: z.literal("Bearer"),
  expires_in: z.number().int().positive(),
  scope: z.string(),
  restaurant: z.object({
    id: z.string().regex(/^\d+$/),
    email: z.string().email(),
    fantasy_name: z.string().min(1)
  }).optional()
});
export type TokenResponse = z.infer<typeof TokenResponseSchema>;

const OrderSchema = z.object({
  id: z.number().int(),
  amount: z.number().int(),
  price: Money,
  total_price: Money,
  canceled_at: z.string().datetime().nullable().optional(),
  product: z.object({ id: z.number().int(), name: z.string() }).passthrough()
}).passthrough();

const BasketSchema = z.object({
  id: z.number().int(),
  channel: z.string().optional(),
  canceled_at: z.string().datetime().nullable().optional(),
  orders: z.array(OrderSchema).default([])
}).passthrough();

const BillSchema = z.object({
  id: z.number().int(),
  order_baskets: z.array(BasketSchema).default([])
}).passthrough();

const PaymentSchema = z.object({
  id: z.number().int(),
  payment_value: Money,
  predicted_received_value: Money,
  payment_method: z.object({ id: z.number().int(), name: z.string() }).passthrough()
}).passthrough();

export const TableSessionSchema = z.object({
  id: z.number().int(),
  total_price: Money,
  old_total_price: NullableMoney,
  status: z.string(),
  start_time: z.string().datetime(),
  end_time: z.string().datetime().nullable().optional(),
  completed_at: z.string().datetime().nullable().optional(),
  delivery_canceled_at: z.string().datetime().nullable().optional(),
  status_timings: z.object({ delivery_canceled_at: z.string().datetime().nullable() }).passthrough(),
  payments: z.array(PaymentSchema).default([]),
  bills: z.array(BillSchema).default([])
}).passthrough();
export const TableSessionsSchema = z.array(TableSessionSchema);
export type TableSession = z.infer<typeof TableSessionSchema>;

export const CashFlowResponseSchema = z.object({
  total: z.number().int().nonnegative(),
  count: z.number().int().nonnegative(),
  remaining: z.number().int().nonnegative(),
  limit: z.literal(100),
  offset: z.number().int().nonnegative(),
  start_date: z.string(),
  end_date: z.string(),
  date_type: z.enum(["due_date", "competence_date"]),
  totals: z.object({
    total_earnings: Money,
    total_earnings_paid: Money,
    total_expenses: Money,
    total_expenses_paid: Money
  }),
  cash_flows: z.array(z.object({
    id: z.number().int(),
    entry_type: z.enum(["standalone", "invoice", "invoice_item", "title", "title_entry"]),
    counts_for_result: z.boolean(),
    value: Money,
    is_earning: z.boolean(),
    paid: z.boolean(),
    category: z.string()
  }).passthrough())
});
export type CashFlowResponse = z.infer<typeof CashFlowResponseSchema>;

const StockItemSchema = z.object({
  name: z.string(),
  unidade: z.string(),
  quantidade: z.string().nullable().optional(),
  total_value: z.string().nullable().optional(),
  unitary_price: z.string().nullable().optional(),
  ideal_stock: z.string().nullable().optional(),
  minimum_stock: z.string().nullable().optional()
}).passthrough();

export const InputsResponseSchema = z.object({
  total: z.number().int().nonnegative(),
  count: z.number().int().nonnegative(),
  remaining: z.number().int().nonnegative(),
  limit: z.literal(100),
  offset: z.number().int().nonnegative(),
  inputs: z.array(StockItemSchema)
});

export const IntermediariesResponseSchema = z.object({
  total: z.number().int().nonnegative(),
  count: z.number().int().nonnegative(),
  remaining: z.number().int().nonnegative(),
  limit: z.literal(100),
  offset: z.number().int().nonnegative(),
  intermediaries: z.array(StockItemSchema)
});
export type StockItem = z.infer<typeof StockItemSchema>;
