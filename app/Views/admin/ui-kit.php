<?= mi_page_header($base, ['Content'], 'Admin UI kit', 'The admin form, table, button and empty-state pattern on the DropX skin. Reference only; it saves nothing.', '') ?>
<form class="card mi-admin-card mb-3">
    <div class="card-header"><h2>Form card</h2></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group col-md-6"><label for="kit-a">Text field</label><input class="form-control" id="kit-a" placeholder="Placeholder"></div>
            <div class="form-group col-md-3"><label for="kit-b">Select</label><select class="form-control" id="kit-b"><option>Option</option></select></div>
            <div class="form-group col-md-3"><span class="mi-field-label">Status</span><div class="custom-control custom-checkbox mi-product-active-switch"><input id="kit-c" class="custom-control-input" type="checkbox" checked><label class="custom-control-label" for="kit-c">Active</label></div></div>
        </div>
        <div class="form-group"><label for="kit-d">Text area</label><textarea class="form-control" id="kit-d" rows="3"></textarea><small class="form-text text-muted">Helper text sits 4px under the field.</small></div>
    </div>
    <div class="card-footer"><button class="btn btn-primary" type="button">Save</button><button class="btn btn-outline-secondary" type="button">Cancel</button></div>
</form>
<div class="card mi-table-card mb-3">
    <div class="card-header"><h2>Table card</h2></div>
    <div class="table-responsive">
        <table class="table table-hover mi-admin-table mb-0">
            <thead><tr><th>Name</th><th class="text-right">Value</th><th>Action</th></tr></thead>
            <tbody><tr><td>Row</td><td class="text-right">1,250</td><td><button class="btn btn-sm btn-outline-secondary" type="button">Edit</button></td></tr></tbody>
        </table>
    </div>
</div>
<div class="card mi-admin-card mb-3"><div class="card-body"><p class="text-muted mb-0">Empty state: one sentence saying what is missing and what fills it.</p></div></div>
