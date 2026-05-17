<?php

/**
 * Breadcrumb-Navigation für die ersetzte Struktur-Seite und für `content/edit`.
 *
 * Jeder Pfad-Eintrag wird als Bootstrap-5-Dropdown ausgegeben. Klick auf das
 * Caret oeffnet ein Menü mit den Geschwister-Elementen auf derselben Ebene,
 * sodass der User innerhalb einer Kategorie zu einer anderen Kategorie und
 * (im Editiermodus) innerhalb eines Artikels zu einem anderen Artikel
 * wechseln kann.
 *
 * @internal
 */
final class rex_structure_replace_breadcrumb
{
    /**
     * Rendert die Breadcrumb-Leiste als HTML.
     *
     * @param bool $showArticleSwitch Wenn true, wird der aktuelle Artikel als
     *                                eigener Eintrag mit Geschwister-Dropdown
     *                                angehaengt (nur sinnvoll auf
     *                                content/edit). In der Struktur-Uebersicht
     *                                bleibt der Eintrag aus.
     */
    public static function render(int $categoryId, int $articleId, int $clang, bool $showArticleSwitch = false): string
    {
        $user = rex::getUser();
        if (!$user instanceof rex_user) {
            return '';
        }
        $structurePerm = $user->getComplexPerm('structure');

        $currentArticle = null;
        if ($articleId > 0) {
            $currentArticle = rex_article::get($articleId, $clang);
            if ($currentArticle && $categoryId <= 0) {
                $categoryId = (int) $currentArticle->getCategoryId();
            }
        }

        $currentCategory = $categoryId > 0 ? rex_category::get($categoryId, $clang) : null;
        $tree = [];
        if ($currentCategory) {
            $tree = $currentCategory->getParentTree();
            $tree[] = $currentCategory;
        }

        $items = [];
        $items[] = self::renderRootItem($clang, $structurePerm, $categoryId);

        $previousId = 0;
        foreach ($tree as $cat) {
            if (!$structurePerm->hasCategoryPerm($cat->getId())) {
                continue;
            }
            $items[] = self::renderCategoryItem($cat, $previousId, $clang, $structurePerm, $categoryId);
            $previousId = $cat->getId();
        }

        if ($showArticleSwitch && $currentArticle instanceof rex_article) {
            $items[] = self::renderArticleItem($currentArticle, $categoryId, $clang);
        }

        return '<nav aria-label="breadcrumb" class="rex-sr-breadcrumb-nav" id="rex-js-structure-breadcrumb">'
            . '<ol class="breadcrumb rex-sr-breadcrumb">' . implode('', $items) . '</ol>'
            . '</nav>';
    }

    private static function renderRootItem(int $clang, rex_structure_perm $structurePerm, int $currentCategoryId): string
    {
        $url = rex_url::backendPage('structure', ['category_id' => 0, 'clang' => $clang], false);
        $label = '<i class="rex-icon rex-icon-structure-root-level" aria-hidden="true"></i> '
            . rex_escape(rex_i18n::msg('root_level'));
        $isActive = $currentCategoryId <= 0;

        if ($structurePerm->hasMountpoints()) {
            $rootCats = $structurePerm->getMountpointCategories();
        } else {
            $rootCats = rex_category::getRootCategories(false, $clang);
        }
        $menu = self::renderCategoryMenu($rootCats, $clang, $structurePerm, $currentCategoryId);

        return self::renderDropdownItem($label, $url, $menu, $isActive, 'rex-sr-breadcrumb-root');
    }

    private static function renderCategoryItem(rex_category $cat, int $parentId, int $clang, rex_structure_perm $structurePerm, int $currentCategoryId): string
    {
        $id = $cat->getId();
        $url = rex_url::backendPage('structure', ['category_id' => $id, 'clang' => $clang], false);

        if ($parentId > 0) {
            $parent = rex_category::get($parentId, $clang);
            $siblings = $parent ? $parent->getChildren(false) : [];
        } else {
            $siblings = $structurePerm->hasMountpoints()
                ? $structurePerm->getMountpointCategories()
                : rex_category::getRootCategories(false, $clang);
        }
        $menu = self::renderCategoryMenu($siblings, $clang, $structurePerm, $currentCategoryId);

        $name = $cat->getName();
        if ($name === '') {
            $name = '#' . $id;
        }

        return self::renderDropdownItem(rex_escape($name), $url, $menu, $id === $currentCategoryId);
    }

    private static function renderArticleItem(rex_article $article, int $categoryId, int $clang): string
    {
        $url = rex_url::backendPage('content/edit', [
            'article_id' => $article->getId(),
            'clang' => $clang,
            'mode' => 'edit',
        ], false);

        $siblings = [];
        $cat = $categoryId > 0 ? rex_category::get($categoryId, $clang) : null;
        if ($cat) {
            $startArt = rex_article::get($categoryId, $clang);
            if ($startArt) {
                $siblings[] = $startArt;
            }
            foreach ($cat->getArticles(false) as $art) {
                if ($art->getId() === $categoryId) {
                    continue;
                }
                $siblings[] = $art;
            }
        }
        $menu = self::renderArticleMenu($siblings, $clang, $article->getId());

        $name = $article->getName();
        if ($name === '') {
            $name = '#' . $article->getId();
        }

        return self::renderDropdownItem(rex_escape($name), $url, $menu, true);
    }

    /**
     * @param list<rex_category> $categories
     */
    private static function renderCategoryMenu(array $categories, int $clang, rex_structure_perm $structurePerm, int $currentCategoryId): string
    {
        $entries = [];
        foreach ($categories as $cat) {
            if (!$structurePerm->hasCategoryPerm($cat->getId())) {
                continue;
            }
            $isActive = $cat->getId() === $currentCategoryId;
            $url = rex_url::backendPage('structure', ['category_id' => $cat->getId(), 'clang' => $clang], false);
            $name = $cat->getName();
            if ($name === '') {
                $name = '#' . $cat->getId();
            }
            $entries[] = '<li><a class="dropdown-item' . ($isActive ? ' active" aria-current="true' : '') . '" href="'
                . rex_escape($url) . '"><i class="rex-icon rex-icon-category" aria-hidden="true"></i> '
                . rex_escape($name) . '</a></li>';
        }
        if (count($entries) === 0) {
            return '';
        }

        return '<ul class="dropdown-menu">' . implode('', $entries) . '</ul>';
    }

    /**
     * @param list<rex_article> $articles
     */
    private static function renderArticleMenu(array $articles, int $clang, int $currentArticleId): string
    {
        $entries = [];
        foreach ($articles as $art) {
            $isActive = $art->getId() === $currentArticleId;
            $url = rex_url::backendPage('content/edit', [
                'article_id' => $art->getId(),
                'clang' => $clang,
                'mode' => 'edit',
            ], false);
            $name = $art->getName();
            if ($name === '') {
                $name = '#' . $art->getId();
            }
            $icon = $art->isStartarticle() ? 'rex-icon-startarticle' : 'rex-icon-article';
            $entries[] = '<li><a class="dropdown-item' . ($isActive ? ' active" aria-current="true' : '') . '" href="'
                . rex_escape($url) . '"><i class="rex-icon ' . $icon . '" aria-hidden="true"></i> '
                . rex_escape($name) . '</a></li>';
        }
        if (count($entries) === 0) {
            return '';
        }

        return '<ul class="dropdown-menu">' . implode('', $entries) . '</ul>';
    }

    private static function renderDropdownItem(string $label, string $url, string $menu, bool $isActive, string $extraClass = ''): string
    {
        $liClass = 'breadcrumb-item rex-sr-breadcrumb-item dropdown';
        if ($isActive) {
            $liClass .= ' active';
        }
        if ($extraClass !== '') {
            $liClass .= ' ' . $extraClass;
        }

        $aria = $isActive ? ' aria-current="page"' : '';
        $hasMenu = $menu !== '';

        $html = '<li class="' . $liClass . '"' . $aria . '>';
        $html .= '<a class="rex-sr-breadcrumb-link" href="' . rex_escape($url) . '">' . $label . '</a>';
        if ($hasMenu) {
            $html .= '<button type="button" class="btn btn-link btn-sm rex-sr-breadcrumb-toggle" '
                . 'data-bs-toggle="dropdown" aria-expanded="false" aria-label="'
                . rex_escape(rex_i18n::msg('structure_replace_breadcrumb_switch')) . '">'
                . '<i class="rex-icon fa-caret-down" aria-hidden="true"></i>'
                . '</button>';
            $html .= $menu;
        }
        $html .= '</li>';

        return $html;
    }
}
