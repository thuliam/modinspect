<?php ob_start(); ?><?= htmlspecialchars(t('v.articles')) ?><?php $dxT = ob_get_clean(); ob_start(); ?><?= htmlspecialchars(t('v.existing_article_crud_for_guides_and')) ?><?php $dxD = ob_get_clean(); ob_start(); ?><button class="btn btn-primary js-article-new" type="button"><i class="fas fa-plus mr-2"></i><?= htmlspecialchars(t('v.add_article')) ?></button><?php $dxA = ob_get_clean(); ?>
<?= mi_page_header($base, array (  0 => 'Content',), $dxT, $dxD, $dxA) ?>

<form id="article-form" class="card mi-admin-card mb-3" method="post" action="<?= $base ?>/admin/articles/save">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(\App\Core\Csrf::token()) ?>">
    <input type="hidden" name="article_id" value="">
    <div class="card-header"><h2 id="article-form-title"><?= htmlspecialchars(t('v.add_article')) ?></h2></div>
    <div class="card-body">
        <div class="form-row">
            <div class="form-group col-md-4"><label><?= htmlspecialchars(t('v.title')) ?><input class="form-control" name="title" maxlength="255" required></label></div>
            <div class="form-group col-md-4"><label><?= htmlspecialchars(t('v.slug')) ?><input class="form-control" name="slug" maxlength="190" required></label></div>
            <div class="form-group col-md-4"><label><?= htmlspecialchars(t('v.status')) ?><select class="form-control" name="status"><option value="draft"><?= htmlspecialchars(t('v.draft')) ?></option><option value="published"><?= htmlspecialchars(t('v.published')) ?></option><option value="archived"><?= htmlspecialchars(t('v.archived')) ?></option></select></label></div>
            <div class="form-group col-md-4"><label><?= htmlspecialchars(t('v.publish_at_optional')) ?><input class="form-control" type="datetime-local" name="publish_at"></label><small class="form-text text-muted"><?= htmlspecialchars(t('v.used_only_when_status_is_published_l')) ?></small></div>
        </div>
        <label><?= htmlspecialchars(t('v.excerpt')) ?><textarea class="form-control" name="excerpt" rows="2"></textarea></label>
        <label><?= htmlspecialchars(t('v.body')) ?><textarea class="form-control" name="body" rows="6" required></textarea></label>
        <button class="btn btn-primary mt-2" type="submit"><?= htmlspecialchars(t('v.save_article')) ?></button>
    </div>
</form>

<form id="articles-filters" class="card mi-admin-card mb-3 js-datatable-filters" data-target="#articles-table">
    <div class="card-body">
        <div class="form-row align-items-end">
            <div class="form-group col-md-8"><label><?= htmlspecialchars(t('v.status')) ?><select class="form-control" name="status"><option value=""><?= htmlspecialchars(t('v.all')) ?></option><option value="draft"><?= htmlspecialchars(t('v.draft')) ?></option><option value="published"><?= htmlspecialchars(t('v.published')) ?></option><option value="archived"><?= htmlspecialchars(t('v.archived')) ?></option></select></label></div>
            <div class="form-group col-md-4"><button class="btn btn-primary btn-block" type="submit"><?= htmlspecialchars(t('v.apply_filters')) ?></button></div>
        </div>
    </div>
</form>

<div class="card mi-table-card">
    <div class="card-header"><h2><?= htmlspecialchars(t('v.article_library')) ?></h2></div>
    <div class="table-responsive">
        <table id="articles-table" class="table table-hover mi-admin-table mb-0 js-server-data-table" data-source-url="<?= $base ?>/admin/articles/data" data-filters="#articles-filters" data-page-length="25">
            <thead>
                <tr><th><?= htmlspecialchars(t('v.article')) ?></th><th><?= htmlspecialchars(t('v.slug')) ?></th><th><?= htmlspecialchars(t('v.status')) ?></th><th><?= htmlspecialchars(t('v.author')) ?></th><th><?= htmlspecialchars(t('v.published')) ?></th><th><?= htmlspecialchars(t('v.updated')) ?></th><th data-orderable="false"><?= htmlspecialchars(t('v.action')) ?></th></tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
