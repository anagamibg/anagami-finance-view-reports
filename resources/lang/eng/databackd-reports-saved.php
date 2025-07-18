<?php

return [
    'menu' => 'Saved Reports',
    'title' => 'Saved Reports',
    'subtitle' => 'Your own and team-shared reports. Open them with one click or receive them automatically by e-mail.',

    'types' => [
        'pivot' => 'Report builder',
        'pnl' => 'Profit and loss',
        'cashflow' => 'Cash flow',
        'counterparties' => 'Counterparties',
    ],
    'schedules' => [
        'none' => 'No sending',
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
    ],

    'table' => [
        'name' => 'Report',
        'type' => 'Type',
        'author' => 'Author',
        'shared' => 'Shared',
        'schedule' => 'Automatic sending',
        'recipients' => 'Recipients',
        'last_sent' => 'Last sent',
        'actions' => 'Actions',
        'yes' => 'Yes',
        'no' => 'No',
        'never' => 'Never sent',
        'recipients_count' => ':count recipients',
        'recipients_one' => '1 recipient',
        'unknown_author' => 'Unknown',
        'last_sent_on' => 'last :date',
    ],

    'actions' => [
        'open' => 'Open',
        'duplicate' => 'Duplicate',
        'edit' => 'Settings',
        'delete' => 'Delete',
    ],

    'empty' => [
        'title' => 'You have no saved reports yet',
        'text' => 'Set up a report in the builder or in one of the reports, press "Save report" and it will appear here, with optional automatic sending by e-mail.',
        'builder' => 'Go to the report builder',
    ],

    'save' => [
        'button' => 'Save report',
        'title' => 'Save the Report',
        'name' => 'Report name',
        'name_placeholder' => 'e.g. Monthly revenue by region',
        'description' => 'Description',
        'description_placeholder' => 'Short description, optional',
        'is_shared' => 'Shared with the team',
        'is_shared_help' => 'Other users of the company will see this report in the list.',
        'schedule' => 'Automatic e-mail sending',
        'schedule_help' => 'The report is sent as a PDF to the recipients when it is due.',
        'recipients' => 'Recipients',
        'recipients_placeholder' => 'E-mail addresses separated by comma or new line',
        'recipients_help' => 'Required for automatic sending.',
        'submit' => 'Save',
        'cancel' => 'Close',
        'saved_link' => 'Go to saved reports',
        'error' => 'The report could not be saved.',
    ],

    'edit' => [
        'title' => 'Report Settings',
        'config_note' => 'The period, filters and columns are changed in the report itself: open it, adjust it and save it as a new one.',
    ],

    'messages' => [
        'saved' => 'The report has been saved.',
        'duplicated' => 'The report has been duplicated.',
        'updated' => 'The changes are saved.',
        'deleted' => 'The report has been deleted.',
        'copy_suffix' => '(copy)',
        'not_found' => 'Report not found.',
        'target_missing' => 'The screen for this report type is not available yet.',
        'confirm_title' => 'Are you sure?',
        'confirm_delete' => 'The report ":name" will be deleted permanently.',
        'confirm_button' => 'Yes, delete',
        'cancel_button' => 'Cancel',
    ],

    'validation' => [
        'name_required' => 'Enter a report name.',
        'name_max' => 'The name may be at most 191 characters.',
        'type_invalid' => 'Invalid report type.',
        'config_invalid' => 'The report configuration is invalid.',
        'schedule_invalid' => 'Invalid sending schedule.',
        'recipients_required' => 'Add at least one recipient for automatic sending.',
        'recipients_invalid' => 'Invalid e-mail address: :email',
    ],

    'mail' => [
        'subject' => 'Report: :name — :company',
        'greeting' => 'Hello,',
        'intro' => 'Attached is the PDF of the automatically sent report ":name" of :company.',
        'description' => 'Description',
        'type' => 'Report type',
        'period' => 'Report parameters',
        'schedule' => 'Schedule',
        'generated_at' => 'Generated on :date',
        'open' => 'Open the report',
        'footer' => 'You receive this e-mail because you were added as a recipient of an automatically sent report in :app. The schedule can be changed from "Saved reports".',
        'attachment_name' => 'report',
    ],

    'command' => [
        'description' => 'Generates a PDF and e-mails the saved reports that are due (daily / weekly / monthly)',
        'no_reports' => 'No reports to send.',
        'found' => 'Reports to send: :count',
        'dry_run' => '[dry-run] would send ":name" (#:id, :type, :schedule) to: :recipients',
        'sent' => 'Sent: ":name" (#:id) to :count recipients',
        'skipped_class' => 'Skipped: ":name" (#:id) - PDF class :class is missing',
        'skipped_company' => 'Skipped: ":name" (#:id) - the company is inactive or missing',
        'skipped_recipients' => 'Skipped: ":name" (#:id) - no valid recipients',
        'failed' => 'Error for ":name" (#:id): :error',
        'summary' => 'Done: sent :sent, skipped :skipped, errors :failed.',
    ],
];
