(function ($) {
    'use strict';

    $('.js-sidebar-toggle').on('click', function () {
        $('body').toggleClass('mi-sidebar-collapsed');
    });

    $('[data-toggle="tooltip"]').tooltip();

    $('.js-table-filter').on('input', function () {
        var target = $(this).data('target');
        var needle = String($(this).val() || '').toLowerCase();
        $(target).find('tbody tr').each(function () {
            var haystack = String($(this).data('filter-row') || $(this).text()).toLowerCase();
            $(this).toggle(haystack.indexOf(needle) !== -1);
        });
    });

    if ($.fn.DataTable) {
        $('.js-data-table').each(function () {
            if (!$.fn.DataTable.isDataTable(this)) {
                $(this).DataTable({
                    pageLength: 25,
                    order: [],
                    responsive: true
                });
            }
        });
    }
})(jQuery);
