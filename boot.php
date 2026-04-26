<?php

/**
 * AddOn `structure_replace` – ersetzt die Backend-Seite `?page=structure`
 * komplett durch eine eigene Implementierung.
 *
 * Mechanik: `PAGES_PREPARED` -> `rex_be_page_main::setPath()` zeigt auf eine
 * eigene Datei. Subpages (`structure/content`, `structure/linkmap`, ...)
 * bleiben unangetastet, weil deren `subPath` nicht überschrieben wird.
 */

if (!rex::isBackend()) {
    return;
}

/**
 * Experimentell: nur Admins erhalten die Strukturseiten-Ersetzung.
 */
$structureReplaceIsAdmin = static function (): bool {
    $user = rex::getUser();
    return $user instanceof rex_user && $user->isAdmin();
};

rex_extension::register('PAGES_PREPARED', static function (rex_extension_point $ep) use ($structureReplaceIsAdmin): array {
    /** @var array<string, rex_be_page> $pages */
    $pages = $ep->getSubject();

    if (!$structureReplaceIsAdmin()) {
        return $pages;
    }

    if (isset($pages['structure']) && $pages['structure'] instanceof rex_be_page_main) {
        $pages['structure']->setPath(
            rex_addon::get('structure_replace')->getPath('pages/structure_replace.php'),
        );
        $pages['structure']->setTitle(rex_i18n::msg('structure_replace_articles'));
    }

    return $pages;
});

// Assets nur auf der Strukturseite (nicht auf Subpages) einbinden.
rex_extension::register('PAGE_HEADER', static function (rex_extension_point $ep) use ($structureReplaceIsAdmin): string {
    $page = rex_be_controller::getCurrentPage();
    if ($page !== 'structure' || !$structureReplaceIsAdmin()) {
        return (string) $ep->getSubject();
    }

    $version = rex_addon::get('structure_replace')->getVersion();
    $assets = '';
    $assets .= '<link rel="stylesheet" type="text/css" href="' . rex_url::addonAssets('structure_replace', 'structure_replace.css') . '?v=' . $version . '">';
    $assets .= '<script src="' . rex_url::addonAssets('structure_replace', 'structure_replace.js') . '?v=' . $version . '" defer></script>';

    return $ep->getSubject() . $assets;
});
