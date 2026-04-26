<?php

/**
 * Helfer für die moderne Struktur-Verwaltung.
 *
 * @internal
 */
final class rex_structure_replace_helper
{
    /** @return list<rex_category> */
    public static function getRootCategoriesForUser(int $clangId): array
    {
        $user = rex::requireUser();
        $structurePerm = $user->getComplexPerm('structure');

        if ($structurePerm->hasMountpoints()) {
            return $structurePerm->getMountpointCategories();
        }

        return rex_category::getRootCategories(false, $clangId);
    }

    /**
     * Liefert die zur Anzeige nötige Kategorie-Hierarchie als verschachteltes
     * Array. Pfad zur aktuellen Kategorie wird mit `expanded => true` markiert.
     *
     * @return list<array{id:int, name:string, status:int, priority:int, hasChildren:bool, expanded:bool, current:bool, children:array}>
     */
    public static function buildTree(int $clangId, int $currentCategoryId): array
    {
        $expandedPath = self::getPathIds($currentCategoryId, $clangId);
        $rootCats = self::getRootCategoriesForUser($clangId);

        // Auto-Expand: ohne aktive Kategorie wird der erste Wurzel-Ordner automatisch
        // aufgeklappt (nur "öffnen", nicht aktiv setzen). Wenn nur eine Wurzel
        // existiert, wird diese zudem immer aufgeklappt.
        $autoExpandFirstRoot = $currentCategoryId <= 0 || count($rootCats) === 1;
        $first = true;

        $build = static function (array $categories, int $depth) use (&$build, $expandedPath, $currentCategoryId): array {
            $items = [];
            foreach ($categories as $cat) {
                $id = $cat->getId();
                $children = $cat->getChildren(false);
                $expanded = isset($expandedPath[$id]);
                $items[] = [
                    'id' => $id,
                    'name' => $cat->getName(),
                    'status' => (int) ($cat->isOnline() ? 1 : 0),
                    'priority' => $cat->getPriority(),
                    'hasChildren' => count($children) > 0,
                    'expanded' => $expanded,
                    'current' => $id === $currentCategoryId,
                    'children' => count($children) > 0 ? $build($children, $depth + 1) : [],
                ];
            }
            return $items;
        };

        $rootItems = [];
        foreach ($rootCats as $cat) {
            $id = $cat->getId();
            $children = $cat->getChildren(false);
            $expanded = isset($expandedPath[$id]) || ($autoExpandFirstRoot && $first);
            $first = false;
            $rootItems[] = [
                'id' => $id,
                'name' => $cat->getName(),
                'status' => (int) ($cat->isOnline() ? 1 : 0),
                'priority' => $cat->getPriority(),
                'hasChildren' => count($children) > 0,
                'expanded' => $expanded,
                'current' => $id === $currentCategoryId,
                'children' => count($children) > 0 ? $build($children, 1) : [],
            ];
        }

        return $rootItems;
    }

    /** @return array<int, true> */
    private static function getPathIds(int $categoryId, ?int $clangId = null): array
    {
        $ids = [];
        $cat = rex_category::get($categoryId, $clangId);
        while ($cat) {
            $ids[$cat->getId()] = true;
            $cat = $cat->getParent();
        }
        return $ids;
    }
}
