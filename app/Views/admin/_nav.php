<?php $authz=new App\Services\Auth\AuthorizationService(); $user=$auth_user ?? null; ?>
<nav class="admin-nav">
  <?php if($authz->can($user,'admin.dashboard.view')): ?><a href="<?= $base ?>/admin">ภาพรวม</a><?php endif; ?>
  <?php if($authz->can($user,'products.view')): ?><a href="<?= $base ?>/admin/products">สินค้า</a><a href="<?= $base ?>/admin/product-aliases">Aliases</a><?php endif; ?>
  <?php if($authz->can($user,'review.view')): ?><a href="<?= $base ?>/admin/price-observations">Observations</a><a href="<?= $base ?>/admin/review-queue">Review Queue</a><?php endif; ?>
  <?php if($authz->can($user,'review.analytics.view')): ?><a href="<?= $base ?>/admin/review-analytics">Review Analytics</a><?php endif; ?>
  <?php if($authz->can($user,'snapshots.view')): ?><a href="<?= $base ?>/admin/price-indices">Price Index</a><?php endif; ?>
  <?php if($authz->can($user,'sources.view')): ?><a href="<?= $base ?>/admin/sources">Sources</a><?php endif; ?>
  <?php if($authz->can($user,'collection.jobs.view')): ?><a href="<?= $base ?>/admin/collector-jobs">Imports</a><?php endif; ?>
  <?php if($authz->can($user,'products.view')): ?><a href="<?= $base ?>/admin/articles">Articles</a><?php endif; ?>
  <?php if($authz->can($user,'audit.view')): ?><a href="<?= $base ?>/admin/audit-logs">Audit</a><?php endif; ?>
</nav>
