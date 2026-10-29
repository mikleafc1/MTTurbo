<?php /** پارشال صفحه‌بندی — متغیرها: page, pages, base (با ? یا & انتهایی) */ if (($pages ?? 1) > 1): ?>
<div class="pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
        <?php if ($i === $page): ?>
            <span class="current"><?= fa_num($i) ?></span>
        <?php else: ?>
            <a href="<?= e($base) ?>page=<?= $i ?>"><?= fa_num($i) ?></a>
        <?php endif; ?>
    <?php endfor; ?>
</div>
<?php endif; ?>
