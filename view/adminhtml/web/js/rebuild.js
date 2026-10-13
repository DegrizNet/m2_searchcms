/**
 * Magelan_SearchCms: rebuild button with confirmation (no inline script, CSP friendly).
 */
define([
    'jquery',
    'Magento_Ui/js/modal/confirm'
], function ($, confirm) {
    'use strict';

    return function (config, element) {
        var $root = $(element),
            $button = $root.find('[data-role="mgl-searchcms-rebuild"]'),
            $label = $button.find('span'),
            $result = $root.find('[data-role="mgl-searchcms-result"]'),
            idleLabel = $label.text();

        function show(type, text) {
            $result.removeClass('message-success message-error').addClass('message-' + type).text(text).prop('hidden', false);
        }

        function run() {
            $button.prop('disabled', true);
            $label.text(config.busyLabel);
            $result.prop('hidden', true);

            $.ajax({
                url: config.url,
                type: 'POST',
                dataType: 'json',
                data: {form_key: window.FORM_KEY},
                showLoader: true
            }).done(function (response) {
                show(response.success ? 'success' : 'error', response.message || '');
                if (response.status) {
                    $('[data-role="mgl-searchcms-status"]').text(response.status.text);
                    $('[data-role="mgl-searchcms-empty"]').text(response.status.empty);
                    $root.find('[data-role="mgl-searchcms-empty-warning"]').remove();
                    $button.removeClass('primary');
                }
                $label.text(response.success ? idleLabel : config.retryLabel);
            }).fail(function (xhr) {
                show('error', (xhr.responseJSON && xhr.responseJSON.message) || xhr.statusText || 'Error');
                $label.text(config.retryLabel);
            }).always(function () {
                $button.prop('disabled', false);
            });
        }

        $button.on('click', function () {
            confirm({
                title: config.confirmTitle,
                content: $('<p>').text(config.confirmText).prop('outerHTML'),
                buttons: [{
                    text: config.cancelButton,
                    class: 'action-secondary action-dismiss',
                    click: function (event) {
                        this.closeModal(event);
                    }
                }, {
                    text: config.confirmButton,
                    class: 'action-primary action-accept',
                    click: function (event) {
                        this.closeModal(event, true);
                    }
                }],
                actions: {
                    confirm: run
                }
            });
        });
    };
});
