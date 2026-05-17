<?php

/**
 * AJAX-Endpoint: macht einen regulaeren Artikel zum Startartikel der eigenen
 * Kategorie. Ersetzt damit den bisherigen Startartikel.
 *
 * Aufruf: POST `index.php?rex-api-call=structure_replace_to_start`
 *   - article_id: Artikel-ID, der zum Startartikel werden soll
 *   - clang:      Sprach-ID
 *   - _csrf_token
 */
class rex_api_structure_replace_to_start extends rex_api_function
{
    /** @var bool */
    protected $published = false;

    public function execute()
    {
        $user = rex::requireUser();

        $articleId = rex_post('article_id', 'int');
        $clang = rex_post('clang', 'int', rex_clang::getStartId());

        if ($articleId <= 0 || !rex_clang::exists($clang)) {
            throw new rex_api_exception('Invalid article_id/clang.');
        }

        $article = rex_article::get($articleId, $clang);
        if (!$article) {
            throw new rex_api_exception('Article not found.');
        }
        if ($article->isStartarticle()) {
            throw new rex_api_exception('Article is already startarticle.');
        }

        $categoryId = (int) $article->getCategoryId();

        // Rechte: wir orientieren uns an `rex_api_article2startarticle`
        // (Core), d. h. zusaetzlich zur Strukturperm wird das Recht
        // `article2startarticle[]` verlangt. `editArticle[]` ist Voraussetzung
        // dafuer, ueberhaupt Artikel zu bearbeiten.
        if (!$user->hasPerm('editArticle[]')) {
            throw new rex_api_exception('Missing permission editArticle[].');
        }
        if (!$user->hasPerm('article2startarticle[]')) {
            throw new rex_api_exception('Missing permission article2startarticle[].');
        }
        if (!$user->getComplexPerm('structure')->hasCategoryPerm($categoryId)) {
            throw new rex_api_exception('Missing structure perm.');
        }

        if (!rex_article_service::article2startarticle($articleId)) {
            throw new rex_api_exception(rex_i18n::msg('content_tostartarticle_failed'));
        }

        return new rex_api_result(true, rex_i18n::msg('content_tostartarticle_ok'));
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
