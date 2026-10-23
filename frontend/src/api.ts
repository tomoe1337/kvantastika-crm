export type SyncMode = 'success' | 'rate_limit' | 'server_error'
export type OperationStatus = 'queued' | 'running' | 'succeeded' | 'failed'

export interface SyncOperation {
  id: number
  status: OperationStatus
  mode: SyncMode
  outcome: string | null
  attempts: number
  max_attempts: number
  external_version: number | null
  last_error: string | null
  started_at: string | null
  finished_at: string | null
  created_at: string
}

export interface Deal {
  id: number
  external_id: string | null
  title: string
  status: string
  amount: string
  external_updated_at: string | null
  external_version: number
  created_at: string
  updated_at: string
  latest_sync_operation?: SyncOperation | null
}

export interface WebhookEvent {
  id: number
  event_id: string
  event_time: string
  status: string
  outcome: string | null
  attempts: number
  last_error: string | null
  created_at: string
}

export interface DealPage {
  data: Deal[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface DealDetail {
  deal: Deal
  sync_operations: SyncOperation[]
  webhook_events: WebhookEvent[]
}

export interface DealInput {
  external_id?: string | null
  title: string
  status: string
  amount: number
}

export class ApiError extends Error {
  errors?: Record<string, string[]>

  constructor(message: string, errors?: Record<string, string[]>) {
    super(message)
    this.name = 'ApiError'
    this.errors = errors
  }
}

async function request<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(path, {
    ...init,
    headers: {
      Accept: 'application/json',
      ...(init?.body ? { 'Content-Type': 'application/json' } : {}),
      ...init?.headers,
    },
  })

  const body = await response.json().catch(() => null)

  if (!response.ok) {
    throw new ApiError(
      body?.message ?? `Ошибка запроса (${response.status})`,
      body?.errors,
    )
  }

  return body as T
}

export function getDeals(params: { q: string; status: string; page: number }) {
  const query = new URLSearchParams({ per_page: '15', page: String(params.page) })
  if (params.q.trim()) query.set('q', params.q.trim())
  if (params.status) query.set('status', params.status)
  return request<DealPage>(`/api/deals?${query.toString()}`)
}

export function getDeal(id: number) {
  return request<DealDetail>(`/api/deals/${id}`)
}

export function createDeal(input: DealInput) {
  return request<Deal>('/api/deals', {
    method: 'POST',
    body: JSON.stringify(input),
  })
}

export function updateDeal(id: number, input: Omit<DealInput, 'external_id'>) {
  return request<Deal>(`/api/deals/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(input),
  })
}

export function refreshDeal(id: number, mode: SyncMode) {
  return request<{ operation: SyncOperation; already_running: boolean }>(
    `/api/deals/${id}/refresh`,
    { method: 'POST', body: JSON.stringify({ mode }) },
  )
}
