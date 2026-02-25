# Anagami Finance View (AFV) – модул „Справки“

Изнесен модул „Справки“ от платформата **Anagami Finance View (AFV)** – multi-tenant SaaS за управление и анализ на финансови данни на малки и средни предприятия (Laravel 12, Blade, Bootstrap 4, jQuery).

> **Частно / вътрешно ползване.** Хранилището е частно и съдържа извадка от кода на AFV. Не е самостоятелно приложение и не се инсталира със `composer require` – файловете запазват оригиналните си относителни пътища и се копират в хост приложението (виж „Интеграция“). Всички кодови идентификатори са запазени непроменени (namespaces `...\Databackd\...`, папки `routes/databackd/`, lang файлове `databackd-*.php`, helper функции `databackd_*()`, конфигурация `config/databackd.php`).

## Какво включва модулът

Модулът обединява два вида справки:

1. **AFV конструктор на справки и запазени справки** (разработени за AFV) – гъвкава pivot справка върху финансовите записи (`fin_entries`), експорт в Excel / PDF / печат, запазване на конфигурации и автоматично изпращане по имейл като PDF по график.
2. **Базови (upstream) справки на системата** – справките на основното приложение (задачи, часове, плащания, приходи/разходи, отпуски, присъствие, разходи, сделки, продажби), заедно с DataTables, експортите, изгледите и имейл отчетите към тях.

**Извън обхвата** (отделен модул „Финансови справки“): Отчет за приходи и разходи (P&L), паричен поток, контрагенти, законови справки, маржове, коефициенти, консолидация и финансовото табло (`PnlReportController`, `CashflowReportController`, `CounterpartyReportController`, `FinDashboardController`, `MarginReportController`, `StatutoryReport` и свързаните им Exports/views/routes/lang).

## Справки

Всички маршрути са под префикс `/account/` (група `auth`, `multi-company-select`, `email_verified`). „Право“ е проверката в контролера (`user()->permission(...)`), „флаг“ – проверката `user_modules()`; всички базови справки изискват модул `reports` **и** модула на съответния обект. Правата са дефинирани в `app/Models/Module.php` (модул `reports`, виж `_extract/Module-reports-permissions.txt`).

### AFV справки (модулен флаг `databackd_reports`)

Достъпът е през middleware `RequireModule:databackd_reports` (пакетен модул; по каталога `databackd_modules_catalog()` – роли `admin` и `employee`). Ролята `client` получава 403 във всички три контролера. Няма отделни „view_*“ права.

| Справка | Маршрут (име) | Описание според кода |
|---|---|---|
| Конструктор на справки | `GET /account/databackd/reports/builder` (`databackd.reports.builder.index`) | Pivot справка върху потокови записи (`kind = flow`). Редове: сметка, тип сметка, юр. лице, регион, клиентски сегмент, продуктова група, център на разходи, контрагент; колони: период или някое от същите измерения; мярка `sum` / `count` / `avg`; процент от общото, % промяна спрямо предходния период, филтър по тип сметка (приход/разход) и по кодове на сметки, ограничение „топ N“ (останалото в ред „други“), диаграма bar / line / pie, сравнителен период. Цялото състояние е в query string. Общи филтри (`ReportFilters`): бърз период или период по избор, периодичност (ден…година), юр. лица, измерения, клиент, сравнение |
| Запазени справки | `GET /account/databackd/reports/saved` (`databackd.reports.saved.index`), `POST` store, `GET {id}` open, `POST {id}/duplicate`, `DELETE {id}` | Списък на собствените и споделените справки; запазване (име, описание, споделяне, график `none/daily/weekly/monthly`, получатели), отваряне (redirect към екрана на типа с конфигурацията в query string), дублиране, изтриване (само автор или `admin`). Типове: `pivot`, `pnl`, `cashflow`, `counterparties` – последните три се показват от модула „Финансови справки“ |
| Експорт на конструктора | `GET .../export/excel`, `.../export/pdf`, `.../export/print` (`databackd.reports.export.*`) | Excel (`PivotExport`, maatwebsite/excel), PDF (`PivotPdf`, dompdf) и печат за текущата конфигурация на конструктора (същият query string) |
| Автоматично изпращане | команда `php artisan databackd:send-reports` | Генерира PDF на запазените справки, на които им е време (дневно / седмично / месечно по календар), и ги изпраща по имейл (`SavedReportMail`). Опции: `--dry-run`, `--company=`, `--report=`, `--force`. Планирана в `Kernel` ежедневно в 07:00 |

Менюто на AFV: `20-reports-builder` (Конструктор) и `24-reports-saved` (Запазени справки) – включени само при `databackd_module('databackd_reports')`.

### Базови справки

| Справка | Маршрут (име) | Право | Модулен флаг (освен `reports`) | Скрита в менюто? |
|---|---|---|---|---|
| Справка за задачите | `task-report.index`, `task-report.chart`, `employee-wise-task-report`, `consolidated-task-report` | `view_task_report` = all | `tasks` | да (`report_tasks`) |
| Справка за отчетено време | `time-log-report.index`, `time-log-report.chart`, `time-log-report.time`, `time-log-consolidated.report`, `project-wise-timelog.report`, `project-wise-timelog.export` (Excel) | `view_time_log_report` = all | `timelogs` | не |
| Седмичен тайм-шийт (одобрение) и чакащи седмични тайм-шийтове | `time-log-weekly-report.index`, `weekly-pending-time-log-report.report` | `view_time_log_report` = all | `timelogs` | да (`report_weekly_timesheet`); „чакащите“ нямат собствен елемент в менюто – достъпват се с бутон от седмичния екран |
| Финансова справка (плащания) | `finance-report.index`, `finance-report.chart` | `view_finance_report` = all | `payments` | не |
| Приходи срещу разходи | `income-expense-report.index` | `view_income_expense_report` = all | `expenses` | не |
| Справка за отпуски и квота за отпуски | `leave-report.leave_quota` (елементът в менюто), `leave-report.index`, `leave-report.show`, `leave-report.employee-leave-quota`, `leave_quota.export_all_leave_quota` (Excel, метод на `LeavesQuotaController`) | `view_leave_report` = all / added / owned / both | `leaves` | да (`report_leaves`) |
| Справка за присъствие | `attendance-report.index` | `view_attendance_report` = all | `attendance` | да (`report_attendance`) |
| Справка за разходите (и по категории) | `expense-report.index`, `expense-report.chart`, `expense-report.expense_category_report` | `view_expense_report` = all | `expenses` | не |
| Справка за сделки / лийдове | `lead-report.index` (табове), `lead-report.profile`, `lead-report.chart`, `lead-report.lead_contact`, `lead-report.total_contact`, `deal-report.export` (Excel) | `view_lead_report` = all (`lead_contact` – `view_lead`) | `leads` | да (`report_deals`) |
| Справка за продажбите | `sales-report.index` | `view_sales_report` = all | `invoices` | не |

> Забележка: в `LeadReportController::leadContact()` се рендира изгледът `reports.lead.lead-report`, който го няма в изнесените файлове (в оригинала `resources/views/reports/` не съдържа такъв файл) – табът „lead“ на справката не е годен за ползване без него.

### Имейл отчети (базови)

| Отчет | Команда / график | Конфигурация | Файлове |
|---|---|---|---|
| Дневен отчет за отчетеното време (PDF в имейл) | `send-daily-timelog-report`, ежедневно в 02:30 | колони `log_time_for.timelog_report` и `daily_report_roles` | `SendDailyTimelogReport`, `DailyTimeLogReportEvent` / `Listener`, нотификация `DailyTimeLogReport`, изгледи `timelog-report` и `mail/timelog/timelog-report` |
| Месечен отчет за присъствие (PDF в имейл) | `send-monthly-attendance-report`, ежемесечно | колони `attendance_settings.monthly_report` и `monthly_report_roles` | `SendMonthlyAttendanceReport`, `MonthlyAttendanceEvent` / `Listener`, нотификация `MonthlyAttendance`, изгледи `attendance-report` и `mail/attendance/monthly-report` |

## Скрити справки (`config/databackd.php` → `hidden`)

В `config/databackd.php` ключът `hidden.menu` скрива **само елементите на страничното меню** (маршрутите, контролерите и правата остават регистрирани и работят при директно отваряне на адреса). Стойностите се четат през `databackd_hidden($group, $key)`, която винаги връща `false` за суперадмин. В изнесената конфигурация за справките са със стойност `true` (скрити):

| Ключ | Скрива |
|---|---|
| `report_tasks` | Справка за задачите |
| `report_weekly_timesheet` | Седмичен тайм-шийт |
| `report_leaves` | Справка за отпуски |
| `report_attendance` | Справка за присъствие |
| `report_deals` | Справка за сделки |

Допълнително са скрити (`true`) цели секции, от които зависи видимостта на част от справките: `hidden.menu.leaves`, `hidden.menu.attendance`, `hidden.menu.leads` (самите модули в менюто). Видими остават: справка за отчетено време, финансова справка, приходи срещу разходи, справка за разходите, справка за продажбите и двете AFV справки (Конструктор, Запазени справки). Блокът на менюто „Справки“ е в `_extract/menu-reports.blade.txt`; в него групата не се отпечатва, ако няма нито една видима връзка.

## Структура на папките

Оригиналните относителни пътища са запазени (корен = корена на Laravel приложението).

| Категория | Папка | Файлове |
|---|---|---|
| Контролери (10 базови + 3 AFV) | `app/Http/Controllers/`, `.../Databackd/Reports/` | 13 |
| DataTables на справки | `app/DataTables/` | 16 |
| Exports (3 базови + 2 AFV) | `app/Exports/`, `app/Exports/Databackd/Reports/` | 5 |
| Услуги (`ReportBuilder`, `ReportFilters`) | `app/Services/Databackd/Reports/` | 2 |
| Модел `FinSavedReport` | `app/Models/Databackd/` | 1 |
| Mail (`SavedReportMail`) | `app/Mail/Databackd/` | 1 |
| Console команди | `app/Console/Commands/` (+ `Databackd/`) | 3 |
| Events / Listeners / Notifications | `app/Events/`, `app/Listeners/`, `app/Notifications/` | 2 / 2 / 2 |
| Изгледи – базови справки | `resources/views/reports/**` | 30 |
| Изгледи – PDF и имейл на отчетите | `resources/views/{attendance-report,timelog-report}.blade.php`, `resources/views/mail/{attendance,timelog}/` | 4 |
| Изгледи – AFV (builder, saved, export, `_filters`) | `resources/views/databackd/reports/` | 9 |
| Елементи на менюто на AFV (20, 24) | `resources/views/databackd/menu/` | 2 |
| Стилове | `resources/scss/databackd/_reports.scss` | 1 |
| Маршрути (AFV) | `routes/databackd/reports-{builder,saved,export}.php` | 3 |
| Маршрути – референция от `web.php` | `routes/reports.routes.php` | 1 |
| Lang – пълни файлове (`databackd-reports-{builder,saved,export,common}`) | `resources/lang/{eng,bg}/` | 8 |
| Lang – фрагменти от общи файлове | `resources/lang/{eng,bg}/_extract/` | 20 |
| Миграции | `database/migrations/` | 4 |
| Демо данни (seeder) | `database/seeders/Databackd/Demo/42ReportsDemoSeeder.php` | 1 |
| Фрагменти от общи файлове (Kernel, EventServiceProvider, Module, menu) | `_extract/` | 4 |
| Документация | `docs/mvp/` | 2 |
| Служебни | `.gitignore`, `README.md` | 2 |
| **Общо** | | **138** |

Бележки по някои файлове:

- `resources/scss/databackd/_reports.scss` е общ за всички AFV справки – освен стиловете на конструктора и запазените справки съдържа и стиловете на екраните на модула „Финансови справки“ (P&L, паричен поток, контрагенти); не е разделян, за да не се променя оригиналът.
- `resources/views/databackd/reports/_filters.blade.php` и `saved/_save-button.blade.php` са споделени с екраните на модула „Финансови справки“.
- Миграцията `2023_11_21_112750_add_permissions_for_leave_reports` освен правата за справката за отпуски променя и друга таблица (`project_time_logs.memo`) и изтрива настройка за известие – общата миграция е запазена цяла, както е в оригинала.
- Миграциите `2023_02_17…` (`view_lead_report`) и `2023_03_24…` (`view_sales_report`) създават съответните права. Останалите права на модула `reports` идват от базовата схема/`Module.php`.
- `docs/mvp/features/03-reports.md` и `docs/mvp/audit-s12/03-report-builder.md` са оригиналната техническа документация и одит на конструктора (обхващат и екраните на модула „Финансови справки“).

### Фрагменти (`_extract/`)

Общите файлове **не са копирани цели**. Извадени са само релевантните части, като референция (не са валидни самостоятелни файлове):

- `resources/lang/{eng,bg}/_extract/*.php` – ключове, свързани със справките, от `app`, `clan`, `email`, `messages`, `modules`, `permissions`, `ui-core`, `ui-misc`, `databackd-packages`, `databackd-ui`. Извадката е по ключова дума („report“, седмичен тайм-шийт) и не претендира за изчерпателност – част от етикетите на колоните и филтрите на базовите справки са в общите секции на `app.php`/`modules.php`.
- `_extract/Console-Kernel.txt` – регистрация и график на командите; `_extract/EventServiceProvider.txt` – връзките Event → Listener; `_extract/Module-reports-permissions.txt` – правата на модул `reports`; `_extract/menu-reports.blade.txt` – блокът „Справки“ от страничното меню.
- `routes/reports.routes.php` – дефинициите на базовите справки от `routes/web.php`. В `routes/web-settings.php`, `web-public.php` и `api.php` няма маршрути на справки.

## Зависимости (споделени, НЕ са включени)

Модулът разчита на следните части на AFV, които остават в хост приложението:

- **Мултитенантност и основа:** трейт `HasCompany`, `CompanyScope`, `BaseModel`, `AccountBaseController`, `BaseDataTable`, `App\Helper\Reply`, helper функции от `app/Helper/start.php` (`company()`, `user()`, `user_roles()`, `user_modules()`, `abort_403()`, `currency_format()`, `canDataTableExport()` и др.), `app/Helper/databackd*.php` (`databackd_module()`, `databackd_hidden()`, `databackd_modules_catalog()`), `config/databackd.php`, `RequireModule` middleware.
- **Модели на домейна:** `Invoice`, `Payment`, `Expense`, `Task`, `Project`, `ProjectTimeLog`, `Attendance`, `Leave`/`LeaveType`, `Lead`/`Deal`, `User`, `Company`, `Role`, `Module`, `Permission` и др.
- **Финансови данни на AFV:** `FinEntry`, `FinAccount`, `FinEntity`, `FinDimension`, `FinancialDataService`, `PeriodBuckets` (`app/Services/Databackd/Reports/`, споделен с модула „Финансови справки“), `App\Exports\Databackd\Concerns\NeutralizesFormulas`.
- **Модул „Финансови справки“:** `PnlPdf`, `CashflowPdf`, `CounterpartyPdf` (използват се от `SendSavedReports` за запазени справки от тип `pnl` / `cashflow` / `counterparties`) и целевите маршрути `databackd.reports.{pnl,cashflow,counterparties}.index`.
- **Регистрации в общи файлове:** `app/Console/Kernel.php` (команди и график), `app/Providers/EventServiceProvider.php` (Event → Listener), `app/Providers/RouteServiceProvider.php` (`mapDatabackdRoutes()` зарежда `routes/databackd/*.php` с префикс `account` и middleware `web`/`auth`), `app/Helper/databackd_modules.php` (модул `databackd_reports`, меню 20 и 24 → `databackd_reports`), `resources/views/sections/menu.blade.php`, `resources/scss/main.scss` (`@import "databackd/reports"`), общите `resources/lang/*/app.php` и др.
- **Колони от общата схема:** `log_time_for.timelog_report`, `log_time_for.daily_report_roles` (миграция `2023_02_13_045833`, която не е изнесена, защото променя и несвързани таблици), `attendance_settings.monthly_report`, `monthly_report_roles`.
- **Налични в хоста изгледи/компоненти:** `layouts.app`, `sections.datatable_css`, `sections.datatable_js`, `sections.daterange_*`, `components.employee` и др.

## Изисквания

- PHP ≥ 8.2 (`composer.json` на AFV: `"php": "^8.2"`)
- Laravel 12 (`laravel/framework ^12.0`)
- Yajra DataTables (`yajra/laravel-datatables-oracle`, `-buttons`, `-html` ^12.0)
- `maatwebsite/excel` ^3.1.41 (Excel експорти)
- `barryvdh/laravel-dompdf` и `dompdf/dompdf` ^3.0 (PDF на справките и на имейл отчетите)
- Пакет за роли и права `shanmuga/laravel-entrust` (проверките `user()->permission(...)`)
- Имейл транспорт и работещ Laravel scheduler (`* * * * * php artisan schedule:run`) за автоматичното изпращане

## Интеграция

1. Копирайте файловете в хост приложението с запазване на пътищата (`rsync -a --exclude='.git' --exclude='README.md' --exclude='docs' ./ /path/to/app/`).
2. **Маршрути:** AFV маршрутите (`routes/databackd/reports-*.php`) се зареждат от `RouteServiceProvider::mapDatabackdRoutes()`. Маршрутите на базовите справки пренесете от `routes/reports.routes.php` във вътрешността на групата `auth` / `account` в `routes/web.php`.
3. **Регистрации:** добавете командите, `Event → Listener` връзките и графика по `_extract/Console-Kernel.txt` и `_extract/EventServiceProvider.txt`; регистрирайте модула `databackd_reports` в `databackd_modules.php`; добавете `@import "databackd/reports"` в `resources/scss/main.scss`.
4. **Меню:** включете блока от `_extract/menu-reports.blade.txt` и елементите `20-reports-builder` и `24-reports-saved` (групата `reports`).
5. **Lang:** копирайте пълните `databackd-reports-*.php` файлове и слейте ключовете от `resources/lang/*/_extract/` в общите lang файлове.
6. **База данни:** `php artisan migrate` (създава таблицата `fin_saved_reports` – `company_id`, `name`, `description`, `type`, `config` JSON, `is_shared`, `schedule`, `recipients` JSON, `last_sent_at`, `created_by` – и правата `view_lead_report`, `view_sales_report`, `view_leave_report`).
7. **Демо данни (по избор):** `php artisan databackd:demo --only=ReportsDemoSeeder` (запазени справки за демо фирма; сийдърът очаква демо данните на AFV).
8. Компилирайте стиловете (`npm run dev` / `npm run prod`).

Проверка на автоматичното изпращане: `php artisan databackd:send-reports --dry-run`.

## Бележки

- Хранилището е **частно**; съдържанието е предназначено за вътрешно ползване от екипа на Anagami. Не съдържа конфигурации на среда, пароли, ключове или IP адреси. Демо имейл адресите в сийдъра са фиктивни (`@demofinans.bg`).
- Базовите справки произлизат от основното (upstream) приложение, на което AFV е надстройка; към тях в AFV са добавени проверки на модулните флагове и правата (коментарите `databackd:` / `AFV` в кода).
- Файловете са снимка от момента на изнасянето и не се синхронизират автоматично с основния код на AFV.
