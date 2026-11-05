<?php

declare(strict_types=1);

/**
 * @param list<array<string, mixed>> $categories
 */
function category_dropdown(array $categories, string $name, ?string $selected = null): void
{
    static $instance = 0;
    $instance++;
    $baseId = 'category-dropdown-' . $instance;
    $selected = $selected ?? '';
    $selectedLabel = 'Tanpa Kategori';
    $selectedColor = '';

    foreach ($categories as $category) {
        if (($category['id'] ?? null) === $selected) {
            $selectedLabel = (string) ($category['name'] ?? '');
            $selectedColor = (string) ($category['color'] ?? '');
            break;
        }
    }
    ?>
    <div class="category-dropdown" data-category-dropdown>
        <span class="form-group__label" id="<?= e($baseId) ?>-label"><?= e(ucfirst(str_replace('_', ' ', $name))) ?></span>
        <button
            type="button"
            class="category-dropdown__trigger"
            aria-haspopup="listbox"
            aria-expanded="false"
            aria-controls="<?= e($baseId) ?>-list"
            aria-labelledby="<?= e($baseId) ?>-label"
            data-dropdown-trigger
        >
            <span class="category-chip category-chip--empty" data-selected-chip data-color="<?= e($selectedColor) ?>"></span>
            <span data-selected-label><?= e($selectedLabel) ?></span>
        </button>
        <ul
            id="<?= e($baseId) ?>-list"
            class="category-dropdown__menu"
            role="listbox"
            tabindex="-1"
            aria-labelledby="<?= e($baseId) ?>-label"
            hidden
            data-dropdown-menu
        >
            <li
                class="category-dropdown__option"
                role="option"
                tabindex="-1"
                aria-selected="<?= $selected === '' ? 'true' : 'false' ?>"
                data-value=""
                data-color=""
            >Tanpa Kategori</li>
            <?php foreach ($categories as $category): ?>
                <?php $categoryId = (string) ($category['id'] ?? ''); ?>
                <li
                    class="category-dropdown__option"
                    role="option"
                    tabindex="-1"
                    aria-selected="<?= $categoryId === $selected ? 'true' : 'false' ?>"
                    data-value="<?= e($categoryId) ?>"
                    data-color="<?= e((string) ($category['color'] ?? '')) ?>"
                ><?= e((string) ($category['name'] ?? '')) ?></li>
            <?php endforeach; ?>
        </ul>
        <input type="hidden" name="<?= e($name) ?>" value="<?= e($selected) ?>" data-dropdown-value>
        <noscript>
            <label for="<?= e($baseId) ?>-fallback"><?= e(ucfirst(str_replace('_', ' ', $name))) ?></label>
            <select id="<?= e($baseId) ?>-fallback" name="<?= e($name) ?>">
                <option value="">Tanpa Kategori</option>
                <?php foreach ($categories as $category): ?>
                    <?php $categoryId = (string) ($category['id'] ?? ''); ?>
                    <option value="<?= e($categoryId) ?>" <?= $categoryId === $selected ? 'selected' : '' ?>><?= e((string) ($category['name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </noscript>
    </div>
    <?php
}
