<?php

return [
    'menu' => 'Flexible Reports',
    'title' => 'Flexible Reports',
    'subtitle' => 'Build your own report: choose rows, columns and a measure — the table and the chart follow the filters.',

    'constructor' => 'Report builder',
    'rows' => 'Rows',
    'columns' => 'Columns',
    'measure' => 'Value',
    'account_type' => 'Revenue / expenses',
    'account_type_all' => 'Revenue and expenses',
    'accounts' => 'Accounts',
    'accounts_all' => 'All accounts',
    'limit' => 'Number of rows',
    'limit_all' => 'All rows',
    'limit_top' => 'Top :n + Other',
    'percent_of_total' => 'Show % of total',
    'show_change' => 'Show change vs the previous period',
    'chart_type' => 'Chart',
    'chart_types' => [
        'bar' => 'Columns',
        'line' => 'Line',
        'pie' => 'Pie',
    ],
    'columns_by_period' => 'Periods (by Periodicity)',
    'apply' => 'Update report',
    'defaults' => 'Defaults',

    'units' => [
        'sum' => '€',
        'count' => 'pcs',
        'avg' => '€',
    ],

    'toolbar' => [
        'saved' => 'Saved reports',
        'excel' => 'Excel',
        'pdf' => 'PDF',
        'print' => 'Print',
    ],

    'result' => [
        'title' => 'Result',
        'subtitle' => ':rows by :columns · :measure',
        'rows_count' => 'Rows: :n',
        'sort_hint' => 'Click a column header to sort.',
        'total_column' => 'Total',
        'share' => 'Share',
        'change_hint' => 'The arrow shows the change against the previous column.',
    ],

    'compare' => [
        'title' => 'Comparison: :current vs :previous',
        'row' => 'Row',
        'current' => 'Current period',
        'previous' => 'Compared period',
        'change' => 'Change',
        'change_pct' => 'Change %',
    ],

    'chart' => [
        'title' => 'Chart',
        'top_note' => 'The chart shows the top :n rows by total value.',
        'pie_note' => 'The pie chart shows the share of every row in the total.',
    ],

    'notices' => [
        'empty' => 'No data for the selected rows, columns and filters. Change the period or the filters.',
        'many_columns' => 'The report has :n columns (periods). The table scrolls horizontally; pick a coarser periodicity (weekly, monthly) for a clearer result.',
        'columns_truncated' => 'The table shows the first :shown of :n columns; the Total column and the chart include all periods.',
        'rows_truncated' => 'The table shows the first :shown of :n rows. Use "Number of rows" to limit the result to the largest ones.',
        'same_dimension' => 'Rows and columns use the same dimension — data will only be on the diagonal. Choose different dimensions.',
        'expense_no_dimension' => 'Expenses have no ":dimension" breakdown — their value is in "No breakdown". Choose revenue or another dimension.',
        'revenue_no_dimension' => 'Revenue has no ":dimension" breakdown — its value is in "No breakdown". Choose expenses or another dimension.',
        'change_needs_period' => 'The change vs the previous period is shown only when the columns are periods.',
        'avg_percent' => 'With "Average amount" the percentage is relative to the overall average of the report.',
        'mixed_types' => 'Revenue and expenses are added up into one amount (turnover). For a result choose the rows "Account type" or "Account", or open the P&L.',
        'period_too_long' => 'The period is too long for this periodicity: the table and the chart show the first :n periods (up to :date), the "Total" column the whole period. Choose a coarser periodicity.',
    ],
];
