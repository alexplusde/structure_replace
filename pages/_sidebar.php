<?php

/**
 * Sidebar: Kategorie-Baum (ohne Root id=0).
 * Aufklappen client-seitig (Bootstrap collapse), CRUD via Modal/API.
 *
 * Erwartete Variablen:
 *   $structureContext, $user, $perms, $catStatusTypes, $clang, $categoryId,
 *   $structurePerm, $ctxUrl
 */

/** @var rex_structure_context $structureContext */
/** @var rex_user $user */
/** @var array<string, bool> $perms */
/** @var array<int, array{0:string,1:string,2:string}> $catStatusTypes */
/** @var int $clang */
/** @var int $categoryId */
/** @var rex_complex_perm_categories $structurePerm */
/** @var rex_context $ctxUrl */

$smAvailable = rex_addon::get('structure_manager')->isAvailable();

// Pfad-IDs der aktuellen Kategorie (für initial-aufgeklappten Zweig)
$pathIds = [];
$currentCat = $categoryId > 0 ? rex_category::get($categoryId, $clang) : null;
if ($currentCat) {
    foreach ($currentCat->getParentTree() as $p) {
        $pathIds[$p->getId()] = true;
    }
    $pathIds[$categoryId] = true;
}

// Wurzel-Kategorien des Users (Mountpoints oder echte Roots)
if ($structurePerm->hasMountpoints()) {
    $rootCats = $structurePerm->getMountpointCategories();
} else {
    $rootCats = rex_category::getRootCategories(false, $clang);
}

// Wenn die Root nur genau eine Kategorie hat, diese (und ggf. ihre einzige
// Tochter etc.) automatisch aufgeklappt darstellen, damit der Nutzer nicht
// erst klicken muss.
if (count($rootCats) === 1) {
    $auto = $rootCats[array_key_first($rootCats)];
    while ($auto instanceof rex_category) {
        $pathIds[$auto->getId()] = true;
        $autoChildren = $auto->getChildren(false);
        if (count($autoChildren) !== 1) {
            break;
        }
        $auto = $autoChildren[0];
    }
}

/**
 * Rendert eine Kategorie-Zeile inkl. Kinder rekursiv.
 */
$renderRow = static function (rex_category $cat, int $depth) use (&$renderRow, $structureContext, $perms, $structurePerm, $catStatusTypes, $ctxUrl, $clang, $categoryId, $pathIds, $smAvailable): string {
    $id = $cat->getId();
    if ($id <= 0) {
        return '';
    }
    if (!$structurePerm->hasCategoryPerm($id)) {
        return '';
    }

    $children = $cat->getChildren(false);
    $hasChildren = count($children) > 0;
    $expanded = isset($pathIds[$id]);
    $isCurrent = $id === $categoryId;

    $status = (int) $cat->getValue('status');
    $statusType = $catStatusTypes[$status] ?? ($catStatusTypes[$cat->isOnline() ? 1 : 0] ?? $catStatusTypes[0]);

    $name = $cat->getName();
    if ($name === '') {
        $name = '#' . $id;
    }
    $iconClass = $hasChildren ? 'rex-icon-category' : 'rex-icon-category-without-elements';
    $nameUrl = $ctxUrl->getUrl(['category_id' => $id, 'article_id' => 0], false);

    // Aktionen — fixe Slot-Reihenfolge: Status, Edit, Duplicate, Delete.
    // Nicht verfuegbare Slots werden durch leere Platzhalter ersetzt, damit
    // die Icons in allen Zeilen exakt buendig stehen.
    $slotPlaceholder = '<span class="rex-sr-action-slot is-empty" aria-hidden="true"></span>';
    $actions = '';

    // Status (mit Label, immer Icon + Text)
    if ($perms['publishCat']) {
        $actions .= '<div class="dropdown d-inline-block rex-sr-action-slot">'
            . '<button class="btn btn-sm btn-link dropdown-toggle ' . rex_escape($statusType[1]) . '" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="' . rex_escape($statusType[0]) . '">'
            . '<i class="rex-icon ' . rex_escape($statusType[2]) . '" aria-hidden="true"></i>'
            . '<span class="rex-sr-status-label">' . rex_escape($statusType[0]) . '</span>'
            . '</button>'
            . '<ul class="dropdown-menu dropdown-menu-end">';
        foreach ($catStatusTypes as $key => $t) {
            $url = $ctxUrl->getUrl(['category-id' => $id, 'cat_status' => $key] + rex_api_category_status::getUrlParams(), false);
            $actions .= "\n" . '<li><a class="dropdown-item ' . rex_escape($t[1]) . '" href="' . rex_escape($url) . '"><i class="rex-icon ' . rex_escape($t[2]) . '"></i> ' . rex_escape($t[0]) . '</a></li>';
        }
        $actions .= "\n" . '</ul></div>' . "\n";
    } else {
        // Read-only-Status: trotzdem Label + Icon ausgeben
        $actions .= '<span class="rex-sr-action-slot ' . rex_escape($statusType[1]) . '" title="' . rex_escape($statusType[0]) . '">'
            . '<i class="rex-icon ' . rex_escape($statusType[2]) . '" aria-hidden="true"></i>'
            . '<span class="rex-sr-status-label">' . rex_escape($statusType[0]) . '</span>'
            . '</span>' . "\n";
    }

    // Edit
    if ($perms['editCat']) {
        $editUrl = $ctxUrl->getUrl(['category_id' => $structureContext->getCategoryId(), 'edit_id' => $id, 'function' => 'edit_cat'], false);
        $actions .= '<a href="' . rex_escape($editUrl) . '" class="btn btn-sm btn-link rex-sr-action-slot" title="' . rex_i18n::msg('change') . '" aria-label="' . rex_i18n::msg('change') . '"><i class="rex-icon rex-icon-edit"></i></a>' . "\n";
    } else {
        $actions .= $slotPlaceholder . "\n";
    }

    // Duplicate
    if ($smAvailable && $perms['editCat']) {
        $actions .= '<button type="button" class="btn btn-sm btn-link rex-sr-action-slot" data-bs-toggle="modal" data-bs-target="#rex-sr-dup-cat-modal" data-source-id="' . $id . '" data-source-name="' . rex_escape($name) . '" title="' . rex_escape(rex_i18n::msg('structure_replace_duplicate')) . '" aria-label="' . rex_escape(rex_i18n::msg('structure_replace_duplicate')) . '"><i class="rex-icon fa-copy"></i></button>' . "\n";
    } else {
        $actions .= $slotPlaceholder . "\n";
    }

    // Delete
    if ($perms['deleteCat']) {
        $delUrl = $ctxUrl->getUrl(['category-id' => $id] + rex_api_category_delete::getUrlParams(), false);
        $actions .= '<a href="' . rex_escape($delUrl) . '" data-confirm="' . rex_i18n::msg('structure_delete_all_clangs') . '" class="btn btn-sm btn-link text-danger rex-sr-action-slot" title="' . rex_i18n::msg('delete') . '" aria-label="' . rex_i18n::msg('delete') . '"><i class="rex-icon rex-icon-delete"></i></a>' . "\n";
    } else {
        $actions .= $slotPlaceholder . "\n";
    }

    // Toggle (Bootstrap Collapse)
    $collapseId = 'rex-sr-cat-' . $id;
    if ($hasChildren) {
        $toggle = '<button type="button" class="btn btn-sm btn-link p-0 rex-sr-toggle" data-bs-toggle="collapse" data-bs-target="#' . $collapseId . '" aria-expanded="' . ($expanded ? 'true' : 'false') . '" aria-controls="' . $collapseId . '" aria-label="' . rex_i18n::msg('structure_replace_expand') . '"><i class="rex-icon fa-chevron-right" aria-hidden="true"></i></button>';
    } else {
        $toggle = '<span class="rex-sr-toggle is-empty" aria-hidden="true"></span>';
    }

    $movable = $perms['editCat'];
    $handle = $movable
        ? '<span class="rex-sr-handle" aria-hidden="true"><i class="rex-icon fa-grip-vertical"></i></span>'
        : '<span class="rex-sr-handle is-disabled" aria-hidden="true"></span>';

    $liClasses = 'rex-sr-tree-item' . ($isCurrent ? ' is-current' : '') . ($expanded ? ' is-expanded' : '');

    $childMarkup = '';
    if ($hasChildren) {
        $childRendered = '';
        foreach ($children as $child) {
            $childRendered .= $renderRow($child, $depth + 1);
        }
        if ($childRendered !== '') {
            $childMarkup = '<div id="' . $collapseId . '" class="collapse' . ($expanded ? ' show' : '') . '">'
                . '<ul class="rex-sr-tree" data-parent-id="' . $id . '">'
                . $childRendered
                . '</ul></div>';
        }
    }

    return '<li class="' . $liClasses . '" data-cat-id="' . $id . '" data-priority="' . (int) $cat->getPriority() . '">' . "\n"
        . '<div class="rex-sr-row">' . "\n"
        . $handle . "\n"
        . $toggle . "\n"
        . '<a class="rex-sr-name" href="' . rex_escape($nameUrl) . '"><i class="rex-icon ' . $iconClass . '"></i> ' . rex_escape($name) . '</a>' . "\n"
        . '<span class="badge text-bg-light text-muted rex-sr-id">#' . $id . '</span>' . "\n"
        . '<span class="rex-sr-actions">' . "\n" . $actions . "\n" . '</span>' . "\n"
        . '</div>' . "\n"
        . $childMarkup
        . '</li>' . "\n";
};

?>
<div class="rex-sr-pane">
    <div class="rex-sr-pane-header">
        <strong class="me-auto"><i class="rex-icon rex-icon-open-category"></i> <span class="rex-sr-pane-title-text"><?= rex_i18n::msg('structure_replace_categories') ?></span></strong>
        <?php if ($perms['addCat']): ?>
            <a class="btn btn-sm btn-secondary rex-sr-pane-action" href="<?= rex_escape($ctxUrl->getUrl(['function' => 'add_cat'], false)) ?>" title="<?= rex_i18n::msg('add_category') ?>" aria-label="<?= rex_i18n::msg('add_category') ?>"><i class="rex-icon rex-icon-add-category"></i><span class="rex-sr-btn-label"> <?= rex_i18n::msg('add_category') ?></span></a>
        <?php endif; ?>
        <button type="button" class="btn btn-sm btn-link" data-sr-toggle="maximize" title="<?= rex_i18n::msg('structure_replace_toggle_max') ?>" aria-label="<?= rex_i18n::msg('structure_replace_toggle_max') ?>">
            <i class="rex-icon fa-arrows-left-right-to-line"></i>
        </button>
    </div>
    <div class="rex-sr-pane-body">
        <ul class="rex-sr-tree rex-sr-tree-root" data-parent-id="0">
            <?php
            $hasAny = false;
            foreach ($rootCats as $cat) {
                $rendered = $renderRow($cat, 0);
                if ($rendered !== '') {
                    $hasAny = true;
                    echo $rendered;
                }
            }
            ?>
            <?php if (!$hasAny): ?>
                <li class="text-muted p-2"><?= rex_i18n::msg('structure_replace_no_categories') ?></li>
            <?php endif; ?>
        </ul>
    </div>
</div>
