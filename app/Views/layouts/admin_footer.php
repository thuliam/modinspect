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
<script src="<?= $base ?>/assets/admin/js/admin.js"></script>
<script src="<?= $base ?>/assets/admin/vendor/select2/select2.min.js"></script>
<script src="<?= $base ?>/assets/admin/vendor/select2/th.js"></script>
<script src="<?= $base ?>/assets/mi-select.js?v=<?= (int) @filemtime(ROOT_PATH . "/public/assets/mi-select.js") ?>"></script>
</body>
</html>
