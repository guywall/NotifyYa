(function ($) {
    function renderRecaptchaWidgets() {
        if (!window.notifyYaFront || !notifyYaFront.recaptchaEnabled || typeof window.grecaptcha === 'undefined') {
            return;
        }

        $('.notifyya-recaptcha').each(function () {
            var $container = $(this);
            if ($container.data('widget-id') !== undefined) {
                return;
            }

            $container.data('widget-id', window.grecaptcha.render(this, {
                sitekey: $container.data('site-key')
            }));
        });
    }

    function resetRecaptcha($form) {
        var $container = $form.find('.notifyya-recaptcha');
        var widgetId = $container.data('widget-id');
        if (typeof widgetId !== 'undefined' && typeof window.grecaptcha !== 'undefined') {
            window.grecaptcha.reset(widgetId);
        }
    }

    function setFeedback($form, message, isError) {
        var $feedback = $form.find('.notifyya-form__feedback');
        $feedback.text(message);
        $feedback.removeClass('is-error is-success').addClass(isError ? 'is-error' : 'is-success').prop('hidden', false);
    }

    function openModal($widget) {
        $widget.find('.notifyya-modal').prop('hidden', false);
        renderRecaptchaWidgets();
    }

    function closeModal($widget) {
        $widget.find('.notifyya-modal').prop('hidden', true);
    }

    function updateVariableState($widget, variation) {
        var $button = $widget.find('.notifyya-open-button');
        var $helper = $widget.find('.notifyya-helper-text');
        var $variationField = $widget.find('input[name="variation_id"]');

        if (!variation || !variation.variation_id) {
            $button.prop('disabled', true).addClass('is-disabled');
            $helper.text(notifyYaFront.selectVariation);
            $variationField.val('0');
            return;
        }

        $variationField.val(variation.variation_id);

        if (variation.is_in_stock) {
            $button.prop('disabled', true).addClass('is-disabled');
            $helper.text(notifyYaFront.inStockMessage);
            return;
        }

        $button.prop('disabled', false).removeClass('is-disabled');
        $helper.text('');
    }

    $(document).on('click', '.notifyya-open-button', function () {
        var $button = $(this);
        if ($button.prop('disabled')) {
            return;
        }

        openModal($button.closest('.notifyya-widget'));
    });

    $(document).on('click', '.notifyya-modal__close, .notifyya-modal__backdrop', function () {
        closeModal($(this).closest('.notifyya-widget'));
    });

    $(document).on('submit', '.notifyya-form', function (event) {
        event.preventDefault();

        var $form = $(this);
        var $submit = $form.find('.notifyya-submit');

        $submit.prop('disabled', true);

        $.post(notifyYaFront.ajaxUrl, $form.serialize() + '&action=notifyya_subscribe&nonce=' + encodeURIComponent(notifyYaFront.nonce))
            .done(function (response) {
                var currentVariationId = $form.find('input[name="variation_id"]').val();
                setFeedback($form, response.data && response.data.message ? response.data.message : notifyYaFront.successMessage, false);
                $form[0].reset();
                $form.find('input[name="variation_id"]').val(currentVariationId);
                resetRecaptcha($form);
            })
            .fail(function (xhr) {
                var message = notifyYaFront.requestError;
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    message = xhr.responseJSON.data.message;
                }
                setFeedback($form, message, true);
                resetRecaptcha($form);
            })
            .always(function () {
                $submit.prop('disabled', false);
            });
    });

    $('.variations_form').on('found_variation', function (event, variation) {
        updateVariableState($(this).closest('.product').find('.notifyya-widget'), variation);
    });

    $('.variations_form').on('reset_data hide_variation', function () {
        updateVariableState($(this).closest('.product').find('.notifyya-widget'), null);
    });

    $(function () {
        renderRecaptchaWidgets();
    });
})(jQuery);