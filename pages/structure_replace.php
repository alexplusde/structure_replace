<?php

/**
 * Moderne Ersatz-Seite für `?page=structure`.
 *
 * Layout (Bootstrap 5 via be_plus):
 *  - Sidebar: Kategorie-Baum mit Drag & Drop, CRUD, Status, Duplicate (structure_manager).
 *  - Main:    Artikel-Liste, Startartikel separat, Drag & Drop, CRUD, Status.
 *  - Modals:  Add/Edit-Forms emit metainfo EPs (`CAT_FORM_*` / `ART_FORM_*`).
 */

$addon = rex_addon::get('structure');

$structureContext = new rex_structure_context([
    'category_id' => rex_request('category_id', 'int'),
    'article_id' => rex_request('article_id', 'int'),
    'clang_id' => rex_request('clang', 'int'),
    'ctype_id' => rex_request('ctype', 'int'),
    'artstart' => rex_request('artstart', 'int'),
    'catstart' => rex_request('catstart', 'int'),
    'edit_id' => rex_request('edit_id', 'int'),
    'function' => rex_request('function', 'string'),
    'rows_per_page' => $addon->getProperty('rows_per_page', 50),
]);

$user = rex::requireUser();
$clang = $structureContext->getClangId();
$categoryId = $structureContext->getCategoryId();
$articleId = $structureContext->getArticleId();
$function = $structureContext->getFunction();
$editId = $structureContext->getEditId();
$ctxUrl = $structureContext->getContext();

if (0 === $clang) {
    echo rex_view::error('No valid clang.');
    return;
}

$catStatusTypes = rex_category_service::statusTypes();
$artStatusTypes = rex_article_service::statusTypes();

$perms = [
    'addCat' => $user->hasPerm('addCategory[]'),
    'editCat' => $user->hasPerm('editCategory[]'),
    'deleteCat' => $user->hasPerm('deleteCategory[]'),
    'publishCat' => $user->hasPerm('publishCategory[]'),
    'addArt' => $user->hasPerm('addArticle[]'),
    'editArt' => $user->hasPerm('editArticle[]'),
    'deleteArt' => $user->hasPerm('deleteArticle[]'),
    'publishArt' => $user->hasPerm('publishArticle[]'),
    'hasCatPerm' => $structureContext->hasCategoryPermission(),
];
$structurePerm = $user->getComplexPerm('structure');

echo rex_view::clangSwitchAsButtons($ctxUrl);
echo rex_api_function::getMessage();

// JS-Bridge
$apiUrlParams = rex_api_structure_replace_reorder::getUrlParams();
$bridge = [
    'reorderUrl' => rex_url::backendController(['rex-api-call' => 'structure_replace_reorder'], false),
    'clang' => $clang,
    'csrf' => [
        rex_csrf_token::PARAM => $apiUrlParams[rex_csrf_token::PARAM],
    ],
    'i18n' => [
        'reorderError' => rex_i18n::msg('structure_replace_reorder_error'),
        'saved' => rex_i18n::msg('structure_replace_saved'),
        'saveFailed' => rex_i18n::msg('structure_replace_save_failed'),
    ],
];
echo '<script type="application/json" id="structure-replace-bridge">' . json_encode($bridge, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';

// Auto-Open Modal wenn function=add_*/edit_* gesetzt
$autoOpenModal = null;
if (in_array($function, ['add_cat', 'edit_cat', 'add_art', 'edit_art'], true)) {
    $autoOpenModal = $function;
}

?>
<div class="rex-structure-replace" id="rex-structure-replace" data-current-category="<?= $categoryId ?>" data-clang="<?= $clang ?>" <?php if ($autoOpenModal): ?>data-auto-open="<?= rex_escape($autoOpenModal) ?>"<?php endif; ?>>
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="rex-sr-toasts" style="z-index:1090"></div>
    <div class="rex-sr-layout">
        <aside class="rex-sr-sidebar" data-pane="sidebar">
            <?php require __DIR__ . '/_sidebar.php'; ?>
        </aside>
        <div class="rex-sr-splitter" role="separator" aria-orientation="vertical" tabindex="0" aria-label="<?= rex_i18n::msg('structure_replace_resize') ?>"></div>
        <section class="rex-sr-main" data-pane="main">
            <?php require __DIR__ . '/_articles.php'; ?>
        </section>
    </div>
</div>
<?php require __DIR__ . '/_modals.php'; ?>
