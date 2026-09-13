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

    var serverTables = [];
    var params = new URLSearchParams(window.location.search || '');
    $('.js-datatable-filters').each(function () {
        $(this).find('input,select').each(function () {
            var name = $(this).attr('name');
            if (name && params.has(name) && !$(this).val()) {
                $(this).val(params.get(name));
            }
        });
    });

    $('.js-confirm-form').on('submit', function (event) {
        var message = $(this).data('confirm') || 'Continue?';
        if (!window.confirm(message)) event.preventDefault();
    });
    $(document).on('click', 'button[data-confirm]', function (event) {
        var message = $(this).data('confirm') || 'Continue?';
        if (!window.confirm(message)) event.preventDefault();
    });

    function activeFilters($table) {
        var selector = $table.data('filters');
        var data = {};
        if (!selector) return data;
        $(selector).find('input,select').each(function () {
            var name = $(this).attr('name');
            if (!name) return;
            data[name] = $(this).val();
        });
        return data;
    }

    if ($.fn.DataTable) {
        $('.js-server-data-table').each(function () {
            var $table = $(this);
            if ($.fn.DataTable.isDataTable(this)) return;
            var instance = $table.DataTable({
                pageLength: parseInt($table.data('page-length') || 25, 10),
                processing: true,
                serverSide: true,
                order: [],
                ajax: {
                    url: $table.data('ajax'),
                    type: 'GET',
                    data: function () { return activeFilters($table); }
                }
            });
            serverTables.push({ table: $table, instance: instance });
        });

        function redrawServerTable(item) {
            if (item.instance && typeof item.instance.draw === 'function') {
                item.instance.start = 0;
                item.instance.draw();
                return;
            }
            var api = item.table.data('miniDataTable');
            if (api && typeof api.draw === 'function') {
                api.start = 0;
                api.draw();
            }
        }

        $('.js-datatable-filters').on('submit', function (event) {
            event.preventDefault();
            var selector = $(this).data('target');
            serverTables.forEach(function (item) {
                if (!selector || item.table.is(selector)) {
                    redrawServerTable(item);
                }
            });
        });
        $('.js-datatable-filters').on('change', 'select,input', function () {
            $(this).closest('form').trigger('submit');
        });
    }

    function showProductForm() {
        var panel = $('#product-form-panel');
        if (panel.length && $.fn.collapse) {
            panel.collapse('show');
        } else {
            panel.show();
        }
        $('.js-product-new').attr('aria-expanded', 'true');
    }

    function hideProductForm() {
        var panel = $('#product-form-panel');
        if (panel.length && $.fn.collapse) {
            panel.collapse('hide');
        } else {
            panel.hide();
        }
        $('.js-product-new').attr('aria-expanded', 'false');
    }

    $(document).on('click', '.js-product-edit', function () {
        var data = $(this).data('product') || {};
        var form = $('#product-form');
        form.find('[name=product_id]').val(data.id || '');
        form.find('[name=category_id]').val(data.category_id || '');
        form.find('[name=brand_id]').val(data.brand_id || '');
        form.find('[name=model_name]').val(data.model_name || '');
        form.find('[name=slug]').val(data.slug || '');
        form.find('[name=full_name]').val(data.full_name || '');
        form.find('[name=generation]').val(data.generation || '');
        form.find('[name=spec_summary]').val(data.spec_summary || '');
        form.find('[name=image_path]').val(data.image_path || '');
        form.find('[name=is_active]').prop('checked', String(data.is_active) === '1');
        $('#product-form-title').text('Edit Product');
        $('#product-form-subtitle').text('Update the canonical Product Master record without changing market observations.');
        $('#product-form-submit-label').html('<i class="fas fa-save mr-2"></i>Save Changes');
        showProductForm();
        $('html, body').animate({ scrollTop: form.offset().top - 90 }, 200);
    });

    $('.js-product-new').on('click', function () {
        var form = $('#product-form');
        form[0].reset();
        form.find('[name=product_id]').val('');
        form.find('[name=is_active]').prop('checked', true);
        $('#product-form-title').text('Add Product');
        $('#product-form-subtitle').text('Create a canonical Product Master record for resolver and price workflows.');
        $('#product-form-submit-label').html('<i class="fas fa-save mr-2"></i>Save Product');
        showProductForm();
        setTimeout(function () { form.find('[name=category_id]').trigger('focus'); }, 150);
    });

    $('.js-product-form-close').on('click', function () {
        hideProductForm();
    });

    $(document).on('click', '.js-alias-edit', function () {
        var data = $(this).data('alias') || {};
        var form = $('#alias-form');
        form.find('[name=alias_id]').val(data.id || '');
        form.find('[name=product_id]').val(data.product_id || '');
        form.find('[name=alias_text]').val(data.alias_text || '');
        form.find('[name=source_note]').val(data.source_note || '');
        form.find('[name=confidence]').val(data.confidence || 100);
        $('#alias-form-title').text('Edit Alias');
        $('html, body').animate({ scrollTop: form.offset().top - 90 }, 200);
    });

    $('.js-alias-new').on('click', function () {
        var form = $('#alias-form');
        form[0].reset();
        form.find('[name=alias_id]').val('');
        form.find('[name=confidence]').val(100);
        $('#alias-form-title').text('Add Alias');
    });

    $(document).on('click', '.js-article-edit', function () {
        var data = $(this).data('article') || {};
        var form = $('#article-form');
        form.find('[name=article_id]').val(data.id || '');
        form.find('[name=title]').val(data.title || '');
        form.find('[name=slug]').val(data.slug || '');
        form.find('[name=excerpt]').val(data.excerpt || '');
        form.find('[name=body]').val(data.body || '');
        form.find('[name=status]').val(data.status || 'draft');
        $('#article-form-title').text('Edit Article');
        $('html, body').animate({ scrollTop: form.offset().top - 90 }, 200);
    });

    $('.js-article-new').on('click', function () {
        var form = $('#article-form');
        form[0].reset();
        form.find('[name=article_id]').val('');
        $('#article-form-title').text('Add Article');
    });
})(jQuery);
