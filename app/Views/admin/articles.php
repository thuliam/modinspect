<div class="mi-page-header">
    <div>
        <span class="mi-breadcrumb">Admin / Content</span>
        <h1>Articles</h1>
        <p>Existing article CRUD for guides and public content routes.</p>
    </div>
    <button class="btn btn-primary js-article-new" type="button"><i class="fas fa-plus mr-2"></i>Add Article</button>
</div>

<form id="article-form" class="card mi-admin-card mb-3" method="post" action="<?= $base ?>/admin/articles/save">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
    <input type="hidden" name="article_id" value="">
    <div class="card-header"><h2 id="article-form-title">Add Article</h2></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group col-md-4"><label>Title<input class="form-control" name="title" maxlength="255" required></label></div>
            <div class="form-group col-md-4"><label>Slug<input class="form-control" name="slug" maxlength="190" required></label></div>
            <div class="form-group col-md-4"><label>Status<select class="form-control" name="status"><option value="draft">Draft</option><option value="published">Published</option><option value="archived">Archived</option></select></label></div>
        </div>
        <label>Excerpt<textarea class="form-control" name="excerpt" rows="2"></textarea></label>
        <label>Body<textarea class="form-control" name="body" rows="6" required></textarea></label>
        <button class="btn btn-primary mt-2" type="submit">Save Article</button>
    </div>
</form>

<form id="articles-filters" class="card mi-admin-card mb-3 js-datatable-filters" data-target="#articles-table">
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-8"><label>Status<select class="form-control" name="status"><option value="">All</option><option value="draft">Draft</option><option value="published">Published</option><option value="archived">Archived</option></select></label></div>
            <div class="form-group col-md-4"><button class="btn btn-primary btn-block" type="submit">Apply Filters</button></div>
        </div>
    </div>
</form>

<div class="card mi-table-card">
    <div class="card-header"><h2>Article Library</h2></div>
    <div class="table-responsive">
        <table id="articles-table" class="table table-hover mi-admin-table mb-0 js-server-data-table" data-ajax="<?= $base ?>/admin/articles/data" data-filters="#articles-filters" data-page-length="25">
            <thead>
                <tr><th>Article</th><th>Slug</th><th>Status</th><th>Author</th><th>Published</th><th>Updated</th><th>Action</th></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
