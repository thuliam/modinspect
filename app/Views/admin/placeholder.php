<?php
$pageDescription=(string)$description;
if (($title ?? '') === 'Articles') {
    $pageDescription='Manage existing article and buying-guide workflow. CMS scope is unchanged in this rebuild.';
}
?>
<div class="mi-page-header">
    <div>
        <span class="mi-breadcrumb">Admin / Module</span>
        <h1><?= htmlspecialchars((string)$title) ?></h1>
        <p><?= htmlspecialchars($pageDescription) ?></p>
    </div>
    <button class="btn btn-outline-secondary disabled-control" type="button" disabled><i class="fas fa-plus mr-2"></i>Create Item</button>
</div>

<div class="card mi-admin-card">
    <div class="card-body">
        <div class="empty">
            This module keeps the existing scope and is presented inside the new Admin shell. Expanded CMS functionality is intentionally out of scope for this rebuild.
        </div>
    </div>
</div>
