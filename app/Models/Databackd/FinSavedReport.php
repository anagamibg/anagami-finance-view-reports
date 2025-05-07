<?php

namespace App\Models\Databackd;

use App\Models\BaseModel;
use App\Models\User;
use App\Traits\HasCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Saved report configuration (pivot builder, P&L, cash flow, counterparties).
 *
 * `config` for type "pivot" is the ReportBuilder config plus the filters:
 *   rows, columns, measure, percent_of_total, show_change, chart (bar|line|pie), filters (ReportFilters::toQuery()).
 * For the standard reports `config` holds only `filters` (and optional report specific keys).
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string|null $description
 * @property string $type pivot|pnl|cashflow|counterparties
 * @property array|null $config
 * @property bool $is_shared
 * @property string $schedule none|daily|weekly|monthly
 * @property array|null $recipients e-mail addresses
 * @property \Illuminate\Support\Carbon|null $last_sent_at
 * @property int|null $created_by
 */
class FinSavedReport extends BaseModel
{

    use HasCompany;

    const TYPES = ['pivot', 'pnl', 'cashflow', 'counterparties'];
    const SCHEDULES = ['none', 'daily', 'weekly', 'monthly'];

    protected $table = 'fin_saved_reports';
    protected $guarded = ['id'];
    protected $casts = [
        'config' => 'array',
        'recipients' => 'array',
        'is_shared' => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

}
