export type TelegramConnectPayload = {
  botToken: string;
  chatId: string;
  enabled: boolean;
};

export type TelegramStatus = {
  enabled: boolean;
  chatId: string | null;
  lastSentAt: string | null;
  sentCount: number;
  failedCount: number;
};

export type CreateOrderPayload = {
  number: string;
  total: string;
  customerName: string;
};

export type CreateOrderResponse = {
  order: {
    id: number;
    shopId: number;
    number: string;
    total: string;
    customerName: string;
    createdAt: string;
  };
  sendStatus: 'sent' | 'failed' | 'skipped';
  skipReason: 'duplicate_order' | 'integration_disabled' | 'already_notified' | null;
};

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status?: number,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

async function http<T>(input: RequestInfo | URL, init?: RequestInit): Promise<T> {
  let res: Response;
  try {
    res = await fetch(input, {
      ...init,
      headers: {
        'Content-Type': 'application/json',
        ...(init?.headers ?? {}),
      },
    });
  } catch {
    throw new ApiError('Сервер недоступен. Проверьте, что backend запущен на localhost:8000.');
  }

  const text = await res.text();
  let data: unknown = null;
  if (text) {
    try {
      data = JSON.parse(text) as unknown;
    } catch {
      data = null;
    }
  }

  if (!res.ok) {
    const message = (data as any)?.error ?? `HTTP ${res.status}`;
    throw new ApiError(message, res.status);
  }

  return data as T;
}

export function connectTelegram(shopId: string, payload: TelegramConnectPayload) {
  return http(`/api/shops/${shopId}/telegram/connect`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export function getTelegramStatus(shopId: string) {
  return http<TelegramStatus>(`/api/shops/${shopId}/telegram/status`);
}

export function createOrder(shopId: string, payload: CreateOrderPayload) {
  return http<CreateOrderResponse>(`/api/shops/${shopId}/orders`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}
