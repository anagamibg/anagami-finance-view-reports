<?php

namespace App\Http\Controllers\Databackd\Reports;

use App\Helper\Reply;
use App\Http\Controllers\AccountBaseController;
use App\Models\Databackd\FinSavedReport;
use App\Services\Databackd\Reports\ReportBuilder;
use App\Services\Databackd\Reports\ReportFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Saved reports: list, save, open, duplicate, delete. Sending by e-mail is done by `databackd:send-reports`.
 */
class SavedReportController extends AccountBaseController
{

    /** Route that shows each report type. */
    const TARGET_ROUTES = [
        'pivot' => 'databackd.reports.builder.index',
        'pnl' => 'databackd.reports.pnl.index',
        'cashflow' => 'databackd.reports.cashflow.index',
        'counterparties' => 'databackd.reports.counterparties.index',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->middleware(function ($request, $next) {
            // set here: the user's locale is applied by the parent middleware, before this one
            $this->pageTitle = __('databackd-reports-saved.title');
            abort_403(in_array('client', user_roles()));

            return $next($request);
        });
    }

    public function index()
    {
        $this->reports = $this->visibleReports()
            ->with('creator')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();
        $this->manageIds = $this->reports->filter(fn($report) => $this->canManage($report))->pluck('id')->all();
        $this->builderUrl = Route::has('databackd.reports.builder.index') ? route('databackd.reports.builder.index') : null;

        return view('databackd.reports.saved.index', $this->data);
    }

    /**
     * Saves a new report, or - with `id` - updates the name, description, sharing and e-mail schedule of an existing one
     * (S12: the schedule could not be changed after saving; the type and the configuration of a report stay as saved).
     */
    public function store(Request $request)
    {
        if ($request->filled('id')) {
            return $this->updateSettings($request);
        }

        $input = $request->all();
        $input['config'] = self::decodeConfig($input['config'] ?? null);
        $recipients = self::parseRecipients($input['recipients'] ?? []);
        $input['recipients'] = $recipients['valid'];

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', 'in:' . implode(',', FinSavedReport::TYPES)],
            'config' => ['required', 'array', function ($attribute, $value, $fail) {
                // S6/P3: the config is stored as JSON and later written into a query string / read by the scheduler
                if (strlen((string)json_encode($value)) > 20000) {
                    $fail(__('databackd-reports-saved.validation.config_invalid'));
                }
            }],
            'schedule' => ['nullable', 'in:' . implode(',', FinSavedReport::SCHEDULES)],
        ], [
            'name.required' => __('databackd-reports-saved.validation.name_required'),
            'name.max' => __('databackd-reports-saved.validation.name_max'),
            'type.required' => __('databackd-reports-saved.validation.type_invalid'),
            'type.in' => __('databackd-reports-saved.validation.type_invalid'),
            'config.required' => __('databackd-reports-saved.validation.config_invalid'),
            'config.array' => __('databackd-reports-saved.validation.config_invalid'),
            'schedule.in' => __('databackd-reports-saved.validation.schedule_invalid'),
        ]);

        $schedule = $input['schedule'] ?? 'none';
        $validator->after(function ($validator) use ($recipients, $schedule) {
            if ($recipients['invalid']) {
                $validator->errors()->add('recipients', __('databackd-reports-saved.validation.recipients_invalid', ['email' => e($recipients['invalid'][0])])); // shown as HTML (help-block)
            } elseif ($schedule !== 'none' && !$recipients['valid']) {
                $validator->errors()->add('recipients', __('databackd-reports-saved.validation.recipients_required'));
            }
        });

        if ($validator->fails()) {
            return response()->json(Reply::formErrors($validator), 422);
        }

        $report = new FinSavedReport();
        $report->company_id = company()->id;
        $report->created_by = user()->id;
        $report->name = trim($input['name']);
        $report->description = isset($input['description']) && trim($input['description']) !== '' ? trim($input['description']) : null;
        $report->type = $input['type'];
        $report->config = self::normalizeConfig($input['type'], $input['config']);
        $report->is_shared = filter_var($input['is_shared'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $report->schedule = $schedule;
        $report->recipients = $recipients['valid'];
        $report->last_sent_at = null;
        $report->save();

        return Reply::successWithData(__('databackd-reports-saved.messages.saved'), [
            'id' => $report->id,
            'redirectUrl' => route('databackd.reports.saved.index'),
        ]);
    }

    /**
     * Redirects to the report screen with the saved configuration in the query string.
     */
    public function open($id)
    {
        $report = $this->visibleReports()->findOrFail($id);
        $routeName = self::TARGET_ROUTES[$report->type] ?? null;

        if (!$routeName || !Route::has($routeName)) {
            return redirect()->route('databackd.reports.saved.index')->with('error', __('databackd-reports-saved.messages.target_missing'));
        }

        return redirect()->to(route($routeName) . self::queryString($report));
    }

    /** Name, description, sharing and schedule of a report the user may manage. */
    protected function updateSettings(Request $request)
    {
        $report = $this->visibleReports()->findOrFail((int)$request->input('id'));
        abort_403(!$this->canManage($report));

        $input = $request->only(['name', 'description', 'schedule']);
        $recipients = self::parseRecipients($request->input('recipients', []));
        $schedule = $input['schedule'] ?? 'none';

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:2000'],
            'schedule' => ['nullable', 'in:' . implode(',', FinSavedReport::SCHEDULES)],
        ], [
            'name.required' => __('databackd-reports-saved.validation.name_required'),
            'name.max' => __('databackd-reports-saved.validation.name_max'),
            'schedule.in' => __('databackd-reports-saved.validation.schedule_invalid'),
        ]);

        $validator->after(function ($validator) use ($recipients, $schedule) {
            if ($recipients['invalid']) {
                $validator->errors()->add('recipients', __('databackd-reports-saved.validation.recipients_invalid', ['email' => e($recipients['invalid'][0])]));
            } elseif ($schedule !== 'none' && !$recipients['valid']) {
                $validator->errors()->add('recipients', __('databackd-reports-saved.validation.recipients_required'));
            }
        });

        if ($validator->fails()) {
            return response()->json(Reply::formErrors($validator), 422);
        }

        $report->name = trim($input['name']);
        $report->description = isset($input['description']) && trim($input['description']) !== '' ? trim($input['description']) : null;
        $report->is_shared = $request->boolean('is_shared');
        $report->schedule = $schedule;
        $report->recipients = $recipients['valid'];

        $report->save();

        return Reply::successWithData(__('databackd-reports-saved.messages.updated'), ['id' => $report->id]);
    }

    /**
     * Copy of a report into the own list (S12: also of a report shared by a colleague - "copy and adapt").
     * The recipients are copied only from an own report (or by an admin); the copy is private and not scheduled.
     */
    public function duplicate($id)
    {
        $report = $this->visibleReports()->findOrFail($id);

        $copy = new FinSavedReport();
        $copy->company_id = $report->company_id;
        $copy->created_by = user()->id;
        $copy->name = Str::limit($report->name, 191 - 12, '') . ' ' . __('databackd-reports-saved.messages.copy_suffix');
        $copy->description = $report->description;
        $copy->type = $report->type;
        $copy->config = $report->config;
        $copy->is_shared = false;
        $copy->schedule = 'none';
        $copy->recipients = $this->canManage($report) ? $report->recipients : [];
        $copy->last_sent_at = null;
        $copy->save();

        return Reply::successWithData(__('databackd-reports-saved.messages.duplicated'), ['id' => $copy->id]);
    }

    public function destroy($id)
    {
        $report = $this->visibleReports()->findOrFail($id);
        abort_403(!$this->canManage($report));
        $report->delete();

        return Reply::success(__('databackd-reports-saved.messages.deleted'));
    }

    /**
     * Query string (with the leading "?") that reopens a saved report on its screen. Public so that other
     * code (e.g. the e-mail link) can build the same URL.
     */
    public static function queryString(FinSavedReport $report): string
    {
        $config = (array)$report->config;
        $filters = (array)($config['filters'] ?? []);
        $params = array_merge(Arr::except($config, ['filters']), $filters);

        // true/false must reach the GET form as 1/0
        $params = array_map(fn($value) => is_bool($value) ? (int)$value : $value, $params);
        $query = http_build_query($params);

        return $query !== '' ? '?' . $query : '';
    }

    /**
     * Splits a comma / semicolon / white space / new line separated list of e-mails (or an array of them).
     *
     * @return array{valid: string[], invalid: string[]}
     */
    public static function parseRecipients($value): array
    {
        $items = is_array($value) ? $value : preg_split('/[\s,;]+/', (string)$value);
        $valid = [];
        $invalid = [];

        foreach ($items as $item) {
            $item = trim((string)$item);

            if ($item === '') {
                continue;
            }

            if (filter_var($item, FILTER_VALIDATE_EMAIL)) {
                $valid[strtolower($item)] = strtolower($item);
            } else {
                $invalid[] = $item;
            }
        }

        return ['valid' => array_values($valid), 'invalid' => $invalid];
    }

    protected static function decodeConfig($config)
    {
        if (is_string($config)) {
            $decoded = json_decode($config, true);

            return is_array($decoded) ? $decoded : null;
        }

        return is_array($config) ? $config : null;
    }

    /**
     * pivot: normalized builder config + chart + filters; the other types: filters (+ report specific keys).
     */
    protected static function normalizeConfig(string $type, array $config): array
    {
        $filters = ReportFilters::fromArray(is_array($config['filters'] ?? null) ? $config['filters'] : $config)->toQuery();

        if ($type === 'pivot') {
            return ReportBuilder::normalizeConfig($config) + ['filters' => $filters];
        }

        return array_merge(Arr::except($config, ['filters']), ['filters' => $filters]);
    }

    /** Own reports and the reports shared with the team (the company is scoped by the model). */
    protected function visibleReports()
    {
        return FinSavedReport::query()->where(function ($query) {
            $query->where('created_by', user()->id)->orWhere('is_shared', true);
        });
    }

    protected function canManage(FinSavedReport $report): bool
    {
        return (int)$report->created_by === (int)user()->id || in_array('admin', user_roles());
    }

}
