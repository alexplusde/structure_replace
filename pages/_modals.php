<?php

/**
 * BS5-Modale für Add/Edit-Forms (Kategorie + Artikel) inkl. metainfo-EPs.
 *
 * Auto-open via JS, wenn `data-auto-open` am Wrapper gesetzt ist.
 */

/** @var rex_structure_context $structureContext */
/** @var rex_user $user */
/** @var array<string, bool> $perms */
/** @var int $clang */
/** @var int $categoryId */
/** @var int $articleId */
/** @var int $editId */
/** @var string $function */
/** @var rex_context $ctxUrl */

$contentAvailable = rex_addon::get('structure')->getPlugin('content')->isAvailable();
$dataColspan = 5;

// =================== KATEGORIE ADD/EDIT ===================

if ($perms['hasCatPerm']) {
    $catFormUrl = $ctxUrl->getUrl([], false);
    $isCatEdit = ('edit_cat' === $function) && $editId > 0;
    $isCatAdd = ('add_cat' === $function);

    $catEditCat = null;
    if ($isCatEdit) {
        $catEditCat = rex_category::get($editId, $clang);
    }

    $modalShow = ($isCatEdit || $isCatAdd) ? ' show d-block' : '';
    $modalAria = ($isCatEdit || $isCatAdd) ? 'true' : 'false';

    $title = $isCatEdit
        ? rex_i18n::msg('structure_replace_edit_category', $catEditCat?->getName() ?? '')
        : rex_i18n::msg('add_category');

    if ($isCatAdd && $perms['addCat']) {
        $metaButtons = rex_extension::registerPoint(new rex_extension_point('CAT_FORM_BUTTONS', '', [
            'id' => $categoryId,
            'clang' => $clang,
        ]));
        ?>
        <div class="modal fade" id="rex-sr-cat-modal" tabindex="-1" aria-labelledby="rex-sr-cat-modal-label" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <form class="modal-content" action="<?= rex_escape($catFormUrl) ?>" method="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="rex-sr-cat-modal-label"><?= rex_escape($title) ?></h5>
                        <a class="btn-close" href="<?= rex_escape($ctxUrl->getUrl(['function' => '', 'edit_id' => 0], false)) ?>" aria-label="Close"></a>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="edit_id" value="<?= $categoryId ?>" />
                        <input type="hidden" name="parent-category-id" value="<?= $categoryId ?>" />
                        <?= rex_api_category_add::getHiddenFields() ?>
                        <div class="mb-3">
                            <label class="form-label"><?= rex_i18n::msg('header_category') ?></label>
                            <input class="form-control" type="text" name="category-name" required maxlength="255" autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= rex_i18n::msg('header_priority') ?></label>
                            <input class="form-control" type="number" name="category-position" value="1" required min="1" inputmode="numeric">
                        </div>
                        <?= rex_extension::registerPoint(new rex_extension_point('CAT_FORM_ADD', '', [
                            'id' => $categoryId,
                            'clang' => $clang,
                            'data_colspan' => $dataColspan + 1,
                        ])) ?>
                    </div>
                    <div class="modal-footer">
                        <a class="btn btn-link" href="<?= rex_escape($ctxUrl->getUrl(['function' => '', 'edit_id' => 0], false)) ?>"><?= rex_i18n::msg('cancel') ?></a>
                        <?= $metaButtons ?>
                        <button class="btn btn-primary" type="submit" name="category-add-button"><?= rex_i18n::msg('add_category') ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    if ($isCatEdit && $catEditCat && $perms['editCat']) {
        $metaButtons = rex_extension::registerPoint(new rex_extension_point('CAT_FORM_BUTTONS', '', [
            'id' => $editId,
            'clang' => $clang,
        ]));
        // Original-EP erwartet $KAT (rex_sql) als 'category'. Wir liefern eine kompatible rex_sql-Instanz.
        $catSql = rex_sql::factory();
        $catSql->setQuery('SELECT * FROM ' . rex::getTable('article') . ' WHERE id=? AND clang_id=? AND startarticle=1', [$editId, $clang]);
        ?>
        <div class="modal fade" id="rex-sr-cat-modal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <form class="modal-content" action="<?= rex_escape($catFormUrl) ?>" method="post">
                    <div class="modal-header">
                        <h5 class="modal-title"><?= rex_escape($title) ?> <small class="text-muted">#<?= $editId ?></small></h5>
                        <a class="btn-close" href="<?= rex_escape($ctxUrl->getUrl(['function' => '', 'edit_id' => 0], false)) ?>" aria-label="Close"></a>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="edit_id" value="<?= $editId ?>">
                        <input type="hidden" name="category-id" value="<?= $editId ?>">
                        <?= rex_api_category_edit::getHiddenFields() ?>
                        <div class="mb-3">
                            <label class="form-label"><?= rex_i18n::msg('header_category') ?></label>
                            <input class="form-control" type="text" name="category-name" value="<?= rex_escape($catSql->getValue('catname')) ?>" required maxlength="255" autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= rex_i18n::msg('header_priority') ?></label>
                            <input class="form-control" type="number" name="category-position" value="<?= rex_escape($catSql->getValue('catpriority')) ?>" required min="1" inputmode="numeric">
                        </div>
                        <?= rex_extension::registerPoint(new rex_extension_point('CAT_FORM_EDIT', '', [
                            'id' => $editId,
                            'clang' => $clang,
                            'category' => $catSql,
                            'catname' => $catSql->getValue('catname'),
                            'catpriority' => $catSql->getValue('catpriority'),
                            'data_colspan' => $dataColspan + 1,
                        ])) ?>
                    </div>
                    <div class="modal-footer">
                        <a class="btn btn-link" href="<?= rex_escape($ctxUrl->getUrl(['function' => '', 'edit_id' => 0], false)) ?>"><?= rex_i18n::msg('cancel') ?></a>
                        <?= $metaButtons ?>
                        <button class="btn btn-primary" type="submit" name="category-edit-button"><?= rex_i18n::msg('save_category') ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
}

// =================== ARTIKEL ADD/EDIT ===================

if ($perms['hasCatPerm'] && $categoryId > 0) {
    $artFormUrl = $ctxUrl->getUrl([], false);
    $isArtEdit = ('edit_art' === $function) && $articleId > 0;
    $isArtAdd = ('add_art' === $function);

    $templateSelect = null;
    if ($contentAvailable) {
        $templateSelect = new rex_template_select($categoryId, $clang);
        $templateSelect->setName('template_id');
        $templateSelect->setSize(1);
        $templateSelect->setStyle('class="form-control selectpicker"');
    }

    if ($isArtAdd && $perms['addArt']) {
        $metaButtons = rex_extension::registerPoint(new rex_extension_point('ART_FORM_BUTTONS', '', [
            'id' => $categoryId,
            'clang' => $clang,
        ]));
        if ($templateSelect) {
            $templateSelect->setSelectedFromStartArticle();
        }
        ?>
        <div class="modal fade" id="rex-sr-art-modal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <form class="modal-content" action="<?= rex_escape($artFormUrl) ?>" method="post">
                    <div class="modal-header">
                        <h5 class="modal-title"><?= rex_i18n::msg('article_add') ?></h5>
                        <a class="btn-close" href="<?= rex_escape($ctxUrl->getUrl(['function' => '', 'article_id' => 0], false)) ?>" aria-label="Close"></a>
                    </div>
                    <div class="modal-body">
                        <?= rex_api_article_add::getHiddenFields() ?>
                        <div class="mb-3">
                            <label class="form-label"><?= rex_i18n::msg('header_article_name') ?></label>
                            <input class="form-control" type="text" name="article-name" required maxlength="255" autofocus>
                        </div>
                        <?php if ($templateSelect): ?>
                            <div class="mb-3">
                                <label class="form-label"><?= rex_i18n::msg('header_template') ?></label>
                                <?= $templateSelect->get() ?>
                            </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label"><?= rex_i18n::msg('header_priority') ?></label>
                            <input class="form-control" type="number" name="article-position" value="1" required min="1" inputmode="numeric">
                        </div>
                        <?= rex_extension::registerPoint(new rex_extension_point('ART_FORM_ADD', '', [
                            'id' => $categoryId,
                            'clang' => $clang,
                            'data_colspan' => $dataColspan + 1,
                        ])) ?>
                    </div>
                    <div class="modal-footer">
                        <a class="btn btn-link" href="<?= rex_escape($ctxUrl->getUrl(['function' => '', 'article_id' => 0], false)) ?>"><?= rex_i18n::msg('cancel') ?></a>
                        <?= $metaButtons ?>
                        <button class="btn btn-primary" type="submit" name="artadd_function"><?= rex_i18n::msg('article_add') ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    if ($isArtEdit && $perms['editArt']) {
        $artObj = rex_article::get($articleId, $clang);
        $artSql = rex_sql::factory();
        $artSql->setQuery('SELECT * FROM ' . rex::getTable('article') . ' WHERE id=? AND clang_id=?', [$articleId, $clang]);
        $metaButtons = rex_extension::registerPoint(new rex_extension_point('ART_FORM_BUTTONS', '', [
            'id' => $articleId,
            'clang' => $clang,
        ]));
        if ($templateSelect && $artObj) {
            $templateSelect->setSelected($artObj->getTemplateId());
        }
        ?>
        <div class="modal fade" id="rex-sr-art-modal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <form class="modal-content" action="<?= rex_escape($artFormUrl) ?>" method="post">
                    <div class="modal-header">
                        <h5 class="modal-title"><?= rex_i18n::msg('structure_replace_edit_article', $artObj?->getName() ?? '') ?> <small class="text-muted">#<?= $articleId ?></small></h5>
                        <a class="btn-close" href="<?= rex_escape($ctxUrl->getUrl(['function' => '', 'article_id' => 0], false)) ?>" aria-label="Close"></a>
                    </div>
                    <div class="modal-body">
                        <?= rex_api_article_edit::getHiddenFields() ?>
                        <input type="hidden" name="article_id" value="<?= $articleId ?>">
                        <div class="mb-3">
                            <label class="form-label"><?= rex_i18n::msg('header_article_name') ?></label>
                            <input class="form-control" type="text" name="article-name" value="<?= rex_escape($artSql->getValue('name')) ?>" required maxlength="255" autofocus>
                        </div>
                        <?php if ($templateSelect): ?>
                            <div class="mb-3">
                                <label class="form-label"><?= rex_i18n::msg('header_template') ?></label>
                                <?= $templateSelect->get() ?>
                            </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label"><?= rex_i18n::msg('header_priority') ?></label>
                            <input class="form-control" type="number" name="article-position" value="<?= rex_escape($artSql->getValue('priority')) ?>" required min="1" inputmode="numeric">
                        </div>
                        <?= rex_extension::registerPoint(new rex_extension_point('ART_FORM_EDIT', '', [
                            'id' => $articleId,
                            'clang' => $clang,
                            'article' => $artSql,
                            'name' => $artSql->getValue('name'),
                            'priority' => $artSql->getValue('priority'),
                            'data_colspan' => $dataColspan + 1,
                        ])) ?>
                    </div>
                    <div class="modal-footer">
                        <a class="btn btn-link" href="<?= rex_escape($ctxUrl->getUrl(['function' => '', 'article_id' => 0], false)) ?>"><?= rex_i18n::msg('cancel') ?></a>
                        <?= $metaButtons ?>
                        <button class="btn btn-primary" type="submit" name="artedit_function"><?= rex_i18n::msg('article_save') ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
}

// =================== DUPLIZIEREN (structure_manager copy form) ===================

if (rex_addon::get('structure_manager')->isAvailable() && $perms['hasCatPerm']) {
    $smCsrf = rex_csrf_token::factory('structure_manager');
    $smTree = \FriendsOfRedaxo\StructureManager\StructureManager::getTree();
    $smStatusOptions = \FriendsOfRedaxo\StructureManager\StructureManager::getAvailableStatusOptions();
    $smActionUrl = rex_url::backendPage('structure_manager/category', [], false);

    $renderTreeOptions = static function (array $items, int $level = 0) use (&$renderTreeOptions): string {
        $out = '';
        foreach ($items as $item) {
            $indent = str_repeat('&nbsp;&nbsp;', $level);
            $out .= '<option value="' . (int) $item['id'] . '">' . $indent . rex_escape($item['name']) . ' (' . (int) $item['id'] . ')</option>';
            if (!empty($item['children'])) {
                $out .= $renderTreeOptions($item['children'], $level + 1);
            }
        }
        return $out;
    };
    $treeOptions = $renderTreeOptions($smTree);
    $defaultSuffix = rex_i18n::msg('structure_copy');
    ?>
    <div class="modal fade" id="rex-sr-dup-cat-modal" tabindex="-1" aria-labelledby="rex-sr-dup-cat-modal-label" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <form class="modal-content" action="<?= rex_escape($smActionUrl) ?>" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="rex-sr-dup-cat-modal-label">
                        <?= rex_escape(rex_i18n::msg('structure_replace_duplicate')) ?>
                        <small class="text-muted" data-source-name></small>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?= $smCsrf->getHiddenField() ?>
                    <input type="hidden" name="addon_action" value="copy">
                    <input type="hidden" name="source_id" value="0" data-source-id-target>
                    <div class="mb-3">
                        <label class="form-label" for="rex-sr-dup-target"><strong><?= rex_i18n::msg('structure_manager.section.copy_category') ?></strong></label>
                        <select class="form-control selectpicker" data-live-search="true" id="rex-sr-dup-target" name="target_id">
                            <option value="0">— <?= rex_i18n::msg('no') ?> —</option>
                            <?= $treeOptions ?>
                        </select>
                    </div>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="rex-sr-dup-newname"><strong><?= rex_i18n::msg('header_category') ?></strong></label>
                            <input type="text" class="form-control" id="rex-sr-dup-newname" name="new_name" value="" placeholder="<?= rex_escape(rex_i18n::msg('structure_replace_duplicate_name_placeholder')) ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="rex-sr-dup-suffix"><strong><?= rex_i18n::msg('structure_replace_duplicate_suffix') ?></strong></label>
                            <input type="text" class="form-control" id="rex-sr-dup-suffix" name="suffix" value="<?= rex_escape($defaultSuffix) ?>">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label" for="rex-sr-dup-status"><strong><?= rex_i18n::msg('structure_replace_duplicate_status') ?></strong></label>
                        <select class="form-control" id="rex-sr-dup-status" name="status">
                            <option value="0">— <?= rex_i18n::msg('structure_replace_duplicate_status_inherit') ?> —</option>
                            <?php foreach ($smStatusOptions as $statusId => $statusLabel): ?>
                                <option value="<?= (int) $statusId ?>"><?= rex_escape($statusLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p class="text-muted small mb-0"><?= rex_i18n::msg('structure_manager.info.copy_help') ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-link" data-bs-dismiss="modal"><?= rex_i18n::msg('cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= rex_i18n::msg('structure_manager.action.copy') ?></button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

// =================== IFRAME-Modal (structure_manager etc.) ===================
?>
<div class="modal fade" id="rex-sr-iframe-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" data-modal-title-target><?= rex_i18n::msg('structure_replace_advanced_actions') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="min-height:60vh">
                <iframe data-iframe-target src="about:blank" style="width:100%;height:70vh;border:0"></iframe>
            </div>
        </div>
    </div>
</div>
