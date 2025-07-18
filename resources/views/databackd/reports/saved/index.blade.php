@extends('layouts.app')

@section('content')
    <div class="content-wrapper dbk-reports dbk-reports-saved">

        <x-dbk.page-header :title="__('databackd-reports-saved.title')" :subtitle="__('databackd-reports-saved.subtitle')" />

        @if (session('error'))
            <div class="alert alert-warning f-14">{{ session('error') }}</div>
        @endif

        @if ($reports->isEmpty())
            <x-dbk.empty class="dbk-card" icon="bookmark" :title="__('databackd-reports-saved.empty.title')" :text="__('databackd-reports-saved.empty.text')">
                @if ($builderUrl)
                    <x-forms.link-primary :link="$builderUrl" icon="chart-bar">
                        @lang('databackd-reports-saved.empty.builder')
                    </x-forms.link-primary>
                @endif
            </x-dbk.empty>
        @else
            <div class="dbk-card">
                <div class="dbk-table-wrap is-flush">
                    <table class="dbk-table dbk-table--compact dbk-table--striped sr-table" id="saved-reports-table">
                        <thead>
                        <tr>
                            <th>@lang('databackd-reports-saved.table.name')</th>
                            <th>@lang('databackd-reports-saved.table.type')</th>
                            <th>@lang('databackd-reports-saved.table.author')</th>
                            <th>@lang('databackd-reports-saved.table.shared')</th>
                            <th>@lang('databackd-reports-saved.table.schedule')</th>
                            <th class="text-right">@lang('databackd-reports-saved.table.actions')</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($reports as $report)
                            @php
                                $recipientsCount = count((array)$report->recipients);
                                $manage = in_array($report->id, $manageIds, true);
                            @endphp
                            <tr id="sr-row-{{ $report->id }}">
                                <td class="sr-name">
                                    <a href="{{ route('databackd.reports.saved.open', $report->id) }}">{{ $report->name }}</a>
                                    @if ($report->description)
                                        <div class="sr-desc text-truncate" title="{{ $report->description }}">{{ $report->description }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="dbk-badge sr-badge sr-badge-{{ $report->type }}">@lang('databackd-reports-saved.types.' . $report->type)</span>
                                </td>
                                <td>
                                    @if ($report->creator)
                                        <x-employee :user="$report->creator" :disabledLink="true" />
                                    @else
                                        <span class="dbk-text-muted">@lang('databackd-reports-saved.table.unknown_author')</span>
                                    @endif
                                </td>
                                <td class="text-nowrap">
                                    @if ($report->is_shared)
                                        <i class="fa fa-users sr-shared-icon mr-1"></i>@lang('databackd-reports-saved.table.yes')
                                    @else
                                        <span class="dbk-text-muted"><i class="fa fa-lock mr-1"></i>@lang('databackd-reports-saved.table.no')</span>
                                    @endif
                                </td>
                                {{-- S12: schedule, recipients and the last sending in one column (the two extra columns were
                                     hidden behind the horizontal scroll even at 1440 px) --}}
                                <td class="sr-schedule">
                                    <span @class(['dbk-badge sr-badge', 'is-info' => $report->schedule !== 'none'])>
                                        @if ($report->schedule !== 'none')<i class="fa fa-envelope mr-1"></i>@endif
                                        @lang('databackd-reports-saved.schedules.' . $report->schedule)
                                    </span>
                                    @if ($report->schedule !== 'none' || $report->last_sent_at)
                                        <div class="sr-desc">
                                            @if ($report->schedule !== 'none')
                                                {{-- the e-mail addresses only for the users who manage the report --}}
                                                <div @if ($manage && $recipientsCount) title="{{ implode(', ', (array)$report->recipients) }}" @endif>{{ $recipientsCount === 1 ? __('databackd-reports-saved.table.recipients_one') : __('databackd-reports-saved.table.recipients_count', ['count' => $recipientsCount]) }}</div>
                                            @endif
                                            @if ($report->last_sent_at)
                                                {{ __('databackd-reports-saved.table.last_sent_on', ['date' => $report->last_sent_at->copy()->timezone(company()->timezone)->format('d.m.Y H:i')]) }}
                                            @else
                                                @lang('databackd-reports-saved.table.never')
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="text-right text-nowrap sr-actions">
                                    <a href="{{ route('databackd.reports.saved.open', $report->id) }}" class="btn btn-sm btn-secondary" title="@lang('databackd-reports-saved.actions.open')">
                                        <i class="fa fa-external-link-alt"></i><span class="sr-only">@lang('databackd-reports-saved.actions.open')</span>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-secondary sr-duplicate" data-id="{{ $report->id }}" title="@lang('databackd-reports-saved.actions.duplicate')">
                                        <i class="fa fa-copy"></i><span class="sr-only">@lang('databackd-reports-saved.actions.duplicate')</span>
                                    </button>
                                    @if ($manage)
                                        <button type="button" class="btn btn-sm btn-secondary sr-edit" title="@lang('databackd-reports-saved.actions.edit')"
                                            data-report="{{ json_encode(['id' => $report->id, 'name' => $report->name, 'description' => (string)$report->description, 'is_shared' => (bool)$report->is_shared, 'schedule' => $report->schedule, 'recipients' => implode(', ', (array)$report->recipients)], JSON_UNESCAPED_UNICODE) }}">
                                            <i class="fa fa-pen"></i><span class="sr-only">@lang('databackd-reports-saved.actions.edit')</span>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-link sr-delete" data-id="{{ $report->id }}" data-name="{{ $report->name }}" title="@lang('databackd-reports-saved.actions.delete')">
                                            <i class="fa fa-trash"></i><span class="sr-only">@lang('databackd-reports-saved.actions.delete')</span>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

    </div>

    {{-- S12: settings of a saved report (name, description, sharing, e-mail schedule) --}}
    <div class="modal fade" id="sr-edit-modal" tabindex="-1" role="dialog" aria-labelledby="sr-edit-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title f-18 f-w-500" id="sr-edit-title">@lang('databackd-reports-saved.edit.title')</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('databackd-misc.close') }}"><span aria-hidden="true">&times;</span></button>
                </div>
                <form id="sr-edit-form" autocomplete="off" onsubmit="return false;">
                    <div class="modal-body">
                        <input type="hidden" name="id" id="sr-edit-id">
                        <div class="form-group">
                            <label class="f-14 text-dark-grey mb-12" for="sr-edit-name">@lang('databackd-reports-saved.save.name') <sup class="f-14 mr-1">*</sup></label>
                            <input type="text" class="form-control height-35 f-14" name="name" id="sr-edit-name" maxlength="191">
                        </div>
                        <div class="form-group">
                            <label class="f-14 text-dark-grey mb-12" for="sr-edit-description">@lang('databackd-reports-saved.save.description')</label>
                            <textarea class="form-control f-14 pt-2" rows="2" name="description" id="sr-edit-description" maxlength="2000"></textarea>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" name="is_shared" id="sr-edit-shared" value="1">
                                <label class="custom-control-label f-14 text-dark" for="sr-edit-shared">@lang('databackd-reports-saved.save.is_shared')</label>
                            </div>
                            <small class="form-text text-lightest">@lang('databackd-reports-saved.save.is_shared_help')</small>
                        </div>
                        <div class="form-group">
                            <label class="f-14 text-dark-grey mb-12" for="sr-edit-schedule">@lang('databackd-reports-saved.save.schedule')</label>
                            <select class="form-control height-35 f-14" name="schedule" id="sr-edit-schedule">
                                @foreach (\App\Models\Databackd\FinSavedReport::SCHEDULES as $schedule)
                                    <option value="{{ $schedule }}">@lang('databackd-reports-saved.schedules.' . $schedule)</option>
                                @endforeach
                            </select>
                            <small class="form-text text-lightest">@lang('databackd-reports-saved.save.schedule_help')</small>
                        </div>
                        <div class="form-group" id="sr-edit-recipients-group">
                            <label class="f-14 text-dark-grey mb-12" for="sr-edit-recipients">@lang('databackd-reports-saved.save.recipients') <sup class="f-14 mr-1">*</sup></label>
                            <textarea class="form-control f-14 pt-2" rows="3" name="recipients" id="sr-edit-recipients"
                                      placeholder="@lang('databackd-reports-saved.save.recipients_placeholder')"></textarea>
                            <small class="form-text text-lightest">@lang('databackd-reports-saved.save.recipients_help')</small>
                        </div>
                        <p class="f-13 text-lightest mb-0"><i class="fa fa-info-circle mr-1"></i>@lang('databackd-reports-saved.edit.config_note')</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-cancel rounded f-14 p-2 mr-2" data-dismiss="modal">@lang('databackd-reports-saved.messages.cancel_button')</button>
                        <button type="button" class="btn btn-primary rounded f-14 p-2" id="sr-edit-submit">
                            <i class="fa fa-check mr-1"></i> @lang('databackd-reports-saved.save.submit')
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            var urlTemplates = {
                duplicate: "{{ route('databackd.reports.saved.duplicate', ':id') }}",
                destroy: "{{ route('databackd.reports.saved.destroy', ':id') }}"
            };

            // ---- Settings (name, sharing, schedule) ----
            var $edit = $('#sr-edit-modal').appendTo('body');
            var toggleEditRecipients = function () {
                $('#sr-edit-recipients-group').toggleClass('d-none', $('#sr-edit-schedule').val() === 'none');
            };
            $('#sr-edit-schedule').on('change', toggleEditRecipients);

            $('body').on('click', '.sr-edit', function () {
                var r = $(this).data('report');
                var $form = $('#sr-edit-form');
                $form.find('.is-invalid').removeClass('is-invalid');
                $form.find('.invalid-feedback, .help-block').remove();
                $('#sr-edit-id').val(r.id);
                $('#sr-edit-name').val(r.name);
                $('#sr-edit-description').val(r.description);
                $('#sr-edit-shared').prop('checked', !!r.is_shared);
                $('#sr-edit-schedule').val(r.schedule);
                $('#sr-edit-recipients').val(r.recipients);
                toggleEditRecipients();
                $edit.modal('show');
            });

            $edit.on('shown.bs.modal', function () { $('#sr-edit-name').trigger('focus'); });

            $('#sr-edit-submit').on('click', function () {
                $.easyAjax({
                    url: "{{ route('databackd.reports.saved.store') }}",
                    container: '#sr-edit-form',
                    type: 'POST',
                    disableButton: true,
                    buttonSelector: '#sr-edit-submit',
                    data: $('#sr-edit-form').serialize() + '&_token={{ csrf_token() }}',
                    success: function (response) {
                        if (response.status === 'success') {
                            $edit.modal('hide');
                            window.setTimeout(function () { window.location.reload(); }, 400);
                        }
                    }
                });
            });

            $('#sr-edit-name').on('keypress', function (event) {
                if (event.which === 13) {
                    $('#sr-edit-submit').trigger('click');
                }
            });

            $('body').on('click', '.sr-duplicate', function () {
                var id = $(this).data('id');

                $.easyAjax({
                    type: 'POST',
                    url: urlTemplates.duplicate.replace(':id', id),
                    data: {'_token': "{{ csrf_token() }}"},
                    disableButton: true,
                    buttonSelector: '.sr-duplicate[data-id="' + id + '"]',
                    success: function (response) {
                        if (response.status === 'success') {
                            window.setTimeout(function () { window.location.reload(); }, 600);
                        }
                    }
                });
            });

            $('body').on('click', '.sr-delete', function () {
                var id = $(this).data('id');
                var name = $(this).data('name');

                Swal.fire({
                    title: @json(__('databackd-reports-saved.messages.confirm_title')),
                    text: @json(__('databackd-reports-saved.messages.confirm_delete')).replace(':name', name),
                    icon: 'warning',
                    showCancelButton: true,
                    focusConfirm: false,
                    confirmButtonText: @json(__('databackd-reports-saved.messages.confirm_button')),
                    cancelButtonText: @json(__('databackd-reports-saved.messages.cancel_button')),
                    customClass: {
                        confirmButton: 'btn btn-primary mr-3',
                        cancelButton: 'btn btn-secondary'
                    },
                    showClass: {
                        popup: 'swal2-noanimation',
                        backdrop: 'swal2-noanimation'
                    },
                    buttonsStyling: false
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        return;
                    }

                    $.easyAjax({
                        type: 'POST',
                        url: urlTemplates.destroy.replace(':id', id),
                        data: {'_token': "{{ csrf_token() }}", '_method': 'DELETE'},
                        success: function (response) {
                            if (response.status === 'success') {
                                $('#sr-row-' + id).fadeOut(200, function () {
                                    $(this).remove();

                                    if ($('#saved-reports-table tbody tr').length === 0) {
                                        window.location.reload();
                                    }
                                });
                            }
                        }
                    });
                });
            });
        });
    </script>
@endpush
