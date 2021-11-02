requirejs(['jquery'], function ($) {
    'use strict';

    /**
     * @type {{buttonsSelector: string, containerContext: string, massHide: string, massExpand: string, codeVisibilitySelector: string}}
     */
    var JsonView = {
        buttonsSelector: '[data-tab]',
        containerContext: '.jsonview-module',
        codeVisibilitySelector: '.expand, .retract',
        massHide: '.mass-hide-ce',
        massExpand: '.mass-expand-ce'
    };

    JsonView.initializeEvents = function () {
        if ($(JsonView.buttonsSelector).length) {
            $(JsonView.buttonsSelector).each(function (index, element) {
                $(element).on(
                    'click', function () {
                        $(JsonView.containerContext + ' .control-buttons .btn').removeClass('active');
                        $(this).addClass('active');
                        $('.tab').removeClass('active');
                        $('#tab-' + $(element).data('tab')).addClass('active');
                    }
                )
            })
        }

        if ($(JsonView.codeVisibilitySelector).length) {
            $(JsonView.codeVisibilitySelector).each(function (index, element) {
                $(element).on(
                    'click', function () {
                        $(element).closest('.ce-block').toggleClass('show-code')
                    }
                )
            })
        }

        if ($(JsonView.massHide).length) {
            $(JsonView.massHide).on(
                'click', function () {
                    $(JsonView.massHide).addClass('hidden')
                    $(JsonView.massExpand).removeClass('hidden')
                    $(JsonView.containerContext + ' .ce-block').toggleClass('show-code', false)
                }
            )
        }

        if ($(JsonView.massExpand).length) {
            $(JsonView.massExpand).on(
                'click', function () {
                    $(JsonView.massExpand).addClass('hidden')
                    $(JsonView.massHide).removeClass('hidden')
                    $(JsonView.containerContext + ' .ce-block').toggleClass('show-code', true)
                }
            )
        }
    };

    $(JsonView.initializeEvents);


    if ($('.tab').length > 0 && $('.tab.active').length === 0) {
        $('.tab:first').addClass('active');
        $(JsonView.containerContext + ' .control-buttons .btn:first').addClass('active');
    }

    if ($('.page-title').length > 0) {
        $('.page-title h1').hover(
            function (event) {
                $(this).find('.t3-icon').show();
            },
            function (event) {
                $(this).find('.t3-icon').hide();
            }
        )
    }

    return JsonView;
});
