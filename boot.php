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

// Wenn 2FA aktiv, aber noch nicht verifiziert ist, muss die OTP-Eingabe
// Vorrang vor der ersetzten Strukturseite haben.
$structureReplaceHasPendingTwoFactorAuth = static function (): bool {
    if (!rex::isBackend() || null === rex::getUser()) {
        return false;
    }

    if (!rex_addon::get('2factor_auth')->isAvailable()) {
        return false;
    }

    if (!class_exists('FriendsOfREDAXO\\TwoFactorAuth\\one_time_password')) {
        return false;
    }

    $otp = FriendsOfREDAXO\TwoFactorAuth\one_time_password::getInstance();

    return $otp->isEnabled() && !$otp->isVerified();
};

rex_extension::register('PAGES_PREPARED', static function (rex_extension_point $ep) use ($structureReplaceIsAdmin, $structureReplaceHasPendingTwoFactorAuth): array {
    /** @var array<string, rex_be_page> $pages */
    $pages = $ep->getSubject();

    if (!$structureReplaceIsAdmin()) {
        return $pages;
    }

    if ($structureReplaceHasPendingTwoFactorAuth()) {
        if ('structure' === rex_be_controller::getCurrentPagePart(1)) {
            rex_be_controller::setCurrentPage('profile');
        }

        return $pages;
    }

    if (isset($pages['structure']) && $pages['structure'] instanceof rex_be_page_main) {
        $pages['structure']->setPath(
            rex_addon::get('structure_replace')->getPath('pages/structure_replace.php'),
        );
        $pages['structure']->setTitle(rex_i18n::msg('structure_replace_articles'));

        // Be_plus-Container der Strukturseite auf "container-fluid" zwingen,
        // damit die Verwaltung die volle Breite einnehmen kann. setProperty
        // ueberschreibt nur in-memory, package.yml des Core-Addons bleibt
        // unangetastet.
        if (rex_addon::get('be_plus')->isAvailable()) {
            rex_addon::get('structure')->setProperty('be_plus_container_class', 'container-fluid');
        }
    }

    return $pages;
});

// Assets nur auf der Strukturseite (nicht auf Subpages) einbinden.
// Auf content/edit und structure/content laden wir lediglich die CSS, da
// dort nur das Breadcrumb-Replacement benoetigt wird (BS5-Dropdowns liefert
// be_plus, das JS der Strukturseite ist hier nicht relevant).
rex_extension::register('PAGE_HEADER', static function (rex_extension_point $ep) use ($structureReplaceIsAdmin, $structureReplaceHasPendingTwoFactorAuth): string {
    $page = rex_be_controller::getCurrentPage();
    if (!$structureReplaceIsAdmin() || $structureReplaceHasPendingTwoFactorAuth()) {
        return (string) $ep->getSubject();
    }

    $version = rex_addon::get('structure_replace')->getVersion();
    $cssTag = '<link rel="stylesheet" type="text/css" href="' . rex_url::addonAssets('structure_replace', 'structure_replace.css') . '?v=' . $version . '">';

    if ($page === 'structure') {
        $jsTag = '<script src="' . rex_url::addonAssets('structure_replace', 'structure_replace.js') . '?v=' . $version . '" defer></script>';
        return $ep->getSubject() . $cssTag . $jsTag;
    }

    if (in_array($page, ['content/edit', 'structure/content'], true)) {
        return $ep->getSubject() . $cssTag;
    }

    return (string) $ep->getSubject();
});

/**
 * Ersetzt die Standard-Breadcrumb (Core-Structure-Addon) auf den
 * Bearbeitungsseiten durch eine moderne Dropdown-Breadcrumb (BS5), mit der
 * man innerhalb einer Kategorie zu Geschwister-Kategorien und innerhalb
 * eines Artikels zu Geschwister-Artikeln springen kann.
 */
rex_extension::register('OUTPUT_FILTER', static function (rex_extension_point $ep) use ($structureReplaceIsAdmin, $structureReplaceHasPendingTwoFactorAuth): string {
    /** @var string $output */
    $output = (string) $ep->getSubject();

    if (!rex::isBackend() || !$structureReplaceIsAdmin() || $structureReplaceHasPendingTwoFactorAuth()) {
        return $output;
    }

    $page = rex_be_controller::getCurrentPage();
    if (!in_array($page, ['content/edit', 'structure/content'], true)) {
        return $output;
    }

    if (!str_contains($output, 'id="rex-js-structure-breadcrumb"')) {
        return $output;
    }

    $articleId = rex_request('article_id', 'int');
    $categoryId = rex_request('category_id', 'int');
    $clang = rex_request('clang', 'int', rex_clang::getStartId());

    if ($articleId > 0 && $categoryId <= 0) {
        $art = rex_article::get($articleId, $clang);
        if ($art) {
            $categoryId = (int) $art->getCategoryId();
        }
    }

    $replacement = rex_structure_replace_breadcrumb::render($categoryId, $articleId, $clang, true);
    if ($replacement === '') {
        return $output;
    }

    $pattern = '#<div\s+id="rex-js-structure-breadcrumb"[^>]*>.*?</div>#s';
    $replaced = preg_replace($pattern, $replacement, $output, 1);

    return is_string($replaced) ? $replaced : $output;
});
