            </div>
            <footer class="dx-footer">
                <span>&copy; <?= date('Y') ?> ModInspect Admin</span>
                <span><?= htmlspecialchars(t('footer.designed_by')) ?> <a href="https://datadropx.net" target="_blank" rel="noopener">DataDropX</a> <?= htmlspecialchars(t('footer.template')) ?></span>
            </footer>
        </main>
    </div>
</div>
<script src="<?= $base ?>/assets/admin/vendor/jquery/jquery-3.7.1.min.js"></script>
<script src="<?= $base ?>/assets/admin/vendor/bootstrap/js/bootstrap.bundle.js"></script>
<script src="<?= $base ?>/assets/admin/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src="<?= $base ?>/assets/admin/vendor/datatables/js/dataTables.bootstrap4.min.js"></script>
<script type="application/json" id="mi-i18n"><?= json_encode([
    'continue' => t('js.continue'), 'edit_product' => t('js.edit_product'), 'edit_product_sub' => t('js.edit_product_sub'), 'save_changes' => t('js.save_changes'),
    'add_product' => t('js.add_product'), 'add_product_sub' => t('js.add_product_sub'), 'save_product' => t('js.save_product'),
    'edit_alias' => t('js.edit_alias'), 'add_alias' => t('js.add_alias'), 'edit_article' => t('js.edit_article'), 'add_article' => t('js.add_article'),
    'dt' => ['processing' => t('dt.processing'), 'search' => t('dt.search'), 'lengthMenu' => t('dt.length_menu'), 'info' => t('dt.info'), 'infoEmpty' => t('dt.info_empty'),
        'infoFiltered' => t('dt.info_filtered'), 'emptyTable' => t('dt.empty_table'), 'zeroRecords' => t('dt.zero_records'),
        'paginate' => ['first' => t('dt.first'), 'last' => t('dt.last'), 'next' => t('dt.next'), 'previous' => t('dt.previous')]],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= $base ?>/assets/admin/js/admin.js"></script>
<script src="<?= $base ?>/assets/admin/vendor/select2/select2.min.js"></script>
<script src="<?= $base ?>/assets/admin/vendor/select2/th.js"></script>
<script src="<?= $base ?>/assets/mi-select.js?v=<?= (int) @filemtime(ROOT_PATH . "/public/assets/mi-select.js") ?>"></script>
</body>
</html>
