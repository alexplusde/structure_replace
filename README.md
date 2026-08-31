# structure_replace

Ersetzt die Backend-Seite `?page=structure` (Hauptseite des `structure`-AddOns)
über den offiziellen `PAGES_PREPARED`-Hook und `rex_be_page_main::setPath()`.

## Mechanik

1. `boot.php` registriert `PAGES_PREPARED`.
2. Im EP wird `$pages['structure']->setPath()` auf
   `pages/structure_replace.php` umgebogen.
3. `rex_be_controller::includeCurrentPage()` includet ausschließlich diesen
   Pfad – das Original `addons/structure/pages/index.php` wird **nicht** mehr
   automatisch aufgerufen.

## Standard-Verhalten

`pages/structure_replace.php` inkludiert das Original explizit per `require`,
damit:

- alle Original-APIs (`rex_api_category_*`, `rex_api_article_*`,
  `rex_category_service`, `rex_article_service`, `rex_structure_context`,
  Pagination, Permissions) ohne Re-Implementation funktionieren,
- die Metainfo-Extension-Points (`CAT_FORM_BUTTONS`, `CAT_FORM_ADD`,
  `CAT_FORM_EDIT`, `ART_FORM_BUTTONS`, `ART_FORM_ADD`, `ART_FORM_EDIT`,
  `CAT_ADDED`, `CAT_UPDATED`, `ART_ADDED`, `ART_UPDATED`) automatisch
  korrekt feuern,
- Subpages (`structure/content`, `structure/linkmap`, `structure/history`,
  `structure/version`) unverändert weiterlaufen.

Vor dem Original wird `_advanced_actions.php` inkludiert, das bei
installiertem `structure_manager`-AddOn eine zusätzliche Aktionssektion mit
Modal-Buttons (IFrame auf `structure_manager/category` bzw.
`structure_manager/article`) rendert.

## Vollständiger Rewrite

Wenn das Original-Markup komplett ersetzt werden soll, das `require` in
`pages/structure_replace.php` durch eigene Logik ersetzen.

**Pflicht** für Metainfo-Kompatibilität: alle der oben genannten
Extension-Points mit identischen Parametern (`id`, `clang`, `category` /
`article`, `data_colspan`, ...) emittieren – sonst greifen die in
`addons/metainfo/lib/handler/{category,article}_handler.php` registrierten
Handler nicht.

Vorlage: `addons/structure/pages/index.php`. Wiederverwendung der
`rex_api_*`-Klassen via `getHiddenFields()` / `getUrlParams()` ist Pflicht,
damit `CAT_ADDED`/`CAT_UPDATED`/`ART_ADDED`/`ART_UPDATED` weiter ausgelöst
werden.

## Deaktivieren

AddOn deaktivieren – das Original `structure` greift sofort wieder.

## Branch-Workflow

- `main` ist geschützt. Direkte Pushes sind nicht möglich, Änderungen laufen ausschließlich
  über Pull Requests.
- Für jede Änderung einen Feature-Branch von `main` anlegen, zum Beispiel
  `feature/neue-option`.
- Der Feature-Branch wird per Pull Request nach `main` gemerged.
