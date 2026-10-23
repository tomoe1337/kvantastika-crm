import { useCallback, useEffect, useState } from 'react'
import {
  ApiError,
  createDeal,
  getDeal,
  getDeals,
  refreshDeal,
  updateDeal,
  type Deal,
  type DealDetail,
  type DealInput,
  type SyncMode,
} from './api'
import './App.css'

const statuses = ['NEW', 'IN_PROGRESS', 'WON', 'LOST']
const statusNames: Record<string, string> = {
  NEW: 'Новая',
  IN_PROGRESS: 'В работе',
  WON: 'Успешно закрыта',
  LOST: 'Не состоялась',
}
const operationNames: Record<string, string> = {
  queued: 'Запрос принят',
  running: 'Обновление выполняется',
  succeeded: 'Успешно обновлено',
  failed: 'Ошибка обновления',
}
const outcomeNames: Record<string, string> = {
  applied: 'Данные обновлены',
  ignored_stale: 'Ответ устарел — данные сохранены',
  conflict: 'Конфликт версии — локальные данные сохранены',
  unchanged: 'Данные не изменились',
  failed: 'Не удалось обновить данные',
}

const defaultForm: DealInput = {
  external_id: '',
  title: '',
  status: 'NEW',
  amount: 0,
}

function readInitialFilters() {
  const params = new URLSearchParams(window.location.search)
  return {
    q: params.get('q') ?? '',
    status: params.get('status') ?? '',
    page: Math.max(1, Number(params.get('page') ?? '1')),
  }
}

function statusName(status: string) {
  return statusNames[status] ?? status
}

function formatAmount(amount: string | number) {
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 2,
  }).format(Number(amount))
}

function formatDate(value: string | null | undefined) {
  if (!value) return '—'
  return new Intl.DateTimeFormat('ru-RU', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function formFromDeal(deal: Deal): DealInput {
  return {
    external_id: deal.external_id,
    title: deal.title,
    status: deal.status,
    amount: Number(deal.amount),
  }
}

function App() {
  const [initial] = useState(readInitialFilters)
  const [searchInput, setSearchInput] = useState(initial.q)
  const [search, setSearch] = useState(initial.q)
  const [statusFilter, setStatusFilter] = useState(initial.status)
  const [page, setPage] = useState(initial.page)
  const [pageData, setPageData] = useState<{ data: Deal[]; current_page: number; last_page: number; total: number } | null>(null)
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [detail, setDetail] = useState<DealDetail | null>(null)
  const [form, setForm] = useState<DealInput>(defaultForm)
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState(false)
  const [loadingList, setLoadingList] = useState(true)
  const [loadingDetail, setLoadingDetail] = useState(false)
  const [saving, setSaving] = useState(false)
  const [refreshing, setRefreshing] = useState(false)
  const [syncMode, setSyncMode] = useState<SyncMode>('success')
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')

  const loadList = useCallback(async () => {
    setLoadingList(true)
    try {
      const result = await getDeals({ q: search, status: statusFilter, page })
      setPageData(result)
      setSelectedId((current) =>
        current && result.data.some((deal) => deal.id === current)
          ? current
          : result.data[0]?.id ?? null,
      )
      setError('')
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Не удалось загрузить сделки')
    } finally {
      setLoadingList(false)
    }
  }, [page, search, statusFilter])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setSearch(searchInput)
      setPage(1)
    }, 250)
    return () => window.clearTimeout(timer)
  }, [searchInput])

  useEffect(() => {
    const params = new URLSearchParams()
    if (search) params.set('q', search)
    if (statusFilter) params.set('status', statusFilter)
    if (page > 1) params.set('page', String(page))
    const query = params.toString()
    window.history.replaceState(null, '', query ? `?${query}` : window.location.pathname)
  }, [page, search, statusFilter])

  useEffect(() => {
    const timer = window.setTimeout(() => { void loadList() }, 0)
    return () => window.clearTimeout(timer)
  }, [loadList])

  const loadDetail = useCallback(async (id: number) => {
    setLoadingDetail(true)
    try {
      const result = await getDeal(id)
      setDetail(result)
      setForm(formFromDeal(result.deal))
      setError('')
      setPageData((current) => current
        ? { ...current, data: current.data.map((deal) => deal.id === id ? result.deal : deal) }
        : current)
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Не удалось загрузить сделку')
    } finally {
      setLoadingDetail(false)
    }
  }, [])

  useEffect(() => {
    if (creating || selectedId === null) {
      return
    }

    let active = true
    getDeal(selectedId)
      .then((result) => {
        if (!active) return
        setDetail(result)
        setForm(formFromDeal(result.deal))
        setPageData((current) => current
          ? { ...current, data: current.data.map((deal) => deal.id === selectedId ? result.deal : deal) }
          : current)
        setError('')
      })
      .catch((cause: unknown) => {
        if (active) setError(cause instanceof Error ? cause.message : 'Не удалось загрузить сделку')
      })
      .finally(() => {
        if (active) setLoadingDetail(false)
      })

    return () => { active = false }
  }, [creating, selectedId])

  const operationStatus = detail?.deal.latest_sync_operation?.status

  useEffect(() => {
    if (!selectedId || (operationStatus !== 'queued' && operationStatus !== 'running')) return

    const timer = window.setInterval(() => {
      void getDeal(selectedId).then((result) => {
        setDetail(result)
        setPageData((current) => current
          ? { ...current, data: current.data.map((deal) => deal.id === selectedId ? result.deal : deal) }
          : current)
      }).catch(() => undefined)
    }, 1500)

    return () => window.clearInterval(timer)
  }, [operationStatus, selectedId])

  useEffect(() => {
    if (!notice) return
    const timer = window.setTimeout(() => setNotice(''), 3500)
    return () => window.clearTimeout(timer)
  }, [notice])

  const selectedDeal = !creating && selectedId !== null && detail?.deal.id === selectedId
    ? detail.deal
    : null
  const currentOperation = selectedDeal?.latest_sync_operation ?? null

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault()
    setSaving(true)
    setError('')

    try {
      if (creating) {
        const created = await createDeal({
          ...form,
          external_id: form.external_id?.trim() || null,
          title: form.title.trim(),
          status: form.status.trim(),
        })
        setCreating(false)
        setEditing(false)
        setPage(1)
        setSelectedId(created.id)
        setNotice('Сделка создана')
      } else if (selectedId !== null) {
        await updateDeal(selectedId, {
          title: form.title.trim(),
          status: form.status.trim(),
          amount: Number(form.amount),
        })
        setEditing(false)
        setNotice('Изменения сохранены')
        await loadDetail(selectedId)
      }
      await loadList()
    } catch (cause) {
      setError(cause instanceof ApiError && cause.errors
        ? Object.values(cause.errors).flat().join(' ')
        : cause instanceof Error ? cause.message : 'Не удалось сохранить сделку')
    } finally {
      setSaving(false)
    }
  }

  async function handleRefresh() {
    if (!selectedId) return
    setRefreshing(true)
    setError('')

    try {
      const result = await refreshDeal(selectedId, syncMode)
      setDetail((current) => current ? {
        ...current,
        deal: { ...current.deal, latest_sync_operation: result.operation },
      } : current)
      setNotice(result.already_running ? 'Обновление уже выполняется' : 'Запрос на обновление принят')
      await loadList()
      await loadDetail(selectedId)
    } catch (cause) {
      setError(cause instanceof Error ? cause.message : 'Не удалось запустить обновление')
    } finally {
      setRefreshing(false)
    }
  }

  function beginCreate() {
    setCreating(true)
    setEditing(true)
    setForm(defaultForm)
    setError('')
  }

  function cancelCreate() {
    setCreating(false)
    setEditing(false)
    setSelectedId(pageData?.data[0]?.id ?? null)
  }

  const totalLabel = pageData?.total ?? 0

  return (
    <main className="app-shell">
      <header className="topbar">
        <a className="brand" href="/" aria-label="Квантатика CRM — главная">
          <span className="brand-mark">К</span>
          <span><strong>Квантатика</strong><small>CRM сделок</small></span>
        </a>
        <div className="topbar-meta"><span className="live-dot" /> Внутренняя система</div>
      </header>

      <section className="page-heading">
        <div>
          <div className="eyebrow">РАБОЧЕЕ ПРОСТРАНСТВО</div>
          <h1>Сделки</h1>
          <p>Управляйте сделками и синхронизируйте данные с внешней CRM.</p>
        </div>
        <button className="button button-primary" onClick={beginCreate} type="button">
          <span aria-hidden="true">＋</span> Новая сделка
        </button>
      </section>

      {notice && <div className="notice" role="status">{notice}</div>}
      {error && <div className="alert" role="alert">{error}</div>}

      <section className="workspace" aria-label="Работа со сделками">
        <div className="list-card">
          <div className="list-toolbar">
            <div>
              <h2>Все сделки <span className="count-pill">{totalLabel}</span></h2>
              <p>Поиск, фильтры и быстрый просмотр</p>
            </div>
          </div>

          <div className="filters">
            <label className="search-field">
              <span className="search-icon" aria-hidden="true">⌕</span>
              <input
                aria-label="Поиск по сделкам"
                placeholder="Название или внешний ID"
                value={searchInput}
                onChange={(event) => setSearchInput(event.target.value)}
              />
              {searchInput && <button type="button" className="clear-search" aria-label="Очистить поиск" onClick={() => setSearchInput('')}>×</button>}
            </label>
            <label className="status-filter">
              <span className="sr-only">Фильтр по статусу</span>
              <select value={statusFilter} onChange={(event) => { setPage(1); setStatusFilter(event.target.value) }}>
                <option value="">Все статусы</option>
                {statuses.map((status) => <option key={status} value={status}>{statusName(status)}</option>)}
              </select>
            </label>
          </div>

          <div className="table-wrap">
            <table>
              <thead>
                <tr><th>Сделка</th><th>Статус</th><th>Сумма</th><th>Версия</th></tr>
              </thead>
              <tbody>
                {loadingList && !pageData && <tr><td colSpan={4} className="table-message">Загружаем сделки…</td></tr>}
                {!loadingList && pageData?.data.length === 0 && (
                  <tr><td colSpan={4} className="table-message empty-state">
                    <span className="empty-icon">◇</span>
                    <strong>Сделок не найдено</strong>
                    <span>Измените запрос или создайте новую сделку.</span>
                  </td></tr>
                )}
                {pageData?.data.map((deal) => (
                  <tr
                    key={deal.id}
                    className={`deal-row ${selectedId === deal.id && !creating ? 'selected' : ''}`}
                    onClick={() => { setCreating(false); setEditing(false); if (selectedId !== deal.id) setLoadingDetail(true); setSelectedId(deal.id) }}
                    onKeyDown={(event) => {
                      if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault()
                        setCreating(false)
                        setEditing(false)
                        if (selectedId !== deal.id) setLoadingDetail(true)
                        setSelectedId(deal.id)
                      }
                    }}
                    tabIndex={0}
                    aria-selected={selectedId === deal.id && !creating}
                  >
                    <td>
                      <span className="deal-title">{deal.title}</span>
                      <span className="deal-id">{deal.external_id ? `ID ${deal.external_id}` : 'Без внешнего ID'}</span>
                    </td>
                    <td><span className={`status-badge status-${deal.status.toLowerCase()}`}><i />{statusName(deal.status)}</span></td>
                    <td className="amount-cell">{formatAmount(deal.amount)}</td>
                    <td className="version-cell">v{deal.external_version}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <footer className="table-footer">
            <span>{pageData ? `${pageData.total} ${pageData.total === 1 ? 'сделка' : 'сделок'}` : ' '}</span>
            <div className="pagination">
              <button type="button" aria-label="Предыдущая страница" disabled={!pageData || pageData.current_page <= 1} onClick={() => setPage((current) => Math.max(1, current - 1))}>←</button>
              <span>{pageData?.current_page ?? 1} <small>из</small> {pageData?.last_page ?? 1}</span>
              <button type="button" aria-label="Следующая страница" disabled={!pageData || pageData.current_page >= pageData.last_page} onClick={() => setPage((current) => current + 1)}>→</button>
            </div>
          </footer>
        </div>

        <aside className="detail-card" aria-label={creating ? 'Новая сделка' : 'Карточка сделки'}>
          {creating ? (
            <DealForm
              form={form}
              setForm={setForm}
              editing
              creating
              saving={saving}
              onSubmit={handleSubmit}
              onCancel={cancelCreate}
            />
          ) : loadingDetail && selectedId !== null && !selectedDeal ? (
            <div className="detail-loading">Загружаем карточку…</div>
          ) : selectedDeal && detail ? (
            <>
              <div className="detail-heading">
                <div>
                  <div className="eyebrow">КАРТОЧКА СДЕЛКИ</div>
                  <h2>{selectedDeal.title}</h2>
                  <span className="detail-id">{selectedDeal.external_id ? `Внешний ID · ${selectedDeal.external_id}` : 'Локальная сделка'}</span>
                </div>
                {!editing && <button className="icon-button" type="button" onClick={() => setEditing(true)} aria-label="Редактировать сделку">✎</button>}
              </div>

              {editing ? (
                <DealForm
                  form={form}
                  setForm={setForm}
                  editing
                  creating={false}
                  saving={saving}
                  onSubmit={handleSubmit}
                  onCancel={() => { setEditing(false); setForm(formFromDeal(selectedDeal)) }}
                />
              ) : (
                <>
                  <div className="deal-summary">
                    <div className="summary-item"><span>Статус</span><strong><span className={`status-badge status-${selectedDeal.status.toLowerCase()}`}><i />{statusName(selectedDeal.status)}</span></strong></div>
                    <div className="summary-item"><span>Сумма сделки</span><strong className="summary-amount">{formatAmount(selectedDeal.amount)}</strong></div>
                    <div className="summary-item"><span>Внешняя версия</span><strong>v{selectedDeal.external_version}</strong></div>
                    <div className="summary-item"><span>Обновлено извне</span><strong className="summary-date">{formatDate(selectedDeal.external_updated_at)}</strong></div>
                  </div>

                  <section className="sync-section">
                    <div className="section-title-row"><h3>Синхронизация</h3><span className="source-tag">Внешняя CRM</span></div>
                    <div className="sync-controls">
                      <label className="sync-mode-label">
                        <span>Сценарий</span>
                        <select value={syncMode} onChange={(event) => setSyncMode(event.target.value as SyncMode)}>
                          <option value="success">Успешный ответ</option>
                          <option value="rate_limit">Два 429 → успех</option>
                          <option value="server_error">Постоянный 500</option>
                        </select>
                      </label>
                      <button className="button button-secondary" type="button" disabled={!selectedDeal.external_id || refreshing || currentOperation?.status === 'queued' || currentOperation?.status === 'running'} onClick={handleRefresh}>
                        <span aria-hidden="true">↻</span> Обновить
                      </button>
                    </div>
                    {!selectedDeal.external_id && <p className="helper-text">Для синхронизации нужен внешний ID сделки.</p>}
                    {currentOperation && (
                      <div className={`operation-state operation-${currentOperation.status}`} role="status">
                        <span className="operation-indicator" />
                        <div>
                          <strong>{operationNames[currentOperation.status] ?? currentOperation.status}</strong>
                          <span>
                            {currentOperation.outcome
                              ? outcomeNames[currentOperation.outcome] ?? currentOperation.outcome
                              : currentOperation.status === 'failed'
                                ? currentOperation.last_error ?? 'Операция завершилась ошибкой'
                                : `Попытка ${currentOperation.attempts} из ${currentOperation.max_attempts}`}
                          </span>
                        </div>
                      </div>
                    )}
                  </section>

                  <section className="history-section">
                    <div className="section-title-row"><h3>История событий</h3><span className="history-count">{detail.webhook_events.length}</span></div>
                    {detail.webhook_events.length === 0 ? (
                      <p className="history-empty">Webhook-событий пока нет.</p>
                    ) : (
                      <ul className="event-list">
                        {detail.webhook_events.map((event) => (
                          <li key={event.id}>
                            <span className={`event-marker event-${event.status}`} />
                            <div className="event-copy">
                              <strong>{event.event_id}</strong>
                              <span>{event.outcome ? outcomeNames[event.outcome] ?? event.outcome : event.status}</span>
                            </div>
                            <time>{formatDate(event.created_at)}</time>
                          </li>
                        ))}
                      </ul>
                    )}
                  </section>
                </>
              )}
            </>
          ) : (
            <div className="detail-empty">
              <span className="empty-icon">◇</span>
              <strong>Выберите сделку</strong>
              <p>Карточка и история появятся здесь.</p>
            </div>
          )}
        </aside>
      </section>

      <footer className="page-footer"><span>Квантатика CRM</span><span>Синхронизация данных</span></footer>
    </main>
  )
}

interface DealFormProps {
  form: DealInput
  setForm: (form: DealInput) => void
  editing: boolean
  creating: boolean
  saving: boolean
  onSubmit: (event: React.FormEvent<HTMLFormElement>) => void
  onCancel: () => void
}

function DealForm({ form, setForm, editing, creating, saving, onSubmit, onCancel }: DealFormProps) {
  const availableStatuses = form.status && !statuses.includes(form.status)
    ? [form.status, ...statuses]
    : statuses

  return (
    <form className="deal-form" onSubmit={onSubmit}>
      <div className="eyebrow">{creating ? 'НОВАЯ ЗАПИСЬ' : 'РЕДАКТИРОВАНИЕ'}</div>
      <h2>{creating ? 'Новая сделка' : 'Изменить сделку'}</h2>
      {creating && (
        <label className="form-field">
          <span>Внешний ID <small>необязательно</small></span>
          <input value={form.external_id ?? ''} onChange={(event) => setForm({ ...form, external_id: event.target.value })} placeholder="Например, 5678" maxLength={255} />
        </label>
      )}
      <label className="form-field">
        <span>Название</span>
        <input required value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} placeholder="Название сделки" maxLength={255} />
      </label>
      <label className="form-field">
        <span>Статус</span>
        <select required value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value })}>
          {availableStatuses.map((status) => <option value={status} key={status}>{statusName(status)}</option>)}
        </select>
      </label>
      <label className="form-field">
        <span>Сумма, ₽</span>
        <input required type="number" min="0" step="0.01" value={form.amount} onChange={(event) => setForm({ ...form, amount: Number(event.target.value) })} />
      </label>
      <div className="form-actions">
        <button className="button button-primary" type="submit" disabled={saving || !editing}>{saving ? 'Сохраняем…' : creating ? 'Создать сделку' : 'Сохранить'}</button>
        <button className="button button-quiet" type="button" onClick={onCancel} disabled={saving}>Отмена</button>
      </div>
    </form>
  )
}

export default App
