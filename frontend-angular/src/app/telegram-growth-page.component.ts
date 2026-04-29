import { CommonModule } from '@angular/common';
import { Component, OnDestroy, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import {
  ApiError,
  createOrder,
  type CreateOrderResponse,
  connectTelegram,
  getTelegramStatus,
  type TelegramStatus,
} from './api';

type Toast = { type: 'error' | 'ok' | 'warn'; text: string };

@Component({
  selector: 'app-telegram-growth-page',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './telegram-growth-page.component.html',
  styleUrl: './telegram-growth-page.component.css',
})
export class TelegramGrowthPageComponent implements OnInit, OnDestroy {
  constructor(private readonly route: ActivatedRoute) {}

  resolvedShopId = '1';
  title = `Telegram интеграция (shopId=${this.resolvedShopId})`;

  botToken = '';
  chatId = '';
  enabled = true;

  status: TelegramStatus | null = null;
  loadingStatus = false;
  statusUpdatedAt: string | null = null;
  saving = false;
  creatingOrder = false;
  toast: Toast | null = null;
  lastSendStatus: CreateOrderResponse['sendStatus'] | null = null;

  orderNumber = '';
  orderTotal = '';
  orderCustomerName = '';

  private toastTimerId: ReturnType<typeof setTimeout> | null = null;

  ngOnInit(): void {
    const routeShopId = this.route.snapshot.paramMap.get('shopId');
    this.resolvedShopId = routeShopId ?? '1';
    this.title = `Telegram интеграция (shopId=${this.resolvedShopId})`;
    void this.refreshStatus();
  }

  ngOnDestroy(): void {
    if (this.toastTimerId) {
      clearTimeout(this.toastTimerId);
      this.toastTimerId = null;
    }
  }

  async refreshStatus(): Promise<void> {
    this.loadingStatus = true;
    try {
      const s = await getTelegramStatus(this.resolvedShopId);
      this.status = s;
      this.statusUpdatedAt = new Date().toLocaleTimeString();
    } catch (e) {
      this.showToast('error', this.humanizeError(e, 'Не удалось загрузить статус'));
    } finally {
      this.loadingStatus = false;
    }
  }

  async onSave(): Promise<void> {
    if (this.botToken.trim() === '' || this.chatId.trim() === '') {
      this.showToast('error', 'Введите botToken и chatId перед сохранением.');
      return;
    }

    this.saving = true;
    this.toast = null;
    try {
      await connectTelegram(this.resolvedShopId, {
        botToken: this.botToken.trim(),
        chatId: this.chatId.trim(),
        enabled: this.enabled,
      });
      this.showToast('ok', 'Интеграция сохранена.');
      await this.refreshStatus();
    } catch (e) {
      this.showToast('error', this.humanizeError(e, 'Не удалось сохранить интеграцию'));
    } finally {
      this.saving = false;
    }
  }

  onOrderTotalInput(value: string): void {
    const next = value.replace(',', '.');
    if (/^\d*\.?\d{0,2}$/.test(next)) {
      this.orderTotal = next;
    }
  }

  onOrderTotalKeydown(event: KeyboardEvent): void {
    const allowedControlKeys = new Set([
      'Backspace',
      'Delete',
      'ArrowLeft',
      'ArrowRight',
      'Tab',
      'Home',
      'End',
      'Enter',
    ]);

    if (event.ctrlKey || event.metaKey || allowedControlKeys.has(event.key)) {
      return;
    }

    if (!/[\d.,]/.test(event.key)) {
      event.preventDefault();
    }
  }

  onOrderTotalPaste(event: ClipboardEvent): void {
    const text = event.clipboardData?.getData('text') ?? '';
    const normalized = text.replace(',', '.').trim();
    if (!/^\d*\.?\d{0,2}$/.test(normalized)) {
      event.preventDefault();
    }
  }

  async onCreateOrder(): Promise<void> {
    if (
      this.orderNumber.trim() === '' ||
      this.orderTotal.trim() === '' ||
      this.orderCustomerName.trim() === ''
    ) {
      this.showToast('error', 'Заполните number, total и customerName.');
      return;
    }

    const normalizedTotal = this.normalizeTotalInput(this.orderTotal);
    if (!this.isValidTotal(normalizedTotal)) {
      this.showToast('error', 'Поле total должно быть числом (например, 1500 или 1500.50).');
      return;
    }

    this.creatingOrder = true;
    this.toast = null;
    this.lastSendStatus = null;

    try {
      const response = await createOrder(this.resolvedShopId, {
        number: this.orderNumber.trim(),
        total: normalizedTotal,
        customerName: this.orderCustomerName.trim(),
      });

      this.lastSendStatus = response.sendStatus;
      if (response.sendStatus === 'sent') {
        this.showToast('ok', `Заказ ${response.order.number} создан, сообщение отправлено в Telegram.`);
        await this.refreshStatus();
        return;
      }

      if (response.sendStatus === 'skipped') {
        this.showToast('warn', `Отправка пропущена: ${this.getSkippedReasonText(response.skipReason)}.`);
        await this.refreshStatus();
        return;
      }

      this.showToast('error', `Заказ ${response.order.number} создан, но отправка в Telegram завершилась ошибкой.`);
      await this.refreshStatus();
    } catch (e) {
      this.showToast('error', this.humanizeError(e, 'Не удалось создать заказ'));
    } finally {
      this.creatingOrder = false;
    }
  }

  private showToast(type: Toast['type'], text: string): void {
    this.toast = { type, text };
    if (this.toastTimerId) {
      clearTimeout(this.toastTimerId);
    }
    this.toastTimerId = setTimeout(() => {
      this.toast = null;
      this.toastTimerId = null;
    }, 7000);
  }

  private humanizeError(error: unknown, fallback: string): string {
    if (error instanceof ApiError) {
      if (error.status === 422) {
        return `Ошибка валидации: ${error.message}`;
      }
      return error.message;
    }
    if (error instanceof Error) {
      return error.message;
    }
    return fallback;
  }

  private normalizeTotalInput(value: string): string {
    return value.replace(',', '.').trim();
  }

  private getSkippedReasonText(skipReason: CreateOrderResponse['skipReason']): string {
    if (skipReason === 'duplicate_order') {
      return 'заказ с таким номером уже существует';
    }

    if (skipReason === 'integration_disabled') {
      return 'интеграция Telegram отключена';
    }

    if (skipReason === 'already_notified') {
      return 'уведомление по этому заказу уже отправлялось';
    }

    return 'отправка пропущена';
  }

  private isValidTotal(value: string): boolean {
    return /^\d+(\.\d{1,2})?$/.test(value);
  }
}
