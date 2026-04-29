import { useEffect, useMemo, useState } from 'react'
import { useParams } from 'react-router-dom'
import {
  ApiError,
  connectTelegram,
  createOrder,
  getTelegramStatus,
  type CreateOrderResponse,
  type TelegramStatus,
} from '../api'
import './telegramGrowth.css'

export function TelegramGrowthPage() {
  const { shopId } = useParams()
  const resolvedShopId = shopId ?? '1'

  const [botToken, setBotToken] = useState('')
  const [chatId, setChatId] = useState('')
  const [enabled, setEnabled] = useState(true)

  const [status, setStatus] = useState<TelegramStatus | null>(null)
  const [loadingStatus, setLoadingStatus] = useState(false)
  const [statusUpdatedAt, setStatusUpdatedAt] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)
  const [creatingOrder, setCreatingOrder] = useState(false)
  const [toast, setToast] = useState<{ type: 'error' | 'ok' | 'warn'; text: string } | null>(null)
  const [lastSendStatus, setLastSendStatus] = useState<CreateOrderResponse['sendStatus'] | null>(null)

  const [orderNumber, setOrderNumber] = useState('')
  const [orderTotal, setOrderTotal] = useState('')
  const [orderCustomerName, setOrderCustomerName] = useState('')

  const title = useMemo(
    () => `Telegram интеграция (shopId=${resolvedShopId})`,
    [resolvedShopId],
  )

  function humanizeError(error: unknown, fallback: string): string {
    if (error instanceof ApiError) {
      if (error.status === 422) {
        return `Ошибка валидации: ${error.message}`
      }
      return error.message
    }
    if (error instanceof Error) {
      return error.message
    }
    return fallback
  }

  function normalizeTotalInput(value: string): string {
    return value.replace(',', '.').trim()
  }

  function isValidTotal(value: string): boolean {
    return /^\d+(\.\d{1,2})?$/.test(value)
  }

  async function refreshStatus() {
    setLoadingStatus(true)
    try {
      const s = await getTelegramStatus(resolvedShopId)
      setStatus(s)
      setStatusUpdatedAt(new Date().toLocaleTimeString())
    } catch (e) {
      setToast({ type: 'error', text: humanizeError(e, 'Не удалось загрузить статус') })
    } finally {
      setLoadingStatus(false)
    }
  }

  useEffect(() => {
    refreshStatus()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [resolvedShopId])

  useEffect(() => {
    if (!toast) {
      return
    }

    const timer = window.setTimeout(() => {
      setToast(null)
    }, 7000)

    return () => {
      window.clearTimeout(timer)
    }
  }, [toast])

  async function onSave() {
    if (botToken.trim() === '' || chatId.trim() === '') {
      setToast({ type: 'error', text: 'Введите botToken и chatId перед сохранением.' })
      return
    }

    setSaving(true)
    setToast(null)
    try {
      await connectTelegram(resolvedShopId, {
        botToken: botToken.trim(),
        chatId: chatId.trim(),
        enabled,
      })
      setToast({ type: 'ok', text: 'Интеграция сохранена.' })
      await refreshStatus()
    } catch (e) {
      setToast({ type: 'error', text: humanizeError(e, 'Не удалось сохранить интеграцию') })
    } finally {
      setSaving(false)
    }
  }

  async function onCreateOrder() {
    if (orderNumber.trim() === '' || orderTotal.trim() === '' || orderCustomerName.trim() === '') {
      setToast({ type: 'error', text: 'Заполните number, total и customerName.' })
      return
    }

    const normalizedTotal = normalizeTotalInput(orderTotal)
    if (!isValidTotal(normalizedTotal)) {
      setToast({ type: 'error', text: 'Поле total должно быть числом (например, 1500 или 1500.50).' })
      return
    }

    setCreatingOrder(true)
    setToast(null)
    setLastSendStatus(null)

    try {
      const response = await createOrder(resolvedShopId, {
        number: orderNumber.trim(),
        total: normalizedTotal,
        customerName: orderCustomerName.trim(),
      })
      setLastSendStatus(response.sendStatus)
      if (response.sendStatus === 'sent') {
        setToast({ type: 'ok', text: `Заказ ${response.order.number} создан, сообщение отправлено в Telegram.` })
      } else if (response.sendStatus === 'skipped') {
        const skippedReason =
          response.skipReason === 'duplicate_order'
            ? 'заказ с таким номером уже существует'
            : response.skipReason === 'integration_disabled'
              ? 'интеграция Telegram отключена'
              : response.skipReason === 'already_notified'
                ? 'уведомление по этому заказу уже отправлялось'
                : 'отправка пропущена'
        setToast({
          type: 'warn',
          text: `Отправка пропущена: ${skippedReason}.`,
        })
      } else {
        setToast({
          type: 'error',
          text: `Заказ ${response.order.number} создан, но отправка в Telegram завершилась ошибкой.`,
        })
      }
      await refreshStatus()
    } catch (e) {
      setToast({ type: 'error', text: humanizeError(e, 'Не удалось создать заказ') })
    } finally {
      setCreatingOrder(false)
    }
  }

  return (
    <div className="tg-page">
      <header className="tg-header">
        <div className="tg-header-left">
          <h1>{title}</h1>
          {statusUpdatedAt && <div className="tg-updated-at">Статус обновлен: {statusUpdatedAt}</div>}
        </div>
        <div className="tg-actions">
          <button type="button" className="tg-button" onClick={refreshStatus} disabled={loadingStatus}>
            Обновить статус
          </button>
        </div>
      </header>

      <div className="tg-grid">
        <section className="tg-card">
          <h2>Подключение</h2>

          <label className="tg-field">
            <span>botToken</span>
            <input
              value={botToken}
              onChange={(e) => setBotToken(e.target.value)}
              placeholder="123456:ABC-DEF..."
              autoComplete="off"
            />
          </label>

          <label className="tg-field">
            <span>chatId</span>
            <input
              value={chatId}
              onChange={(e) => setChatId(e.target.value)}
              placeholder="987654321"
              autoComplete="off"
            />
          </label>

          <label className="tg-toggle">
            <input
              className="tg-toggle-input"
              type="checkbox"
              checked={enabled}
              onChange={(e) => setEnabled(e.target.checked)}
            />
            <span className="tg-toggle-label">enabled</span>
          </label>

          <button type="button" className="tg-button tg-primary" onClick={onSave} disabled={saving}>
            {saving ? 'Сохранение…' : 'Сохранить'}
          </button>

          <div className="tg-hint">
            <strong>Как узнать chatId:</strong>
            <div>
              Самый простой путь для MVP — написать боту и получить chat id через один из готовых
              ботов (например, @userinfobot) или через любой “getUpdates” инструмент. Затем вставьте
              chatId сюда.
            </div>
          </div>
        </section>

        <section className="tg-card">
          <h2>Статус</h2>

          {loadingStatus && <div className="tg-muted">Загрузка…</div>}

          {!loadingStatus && status && (
            <dl className="tg-dl">
              <div>
                <dt>enabled</dt>
                <dd>{status.enabled ? 'true' : 'false'}</dd>
              </div>
              <div>
                <dt>chatId</dt>
                <dd>{status.chatId ?? '—'}</dd>
              </div>
              <div>
                <dt>lastSentAt</dt>
                <dd>{status.lastSentAt ?? '—'}</dd>
              </div>
              <div>
                <dt>sentCount (7d)</dt>
                <dd>{status.sentCount}</dd>
              </div>
              <div>
                <dt>failedCount (7d)</dt>
                <dd>{status.failedCount}</dd>
              </div>
            </dl>
          )}

          {!loadingStatus && !status && <div className="tg-muted">Нет данных</div>}
        </section>
      </div>

      <section className="tg-card tg-order-card">
        <h2>Тестовый заказ</h2>

        <div className="tg-order-grid">
          <label className="tg-field">
            <span>number</span>
            <input
              value={orderNumber}
              onChange={(e) => setOrderNumber(e.target.value)}
              placeholder="A-1001"
              autoComplete="off"
            />
          </label>

          <label className="tg-field">
            <span>total</span>
            <input
              inputMode="decimal"
              value={orderTotal}
              onChange={(e) => {
                const next = e.target.value.replace(',', '.')
                if (/^\d*\.?\d{0,2}$/.test(next)) {
                  setOrderTotal(next)
                }
              }}
              placeholder="1500"
              autoComplete="off"
            />
          </label>

          <label className="tg-field">
            <span>customerName</span>
            <input
              value={orderCustomerName}
              onChange={(e) => setOrderCustomerName(e.target.value)}
              placeholder="Иван"
              autoComplete="off"
            />
          </label>
        </div>

        <div className="tg-order-actions">
          <button
            type="button"
            className="tg-button tg-primary"
            onClick={onCreateOrder}
            disabled={creatingOrder}
          >
            {creatingOrder ? 'Создание…' : 'Создать тестовый заказ'}
          </button>
          {lastSendStatus && (
            <span className={`tg-send-status tg-send-status--${lastSendStatus}`}>sendStatus: {lastSendStatus}</span>
          )}
        </div>
      </section>

      {toast && <div className={`tg-toast tg-toast--${toast.type}`}>{toast.text}</div>}
    </div>
  )
}

