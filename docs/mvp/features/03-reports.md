# Техническа документация — Финансови справки, репорти и визуализация (S3)

## Таблица `fin_saved_reports` (миграция `2026_10_27_300000_create_databackd_reports_saved_table.php`)
`id, company_id (FK), name, description, type enum(pivot|pnl|cashflow|counterparties), config json, is_shared bool, schedule enum(none|daily|weekly|monthly), recipients json (имейли), last_sent_at, created_by, timestamps`. Модел `App\Models\Databackd\FinSavedReport` (`HasCompany`). `config` на `pivot` = конфигурация на `ReportBuilder` + `chart` + `filters`; на `pnl|cashflow` = `{filters}`; на `counterparties` = `{filters, side}`. Данните идват от `fin_entries` (S1) — няма други нови таблици.

## Общи класове (`App\Services\Databackd\Reports\`)
- **`ReportFilters`** — състоянието на общата лента с филтри: период (бързи периоди `today…last_year`/`custom`), периодичност (`day|week|month|quarter|year`), юр. лица, 4 разреза, клиент, сравнение (`none|previous|last_year`). `fromRequest/fromArray`, `toQuery` (запазва бързия период относителен), `toServiceFilters` (формат на `FinancialDataService`), `comparePeriod`, `withRange`, `buckets`, `summary`.
- **`PeriodBuckets`** — кофи за периодите + MySQL израз за `GROUP BY` със същите ключове (`2026-10-27`, `2026-W43`, `2026-10`, `2026-Q4`, `2026`), крайните кофи са орязани до диапазона → сборът по дни = сборът по месеци.
- **`ReportBuilder`** — pivot двигател: редове (сметка, тип сметка, юр. лице, регион, сегмент, продуктова група, център на разходи, контрагент) × колони (период или разрез), мярка `sum|count|avg`, `% от общото`, `изменение спрямо предходната колона`, филтри по тип/кодове сметки, `limit` (топ N + „Други“); `compare()` за сравнителния период. Агрегира се в SQL по един `GROUP BY`.
- **`DashboardMetrics`** (табло), **`PnlStatement`** (ОПР по НСС), **`CashflowStatement`** (паричен поток) — всички с `__construct(?int $companyId)`, работят без сесия.

## Routes (имена `databackd.reports.*`, URL `/account/databackd/reports/…`; ролята `client` получава 403)
| Екран | Routes |
|---|---|
| Табло | `dashboard.index` |
| Конструктор | `builder.index` (цялото състояние е в query string) |
| Експорт на конструктора | `export.excel`, `export.pdf`, `export.print` (същият query string като конструктора) |
| Запазени | `saved.index`, `saved.store` (POST), `saved.open` (`{id}`, redirect към екрана на типа), `saved.duplicate` (POST), `saved.destroy` (DELETE) |
| ОПР | `pnl.index`, `pnl.excel`, `pnl.pdf` |
| Паричен поток | `cashflow.index`, `cashflow.excel`, `cashflow.pdf` |
| Контрагенти | `counterparties.index`, `counterparties.show` (`?name=`, `__none__` = без контрагент), `counterparties.excel`, `counterparties.pdf` |

Меню: `05-reports-dashboard`, `20-reports-builder`, `21-reports-pnl`, `22-reports-cashflow`, `23-reports-counterparties`, `24-reports-saved`.

## Как работи
- **Таблото**: ~15 SQL заявки (≈80 ms): 7 KPI с % промяна спрямо сравнителния период (по подразбиране `previous`; марж — в процентни пунктове), 7 диаграми с Chart.js 3.5.1. Салдата (пари 501+503, вземания 411, задължения 401) следват само филтъра за юр. лица.
- **Конструктор**: GET форма, разширява `_filters`; таблица със сортиране, лепкава първа колона, цветове, % и изменение, блок „сравнение“, графика (колони/линия/кръгова) с до 12 серии. Максимум 366 колони/300 реда на екрана (тоталите и графиката включват всичко).
- **Експорт**: Excel (`PivotExport`, maatwebsite/excel, реални числа, замразени панели), PDF (`PivotPdf`, dompdf, DejaVu Sans, A4 пейзаж, блокове по ~12 колони, лого, име на фирмата, `ReportFilters::summary()`), печат (отделен лек layout + `window.print()`). `PivotPdf::render`, `PnlPdf::render`, `CashflowPdf::render`, `CounterpartyPdf::render` не ползват сесия — подава се `companyId`.
- **ОПР**: един `GROUP BY` сметка × кофа; секции по НСС (702–704, 709, 601–605, 609, 721/621), оперативна печалба, печалба преди данъци, **данък 10% ориентировъчен** (в сметкоплана няма сметка за данък; ако има сметки с подтип `tax` — те), нетна печалба; сгъване/разгъване по сметки; % от нетните приходи; сравнение.
- **Паричен поток**: салдо 501+503 (`balanceAt`) начало/край на кофата, постъпления = приходни обороти, плащания = разходни обороти, ред „Други движения“ за съгласуваност на таблицата.
- **Контрагенти**: `counterparty_name` със страни `revenue|expense|all`; оборот, брой документи (`COUNT(DISTINCT document_no)` + записи без номер), дял, sparkline (inline SVG), търсене/сортиране; детайл по месеци.
- **Автоматично изпращане**: `php artisan databackd:send-reports [--dry-run] [--force] [--company=ID] [--report=ID]` — за справките с график ≠ `none`, на които им е време (по календарни дати: daily — не е изпращана днес; weekly — ≥7 дни; monthly — не през текущия месец), генерира PDF според типа и праща писмо (`App\Mail\Databackd\SavedReportMail`) през текущия mail driver (локално `log`), после обновява `last_sent_at`. Езикът е този на компанията.

## Демо данни
`database/seeders/Databackd/Demo/42ReportsDemoSeeder.php` (`php artisan databackd:demo --only=ReportsDemoSeeder`): 9 запазени справки (8 по задание + „Разходи по центрове на разходи“), всяка проверена, че дава резултат.
