<div class="mi-page-header">
    <div>
        <span class="mi-breadcrumb">Admin / System</span>
        <h1>Audit Logs</h1>
        <p>Concise audit trail for administrative mutations. Full payloads are collapsed by default.</p>
    </div>
</div>

<form id="audits-filters" class="card mi-admin-card mb-3 js-datatable-filters" data-target="#audits-table">
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-2"><label>Actor ID<input class="form-control" name="actor" inputmode="numeric"></label></div>
            <div class="form-group col-md-2"><label>Action<input class="form-control" name="action" maxlength="120"></label></div>
            <div class="form-group col-md-2"><label>Entity<input class="form-control" name="entity" maxlength="100"></label></div>
            <div class="form-group col-md-2"><label>From<input class="form-control" type="date" name="from"></label></div>
            <div class="form-group col-md-2"><label>To<input class="form-control" type="date" name="to"></label></div>
            <div class="form-group col-md-2"><button class="btn btn-primary btn-block" type="submit">Apply</button></div>
        </div>
    </div>
</form>

<div class="card mi-table-card">
    <div class="card-header"><h2>Audit Events</h2></div>
    <div class="table-responsive">
        <table id="audits-table" class="table table-hover mi-admin-table mb-0 js-server-data-table" data-ajax="<?= $base ?>/admin/audit-logs/data" data-filters="#audits-filters" data-page-length="25">
            <thead><tr><th>Timestamp</th><th>Actor</th><th>Action</th><th>Entity</th><th>Summary</th><th>Details</th></tr></thead>
            <tbody></tbody>
        </table>
    </div>
</div>
