<?php
$pageDescription=(string)$description;
if (($title ?? '') === 'Articles') {
    $pageDescription='Manage existing article and buying-guide workflow. CMS scope is unchanged in this rebuild.';
}
?>
<?php ob_start(); ?><?= htmlspecialchars((string)$title) ?><?php $dxT = ob_get_clean(); ob_start(); ?><?= htmlspecialchars($pageDescription) ?><?php $dxD = ob_get_clean(); ob_start(); ?><button class="btn btn-outline-secondary disabled-control" type="button" disabled><i class="fas fa-plus mr-2"></i>Create Item</button><?php $dxA = ob_get_clean(); ?>
<?= mi_page_header($base, array (  0 => 'Module',), $dxT, $dxD, $dxA) ?>

<div class="card mi-admin-card">
    <div class="card-body">
        <div class="empty">
            This module keeps the existing scope and is presented inside the new Admin shell. Expanded CMS functionality is intentionally out of scope for this rebuild.
        </div>
    </div>
</div>
