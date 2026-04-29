import { Routes } from '@angular/router';
import { TelegramGrowthPageComponent } from './telegram-growth-page.component';

export const routes: Routes = [
  { path: 'shops/:shopId/growth/telegram', component: TelegramGrowthPageComponent },
  { path: '**', redirectTo: 'shops/1/growth/telegram' },
];
