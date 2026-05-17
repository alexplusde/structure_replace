<?php

/**
 * AJAX-Endpoint: Batch-Aktionen für Artikel und Kategorien.
 *
 * Aufruf: POST `index.php?rex-api-call=structure_replace_bulk`
 *   - kind:   "art" | "cat"
 *   - action: "delete" | "status" | "move"
 *   - ids[]:  Array von Element-IDs
 *   - clang:  Sprach-ID
 *   - status: int (nur action=status; konkreter Statuswert)
 *   - target_id: int (nur action=move; ziel-Kategorie-ID)
 *   - _csrf_token: CSRF
 *
 * Antwort: JSON mit `ok`, `done` (Anzahl erfolgreich), `failed` (Liste IDs).
 */
class rex_api_structure_replace_bulk extends rex_api_function
{
    /** @var bool */
    protected $published = false;

    public function execute()
    {
        $user = rex::requireUser();

        $kind = rex_post('kind', 'string');
        $action = rex_post('action', 'string');
        $ids = (array) rex_post('ids', 'array', []);
        $clang = rex_post('clang', 'int', rex_clang::getStartId());
        $status = rex_post('status', 'int', -1);
        $targetId = rex_post('target_id', 'int', -1);

        if (!rex_clang::exists($clang)) {
            throw new rex_api_exception('Invalid clang.');
        }
        if (!in_array($kind, ['art', 'cat'], true)) {
            throw new rex_api_exception('Unknown kind.');
        }
        if (!in_array($action, ['delete', 'status', 'move'], true)) {
            throw new rex_api_exception('Unknown action.');
        }

        $cleanIds = [];
        foreach ($ids as $rawId) {
            $cid = (int) $rawId;
            if ($cid > 0) {
                $cleanIds[$cid] = $cid;
            }
        }
        if (count($cleanIds) === 0) {
            throw new rex_api_exception('No ids given.');
        }

        $structurePerm = $user->getComplexPerm('structure');
        $done = 0;
        $failed = [];

        foreach ($cleanIds as $id) {
            try {
                if ('art' === $kind) {
                    $art = rex_article::get($id, $clang);
                    if (!$art) {
                        $failed[] = $id;
                        continue;
                    }
                    if (!$structurePerm->hasCategoryPerm((int) $art->getCategoryId())) {
                        $failed[] = $id;
                        continue;
                    }

                    if ('delete' === $action) {
                        if (!$user->hasPerm('deleteArticle[]')) {
                            $failed[] = $id;
                            continue;
                        }
                        rex_article_service::deleteArticle($id);
                        ++$done;
                        continue;
                    }
                    if ('status' === $action) {
                        if (!$user->hasPerm('publishArticle[]')) {
                            $failed[] = $id;
                            continue;
                        }
                        rex_article_service::articleStatus($id, $clang, $status >= 0 ? $status : null);
                        ++$done;
                        continue;
                    }
                    if ('move' === $action) {
                        if (!$user->hasPerm('editArticle[]')) {
                            $failed[] = $id;
                            continue;
                        }
                        if ($targetId < 0) {
                            $failed[] = $id;
                            continue;
                        }
                        if ($targetId > 0 && !$structurePerm->hasCategoryPerm($targetId)) {
                            $failed[] = $id;
                            continue;
                        }
                        rex_article_service::moveArticle($id, (int) $art->getCategoryId(), $targetId);
                        ++$done;
                        continue;
                    }
                }

                if ('cat' === $kind) {
                    if (!$structurePerm->hasCategoryPerm($id)) {
                        $failed[] = $id;
                        continue;
                    }
                    $cat = rex_category::get($id, $clang);
                    if (!$cat) {
                        $failed[] = $id;
                        continue;
                    }

                    if ('delete' === $action) {
                        if (!$user->hasPerm('deleteCategory[]')) {
                            $failed[] = $id;
                            continue;
                        }
                        rex_category_service::deleteCategory($id);
                        ++$done;
                        continue;
                    }
                    if ('status' === $action) {
                        if (!$user->hasPerm('publishCategory[]')) {
                            $failed[] = $id;
                            continue;
                        }
                        rex_category_service::categoryStatus($id, $clang, $status >= 0 ? $status : null);
                        ++$done;
                        continue;
                    }
                    if ('move' === $action) {
                        if (!$user->hasPerm('editCategory[]')) {
                            $failed[] = $id;
                            continue;
                        }
                        if ($targetId < 0 || $targetId === $id) {
                            $failed[] = $id;
                            continue;
                        }
                        if ($targetId > 0 && !$structurePerm->hasCategoryPerm($targetId)) {
                            $failed[] = $id;
                            continue;
                        }
                        if ($targetId === (int) $cat->getParentId()) {
                            ++$done;
                            continue;
                        }
                        rex_category_service::moveCategory($id, $targetId);
                        ++$done;
                        continue;
                    }
                }
            } catch (rex_exception $e) {
                $failed[] = $id;
            } catch (rex_api_exception $e) {
                $failed[] = $id;
            }
        }

        rex_response::cleanOutputBuffers();
        rex_response::sendJson([
            'ok' => count($failed) === 0,
            'done' => $done,
            'failed' => array_values($failed),
            'message' => rex_i18n::rawMsg('structure_replace_bulk_done', (string) $done),
        ]);
        exit;
    }

    protected function requiresCsrfProtection()
    {
        return true;
    }
}
