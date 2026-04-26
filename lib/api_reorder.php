<?php

/**
 * AJAX-Endpoint: Reorder + Inline-Edit (name, template_id, priority).
 *
 * Aufruf: POST `index.php?rex-api-call=structure_replace_reorder`
 *   - kind: "cat" | "art"
 *   - id:   element id
 *   - clang: Sprach-ID
 *   - priority: optional, neue Priorität (>= 1)
 *   - name:     optional, neuer Name
 *   - template_id: optional, neue Template-ID (nur kind=art)
 *   - _csrf_token: CSRF
 */
class rex_api_structure_replace_reorder extends rex_api_function
{
    /** @var bool */
    protected $published = false;

    public function execute()
    {
        $user = rex::requireUser();

        $kind = rex_post('kind', 'string');
        $id = rex_post('id', 'int');
        $clang = rex_post('clang', 'int', rex_clang::getStartId());
        $priority = rex_post('priority', 'int', 0);
        $name = rex_post('name', 'string', '');
        $hasName = '' !== $name;
        $templateId = rex_post('template_id', 'int', -1);
        $hasTemplate = $templateId >= 0;

        if ($id <= 0 || !rex_clang::exists($clang)) {
            throw new rex_api_exception('Invalid id/clang.');
        }

        if ('cat' === $kind) {
            if (!$user->hasPerm('editCategory[]')) {
                throw new rex_api_exception('Missing permission editCategory[].');
            }
            if (!$user->getComplexPerm('structure')->hasCategoryPerm($id)) {
                throw new rex_api_exception('Missing structure perm.');
            }
            $cat = rex_category::get($id, $clang);
            if (!$cat) {
                throw new rex_api_exception('Category not found.');
            }
            $data = [
                'catname' => $hasName ? $name : $cat->getName(),
                'catpriority' => $priority > 0 ? $priority : $cat->getPriority(),
            ];
            rex_category_service::editCategory($id, $clang, $data);
            return new rex_api_result(true, rex_i18n::msg('category_updated'));
        }

        if ('art' === $kind) {
            if (!$user->hasPerm('editArticle[]')) {
                throw new rex_api_exception('Missing permission editArticle[].');
            }
            $art = rex_article::get($id, $clang);
            if (!$art) {
                throw new rex_api_exception('Article not found.');
            }
            if (!$user->getComplexPerm('structure')->hasCategoryPerm($art->getCategoryId())) {
                throw new rex_api_exception('Missing structure perm.');
            }
            $data = [
                'name' => $hasName ? $name : $art->getName(),
                'template_id' => $hasTemplate ? $templateId : $art->getTemplateId(),
                'priority' => $priority > 0 ? $priority : $art->getPriority(),
            ];
            rex_article_service::editArticle($id, $clang, $data);
            return new rex_api_result(true, rex_i18n::msg('article_updated'));
        }

        throw new rex_api_exception('Unknown kind.');
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
