<?php
$pageDescription=(string)$description;
if (in_array(($title ?? ''), ['Articles', t('nav.articles')], true)) {
    $pageDescription=t('misc.articles_module_desc');
}
?>
<?php ob_start(); ?><?= htmlspecialchars((string)$title) ?><?php $dxT = ob_get_clean(); ob_start(); ?><?= htmlspecialchars($pageDescription) ?><?php $dxD = ob_get_clean(); ob_start(); ?><button class="btn btn-outline-secondary disabled-control" type="button" disabled><i class="fas fa-plus mr-2"></i><?= htmlspecialchars(t('v.create_item')) ?></button><?php $dxA = ob_get_clean(); ?>
<?= mi_page_header($base, array (  0 => 'Module',), $dxT, $dxD, $dxA) ?>

<div class="card mi-admin-card">
    <div class="card-body">
        <div class="empty">
            <?= htmlspecialchars(t('v.this_module_keeps_the_existing_scope')) ?>
        </div>
    </div>
</div>
