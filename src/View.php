<?php
declare(strict_types=1);

namespace Devithor;

/**
 * Tiny PHP-template renderer. Pass `data` keys become local variables inside
 * the template via `extract`. Templates live in src/Views/ and use plain PHP
 * — no compile step, no template language to learn.
 *
 *   View::render('admin/dashboard', ['title' => 'Dashboard'])
 */
final class View
{
    public static function render(string $template, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include __DIR__ . '/Views/' . $template . '.php';
        return ob_get_clean() ?: '';
    }

    /** HTML-escape helper. Use everywhere user-supplied or DB-sourced content lands in markup. */
    public static function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
