{{--
    "Save report" button + modal. Include from the report screens:
    @includeIf('databackd.reports.saved._save-button', ['type' => 'pivot', 'config' => $configArray])
    $type   pivot|pnl|cashflow|counterparties
    $config array (stored as the report config; for the standard reports ['filters' => $filters->toQuery()])
    Optional: the screen can replace the config before saving by setting $('#sr-config').val(JSON.stringify(config)).
--}}
@php
    $srType = $type ?? 'pivot';
    $srConfig = $config ?? [];
    $srBtnClass = $buttonClass ?? 'btn-secondary';
@endphp

@once
    <button type="button" class="btn {{ $srBtnClass }} btn-sm" id="sr-open-modal" data-toggle="modal" data-target="#sr-modal">
        <i class="fa fa-bookmark mr-1"></i> @lang('databackd-reports-saved.save.button')
    </button>

    <div class="modal fade" id="sr-modal" tabindex="-1" role="dialog" aria-labelledby="sr-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title f-18 f-w-500" id="sr-modal-title">@lang('databackd-reports-saved.save.title')</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('databackd-misc.close') }}"><span aria-hidden="true">&times;</span></button>
                </div>

                <form id="sr-form" autocomplete="off" onsubmit="return false;">
                    <div class="modal-body" id="sr-body">
                        <input type="hidden" name="type" id="sr-type" value="{{ $srType }}">
                        <input type="hidden" name="config" id="sr-config" value="{{ json_encode($srConfig, JSON_UNESCAPED_UNICODE) }}">

                        <div class="form-group">
                            <label class="f-14 text-dark-grey mb-12" for="sr-name">@lang('databackd-reports-saved.save.name') <sup class="f-14 mr-1">*</sup></label>
                            <input type="text" class="form-control height-35 f-14" name="name" id="sr-name" maxlength="191"
                                   placeholder="@lang('databackd-reports-saved.save.name_placeholder')">
                        </div>

                        <div class="form-group">
                            <label class="f-14 text-dark-grey mb-12" for="sr-description">@lang('databackd-reports-saved.save.description')</label>
                            <textarea class="form-control f-14 pt-2" rows="2" name="description" id="sr-description" maxlength="2000"
                                      placeholder="@lang('databackd-reports-saved.save.description_placeholder')"></textarea>
                        </div>

                        <div class="form-group">
                            <div class="custom-control custom-switch">
                                <input type="checkbox" class="custom-control-input" name="is_shared" id="sr-shared" value="1">
                                <label class="custom-control-label f-14 text-dark" for="sr-shared">@lang('databackd-reports-saved.save.is_shared')</label>
                            </div>
                            <small class="form-text text-lightest">@lang('databackd-reports-saved.save.is_shared_help')</small>
                        </div>

                        <div class="form-group">
                            <label class="f-14 text-dark-grey mb-12" for="sr-schedule">@lang('databackd-reports-saved.save.schedule')</label>
                            <select class="form-control height-35 f-14" name="schedule" id="sr-schedule">
                                @foreach (\App\Models\Databackd\FinSavedReport::SCHEDULES as $schedule)
                                    <option value="{{ $schedule }}">@lang('databackd-reports-saved.schedules.' . $schedule)</option>
                                @endforeach
                            </select>
                            <small class="form-text text-lightest">@lang('databackd-reports-saved.save.schedule_help')</small>
                        </div>

                        <div class="form-group d-none" id="sr-recipients-group">
                            <label class="f-14 text-dark-grey mb-12" for="sr-recipients">@lang('databackd-reports-saved.save.recipients') <sup class="f-14 mr-1">*</sup></label>
                            <textarea class="form-control f-14 pt-2" rows="3" name="recipients" id="sr-recipients"
                                      placeholder="@lang('databackd-reports-saved.save.recipients_placeholder')"></textarea>
                            <small class="form-text text-lightest">@lang('databackd-reports-saved.save.recipients_help')</small>
                        </div>
                    </div>

                    <div class="modal-footer" id="sr-footer">
                        <button type="button" class="btn btn-cancel rounded f-14 p-2 mr-2" data-dismiss="modal">@lang('databackd-reports-saved.save.cancel')</button>
                        <button type="button" class="btn btn-primary rounded f-14 p-2" id="sr-submit">
                            <i class="fa fa-check mr-1"></i> @lang('databackd-reports-saved.save.submit')
                        </button>
                    </div>
                </form>

                <div class="modal-body d-none text-center py-5" id="sr-success">
                    <i class="fa fa-check-circle f-36 text-success mb-3"></i>
                    <div class="f-16 text-dark mb-3" id="sr-success-message"></div>
                    <a href="{{ route('databackd.reports.saved.index') }}" class="btn btn-primary rounded f-14 p-2 mr-2">
                        <i class="fa fa-bookmark mr-1"></i> @lang('databackd-reports-saved.save.saved_link')
                    </a>
                    <button type="button" class="btn btn-cancel rounded f-14 p-2" data-dismiss="modal">@lang('databackd-reports-saved.save.cancel')</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            $(function () {
                var $modal = $('#sr-modal');

                // A modal inside the page content gets covered by the fixed header: move it to the body
                $modal.appendTo('body');

                var toggleRecipients = function () {
                    $('#sr-recipients-group').toggleClass('d-none', $('#sr-schedule').val() === 'none');
                };

                $('#sr-schedule').on('change', toggleRecipients);
                toggleRecipients();

                // S12: after a successful save the modal showed the success screen again on the next opening -
                // start with an empty form (another report of the same screen can be saved)
                $modal.on('hidden.bs.modal', function () {
                    if (!$('#sr-success').hasClass('d-none')) {
                        $('#sr-success').addClass('d-none');
                        $('#sr-form').removeClass('d-none')[0].reset();
                        toggleRecipients();
                    }
                });

                $modal.on('shown.bs.modal', function () {
                    if ($('#sr-form').is(':visible')) {
                        $('#sr-name').trigger('focus');
                    }
                });

                $('#sr-name').on('keypress', function (event) {
                    if (event.which === 13) {
                        $('#sr-submit').trigger('click');
                    }
                });

                $('#sr-submit').on('click', function () {
                    $.easyAjax({
                        url: "{{ route('databackd.reports.saved.store') }}",
                        container: '#sr-form',
                        type: 'POST',
                        disableButton: true,
                        buttonSelector: '#sr-submit',
                        data: $('#sr-form').serialize() + '&_token={{ csrf_token() }}',
                        success: function (response) {
                            if (response.status === 'success') {
                                $('#sr-success-message').text(response.message);
                                $('#sr-form').addClass('d-none');
                                $('#sr-success').removeClass('d-none');
                            }
                        }
                    });
                });
            });
        </script>
    @endpush
@endonce
