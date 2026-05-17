<?php

/**
 * Hauptbereich: Startartikel-Card + sortierbare Artikel-Tabelle.
 *
 * Spalten-Sortierung via URL-Param `sort` (name|priority|updatedate|createdate)
 * und `sort_dir` (asc|desc). Drag&Drop ist nur bei sort=priority+asc aktiv.
 */

/** @var rex_structure_context $structureContext */
/** @var rex_user $user */
/** @var array<string, bool> $perms */
/** @var array<int, array{0:string,1:string,2:string}> $artStatusTypes */
/** @var int $clang */
/** @var int $categoryId */
/** @var int $articleId */
/** @var rex_context $ctxUrl */

if (!$perms['hasCatPerm'] && $categoryId > 0) {
    echo rex_view::warning(rex_i18n::msg('haveNoPermissionsForArea'));
    return;
}

$contentAvailable = rex_addon::get('structure')->getPlugin('content')->isAvailable();

/** @var array<int,string> $templateNames */
$templateNames = [];
if ($contentAvailable) {
    foreach (rex_template::getTemplatesForCategory($categoryId) as $tid => $tname) {
        $templateNames[(int) $tid] = $tname;
    }
    $templateNames[0] = rex_i18n::msg('template_default_name');
}

// Sortierung
$validSorts = ['priority', 'name', 'updatedate', 'createdate', 'status'];
$sort = rex_request('sort', 'string', 'priority');
if (!in_array($sort, $validSorts, true)) {
    $sort = 'priority';
}
$sortDir = strtolower(rex_request('sort_dir', 'string', 'asc')) === 'desc' ? 'desc' : 'asc';
$dndActive = $sort === 'priority' && $sortDir === 'asc';

// Daten laden: Startartikel + reguläre Artikel
$startArticle = null;
$articles = [];
$currentCat = $categoryId > 0 ? rex_category::get($categoryId, $clang) : null;

if ($currentCat) {
    $startArticle = rex_article::get($categoryId, $clang);
    foreach ($currentCat->getArticles(false) as $art) {
        if ($art->getId() === $categoryId) {
            continue;
        }
        $articles[] = $art;
    }
} elseif ($categoryId === 0 && !$user->getComplexPerm('structure')->hasMountpoints()) {
    $sql = rex_sql::factory();
    $sql->setQuery('SELECT id FROM ' . rex::getTable('article') . ' WHERE parent_id=0 AND startarticle=0 AND clang_id=? ORDER BY priority, name', [$clang]);
    foreach ($sql->getArray() as $row) {
        $a = rex_article::get((int) $row['id'], $clang);
        if ($a) {
            $articles[] = $a;
        }
    }
}

// Sortieren
usort($articles, static function (rex_article $a, rex_article $b) use ($sort, $sortDir): int {
    $cmp = match ($sort) {
        'name'       => strcasecmp($a->getName(), $b->getName()),
        'updatedate' => (int) $a->getValue('updatedate') <=> (int) $b->getValue('updatedate'),
        'createdate' => (int) $a->getValue('createdate') <=> (int) $b->getValue('createdate'),
        'status'     => (int) $a->getValue('status') <=> (int) $b->getValue('status'),
        default      => $a->getPriority() <=> $b->getPriority(),
    };
    if ($cmp === 0) {
        $cmp = strcasecmp($a->getName(), $b->getName());
    }
    return $sortDir === 'desc' ? -$cmp : $cmp;
});

/**
 * Erzeugt einen Sort-Header-Link (Bootstrap-Icon + Toggle ASC/DESC).
 */
$sortHeader = static function (string $key, string $label) use ($sort, $sortDir, $ctxUrl): string {
    $newDir = ($sort === $key && $sortDir === 'asc') ? 'desc' : 'asc';
    $url = $ctxUrl->getUrl(['sort' => $key, 'sort_dir' => $newDir], false);
    $icon = '';
    if ($sort === $key) {
        $icon = $sortDir === 'asc'
            ? ' <i class="rex-icon fa-arrow-up-short-wide ms-1" aria-hidden="true"></i>'
            : ' <i class="rex-icon fa-arrow-down-wide-short ms-1" aria-hidden="true"></i>';
    } else {
        $icon = ' <i class="rex-icon fa-sort ms-1 text-muted" aria-hidden="true"></i>';
    }
    return '<a class="rex-sr-sort-link text-decoration-none" href="' . rex_escape($url) . '">' . rex_escape($label) . $icon . '</a>';
};

// =================== BREADCRUMB ===================
echo rex_structure_replace_breadcrumb::render($categoryId, $articleId, $clang);

// =================== STARTARTIKEL-CARD ===================
?>
<div class="card rex-sr-card mb-3 rex-sr-startcard" data-sortable="startart"
	data-category-id="<?= $categoryId ?>">
	<div class="card-body">
		<?php if ($startArticle):
		    $saStatus = (int) $startArticle->getValue('status');
		    $saType = $artStatusTypes[$saStatus] ?? $artStatusTypes[1];
		    $saIcon = $startArticle->getId() === rex_article::getSiteStartArticleId() ? 'rex-icon-sitestartarticle' : 'rex-icon-startarticle';
		    $saEditUrl = rex_url::backendPage('content/edit', ['article_id' => $startArticle->getId(), 'clang' => $clang, 'mode' => 'edit'], false);
		    ?>
		<div class="d-flex align-items-center gap-3 rex-sr-startarticle-row is-startarticle"
			data-art-id="<?= $startArticle->getId() ?>"
			data-priority="<?= (int) $startArticle->getPriority() ?>"
			data-clang="<?= $clang ?>">
			<i
				class="rex-icon <?= $saIcon ?> fa-2x text-warning"></i>
			<div class="flex-grow-1">
				<div class="text-muted small d-flex align-items-center gap-2">
					<span><?= rex_i18n::msg('structure_replace_startarticle') ?></span>
					<span
						class="badge text-bg-light text-muted">#<?= $startArticle->getId() ?></span>
					<span class="<?= rex_escape($saType[1]) ?>"><i
							class="rex-icon <?= rex_escape($saType[2]) ?>"></i>
						<?= rex_escape($saType[0]) ?></span>
				</div>
				<?php if ($perms['editArt'] && $perms['hasCatPerm']): ?>
				<input type="text" class="form-control form-control-lg rex-sr-inline" data-sr-inline="art"
					data-sr-field="name"
					data-sr-id="<?= $startArticle->getId() ?>"
					value="<?= rex_escape($startArticle->getName()) ?>"
					maxlength="255" />
				<?php else: ?>
				<strong><?= rex_escape($startArticle->getName()) ?></strong>
				<?php endif; ?>
			</div>
			<div class="d-flex gap-2 align-items-center">
				<?php if ($perms['editArt'] && $perms['hasCatPerm']): ?>
				<input type="number" min="1" step="1"
					class="form-control form-control-sm rex-sr-inline rex-sr-priority-input" data-sr-inline="art"
					data-sr-field="priority"
					data-sr-id="<?= $startArticle->getId() ?>"
					value="<?= (int) $startArticle->getPriority() ?>"
					title="<?= rex_i18n::msg('header_priority') ?>"
					aria-label="<?= rex_i18n::msg('header_priority') ?>" />
				<?php endif; ?>
				<?php if ($contentAvailable && $perms['editArt'] && $perms['hasCatPerm']): ?>
				<select class="form-select form-select-sm rex-sr-inline rex-sr-template-select" data-sr-inline="art"
					data-sr-field="template_id"
					data-sr-id="<?= $startArticle->getId() ?>">
					<?php foreach ($templateNames as $tid => $tname): ?>
					<option value="<?= (int) $tid ?>" <?= $startArticle->getTemplateId() === (int) $tid ? 'selected' : '' ?>><?= rex_escape($tname) ?>
					</option>
					<?php endforeach; ?>
				</select>
				<?php endif; ?>
				<a class="btn btn-sm btn-primary"
					href="<?= rex_escape($saEditUrl) ?>"><i
						class="rex-icon rex-icon-edit-mode"></i>
					<?= rex_i18n::msg('structure_replace_edit_content') ?></a>
			</div>
		</div>
		<?php elseif ($categoryId > 0): ?>
		<em
			class="text-muted"><?= rex_i18n::msg('structure_replace_no_articles') ?></em>
		<?php endif; ?>
	</div>
</div>

<?php
// =================== ARTIKEL-TABELLE ===================
?>
<div class="rex-sr-pane">
	<div class="rex-sr-pane-header">
		<strong class="me-auto"><i class="rex-icon rex-icon-article"></i>
			<?= rex_i18n::msg('structure_replace_articles') ?></strong>
		<div class="rex-sr-bulkbar d-none align-items-center gap-2 me-2" data-rex-sr-bulkbar>
			<span class="text-muted small" data-rex-sr-bulkcount>0</span>
			<button type="button" class="btn btn-sm btn-link" data-rex-sr-bulk="clear"
				title="<?= rex_i18n::msg('structure_replace_bulk_clear') ?>"><i
					class="rex-icon fa-xmark"></i></button>
			<?php if ($perms['publishArt'] && $perms['hasCatPerm']): ?>
			<div class="dropdown">
				<button type="button" class="btn btn-sm btn-secondary dropdown-toggle" data-bs-toggle="dropdown"
					aria-expanded="false">
					<i class="rex-icon fa-toggle-on"></i>
					<?= rex_i18n::msg('structure_replace_bulk_status') ?>
				</button>
				<ul class="dropdown-menu dropdown-menu-end">
					<?php foreach ($artStatusTypes as $sKey => $sType): ?>
					<li><button type="button"
							class="dropdown-item <?= rex_escape($sType[1]) ?>"
							data-rex-sr-bulk="status"
							data-rex-sr-status="<?= (int) $sKey ?>">
							<i
								class="rex-icon <?= rex_escape($sType[2]) ?>"></i>
							<?= rex_escape($sType[0]) ?>
						</button></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php endif; ?>
			<?php if ($perms['deleteArt'] && $perms['hasCatPerm']): ?>
			<button type="button" class="btn btn-sm btn-outline-danger" data-rex-sr-bulk="delete">
				<i class="rex-icon rex-icon-delete"></i>
				<?= rex_i18n::msg('structure_replace_bulk_delete') ?>
			</button>
			<?php endif; ?>
		</div>
		<?php if (!$dndActive): ?>
		<a class="btn btn-sm btn-link"
			href="<?= rex_escape($ctxUrl->getUrl(['sort' => 'priority', 'sort_dir' => 'asc'], false)) ?>"
			title="<?= rex_i18n::msg('structure_replace_reset_sort') ?>"><i
				class="rex-icon fa-arrows-up-down"></i>
			<?= rex_i18n::msg('structure_replace_enable_dnd') ?></a>
		<?php endif; ?>
		<?php if ($perms['addArt'] && $perms['hasCatPerm'] && $categoryId > 0): ?>
		<a class="btn btn-sm btn-secondary"
			href="<?= rex_escape($ctxUrl->getUrl(['function' => 'add_art'], false)) ?>"><i
				class="rex-icon rex-icon-add-article"></i>
			<?= rex_i18n::msg('article_add') ?></a>
		<?php endif; ?>
	</div>

	<div class="rex-sr-pane-body p-0">
		<div class="card rex-sr-card">
			<div class="table-responsive">
				<table class="table table-hover align-middle mb-0 rex-sr-art-table"
					data-dnd-active="<?= $dndActive ? '1' : '0' ?>">
					<thead>
						<tr>
							<th class="rex-sr-col-handle" scope="col" aria-label=""></th>
							<th class="rex-sr-col-select" scope="col">
								<input type="checkbox" class="form-check-input" data-rex-sr-bulk-toggle-all
									aria-label="<?= rex_i18n::msg('structure_replace_select_all') ?>" />
							</th>
							<th class="rex-sr-col-id" scope="col">ID</th>
							<th class="rex-sr-col-name" scope="col">
								<?= $sortHeader('name', rex_i18n::msg('header_article_name')) ?>
							</th>
							<?php if ($contentAvailable): ?>
							<th class="rex-sr-col-template" scope="col">
								<?= rex_i18n::msg('header_template') ?>
							</th>
							<?php endif; ?>
							<th class="rex-sr-col-status" scope="col">
								<?= $sortHeader('status', rex_i18n::msg('header_status')) ?>
							</th>
							<th class="rex-sr-col-prio" scope="col">
								<?= $sortHeader('priority', rex_i18n::msg('header_priority')) ?>
							</th>
							<th class="rex-sr-col-updated" scope="col">
								<?= $sortHeader('updatedate', rex_i18n::msg('updated_on')) ?>
							</th>
							<th class="rex-sr-col-actions" scope="col" aria-label=""></th>
						</tr>
					</thead>
					<tbody data-sortable="articles"
						data-category-id="<?= $categoryId ?>">
						<?php
            if (count($articles) === 0): ?>
						<tr>
							<td colspan="<?= $contentAvailable ? 9 : 8 ?>"
								class="text-muted text-center py-3">
								<?= rex_i18n::msg('structure_replace_no_articles') ?>
							</td>
						</tr>
						<?php else:
						    foreach ($articles as $art):
						        $id = $art->getId();
						        $status = (int) $art->getValue('status');
						        $type = $artStatusTypes[$status] ?? $artStatusTypes[1];
						        $editUrl = rex_url::backendPage('content/edit', ['article_id' => $id, 'clang' => $clang, 'mode' => 'edit'], false);
						        $delUrl = $ctxUrl->getUrl(['article_id' => $id] + rex_api_article_delete::getUrlParams(), false);
						        $movable = $dndActive && $perms['editArt'] && $perms['hasCatPerm'];
						        $canEdit = $perms['editArt'] && $perms['hasCatPerm'];
						        $iconClass = $id === rex_article::getSiteStartArticleId() ? 'rex-icon-sitestartarticle' : 'rex-icon-article';
						        $updated = (int) $art->getValue('updatedate');
						        ?>
						<tr class="rex-sr-art-row"
							data-art-id="<?= $id ?>"
							data-priority="<?= (int) $art->getPriority() ?>"
							data-clang="<?= $clang ?>">
							<td class="rex-sr-col-handle">
								<?php if ($movable): ?>
								<span class="rex-sr-handle" aria-hidden="true"><i
										class="rex-icon fa-grip-vertical"></i></span>
								<?php else: ?>
								<span class="rex-sr-handle is-disabled" aria-hidden="true"></span>
								<?php endif; ?>
							</td>
							<td class="rex-sr-col-select">
								<?php if ($canEdit || $perms['deleteArt']): ?>
								<input type="checkbox" class="form-check-input" data-rex-sr-bulk-row
									data-art-id="<?= $id ?>"
									aria-label="<?= rex_i18n::msg('structure_replace_select') ?>" />
								<?php endif; ?>
							</td>
							<td class="rex-sr-col-id"><span
									class="badge text-bg-light text-muted">#<?= $id ?></span>
							</td>
							<td class="rex-sr-col-name">
								<?php if ($canEdit): ?>
								<input type="text" class="form-control form-control-sm rex-sr-inline"
									data-sr-inline="art" data-sr-field="name"
									data-sr-id="<?= $id ?>"
									value="<?= rex_escape($art->getName()) ?>"
									maxlength="255" />
								<?php else: ?>
								<a class="rex-link-expanded"
									href="<?= rex_escape($editUrl) ?>"><?= rex_escape($art->getName()) ?></a>
								<?php endif; ?>
							</td>
							<?php if ($contentAvailable): ?>
							<td class="rex-sr-col-template">
								<?php if ($canEdit): ?>
								<select class="form-select form-select-sm rex-sr-inline rex-sr-template-select"
									data-sr-inline="art" data-sr-field="template_id"
									data-sr-id="<?= $id ?>">
									<?php foreach ($templateNames as $tid => $tname): ?>
									<option value="<?= (int) $tid ?>"
										<?= $art->getTemplateId() === (int) $tid ? 'selected' : '' ?>><?= rex_escape($tname) ?>
									</option>
									<?php endforeach; ?>
								</select>
								<?php else: ?>
								<span
									class="text-muted small"><?= rex_escape($templateNames[(int) $art->getValue('template_id')] ?? '') ?></span>
								<?php endif; ?>
							</td>
							<?php endif; ?>
							<td class="rex-sr-col-status">
								<?php if ($perms['publishArt'] && $perms['hasCatPerm']): ?>
								<div class="dropdown">
									<button
										class="btn btn-sm btn-link dropdown-toggle <?= rex_escape($type[1]) ?>"
										type="button" data-bs-toggle="dropdown" aria-expanded="false"
										title="<?= rex_escape($type[0]) ?>"><i
											class="rex-icon <?= rex_escape($type[2]) ?>"
											aria-hidden="true"></i> <span
											class="rex-sr-status-label"><?= rex_escape($type[0]) ?></span></button>
									<ul class="dropdown-menu dropdown-menu-end">
										<?php foreach ($artStatusTypes as $key => $t):
										    $url = $ctxUrl->getUrl(['article_id' => $id, 'art_status' => $key] + rex_api_article_status::getUrlParams(), false);
										    ?>
										<li><a class="dropdown-item <?= rex_escape($t[1]) ?>"
												href="<?= rex_escape($url) ?>"><i
													class="rex-icon <?= rex_escape($t[2]) ?>"></i>
												<?= rex_escape($t[0]) ?></a>
										</li>
										<?php endforeach; ?>
									</ul>
								</div>
								<?php else: ?>
								<span
									class="<?= rex_escape($type[1]) ?>"
									title="<?= rex_escape($type[0]) ?>"><i
										class="rex-icon <?= rex_escape($type[2]) ?>"
										aria-hidden="true"></i> <span
										class="rex-sr-status-label"><?= rex_escape($type[0]) ?></span></span>
								<?php endif; ?>
							</td>
							<td class="rex-sr-col-prio text-muted">
								<?= (int) $art->getPriority() ?>
							</td>
							<td class="rex-sr-col-updated text-muted small">
								<?= $updated > 0 ? rex_escape(rex_formatter::strftime($updated, 'date')) : '–' ?>
							</td>
							<td class="rex-sr-col-actions text-end">
								<div class="dropdown">
									<button type="button" class="btn btn-sm btn-secondary dropdown-toggle"
										data-bs-toggle="dropdown" aria-expanded="false"
										aria-label="<?= rex_i18n::msg('structure_replace_actions') ?>"
										title="<?= rex_i18n::msg('structure_replace_actions') ?>">
										<i class="rex-icon fa-ellipsis-vertical" aria-hidden="true"></i>
									</button>
									<ul class="dropdown-menu dropdown-menu-end">
										<li><a class="dropdown-item"
												href="<?= rex_escape($editUrl) ?>"><i
													class="rex-icon rex-icon-edit-mode"></i>
												<?= rex_i18n::msg('structure_replace_edit_content') ?></a>
										</li>
										<?php if ($perms['deleteArt'] && $perms['hasCatPerm']): ?>
										<li>
											<hr class="dropdown-divider">
										</li>
										<li><a class="dropdown-item text-danger"
												href="<?= rex_escape($delUrl) ?>"
												data-confirm="<?= rex_i18n::msg('structure_delete_all_clangs') ?>"><i
													class="rex-icon rex-icon-delete"></i>
												<?= rex_i18n::msg('delete') ?></a>
										</li>
										<?php endif; ?>
									</ul>
								</div>
							</td>
						</tr>
						<?php
						    endforeach;
endif;
?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>