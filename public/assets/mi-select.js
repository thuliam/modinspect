/* LS-18 (owner request): every dropdown on the public site and in Admin uses Select2 4.0.13 (self-hosted, admin/vendor/select2).
   Glue only: one place for both layouts. A select keeps working without scripts (Select2 enhances the real <select>, the form posts it as before).
   Opt out with data-native (or a .js-select2-manual select that a page initialises itself, e.g. Compare); DataTables' own "show N rows" stays native.
   Long lists get a search box, short ones (8 options or fewer) do not. data-width="auto" keeps an inline control narrow. */
jQuery(function ($) {
    if (!$.fn.select2) { return; }
    window.miSelect2Options = function ($s, extra) {
        var $modal = $s.closest('.modal');
        return $.extend({
            language: 'th',
            width: $s.data('width') || '100%',
            minimumResultsForSearch: $s.find('option').length > 8 ? 0 : Infinity,
            dropdownParent: $modal.length ? $modal : $(document.body)
        }, extra || {});
    };
    window.miSelectInit = function (ctx) {
        $(ctx || document).find('select').not('[data-native], .js-select2-manual, .dataTables_length select, .select2-hidden-accessible').each(function () {
            var $s = $(this);
            $s.select2(window.miSelect2Options($s));
        });
    };
    window.miSelectInit(document);
});
