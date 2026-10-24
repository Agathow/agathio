<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('/user/login.php');
    }
}

function require_staff(): void
{
    $role = $_SESSION['user']['role'] ?? null;

    if (!is_logged_in() || !in_array($role, ['admin', 'inspector'], true)) {
        redirect('/user/login.php');
    }
}

function is_admin(): bool
{
    return is_logged_in()
        && ($_SESSION['user']['role'] ?? null) === 'admin';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }

    return $_SESSION['csrf'];
}

function check_csrf(): void
{
    $sessionToken = $_SESSION['csrf'] ?? '';
    $submittedToken = $_POST['csrf'] ?? '';

    if (
        !is_string($sessionToken)
        || !is_string($submittedToken)
        || $sessionToken === ''
        || !hash_equals($sessionToken, $submittedToken)
    ) {
        http_response_code(419);
        exit('Invalid CSRF token');
    }
}

function risk_badge(string $risk): string
{
    $riskLevels = [
        'low' => ['Low', 'low'],
        'moderate' => ['Moderate', 'moderate'],
        'elevated' => ['Elevated', 'elevated'],
        'high' => ['High', 'high'],
    ];

    [$label, $class] = $riskLevels[strtolower($risk)]
        ?? ['Unknown', 'moderate'];

    return sprintf(
        '<span class="badge badge-%s">%s</span>',
        e($class),
        e($label)
    );
}

function status_label(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

function rupiah($amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = [$type, $message];
}

function get_flash(): ?array
{
    $flashMessage = $_SESSION['flash'] ?? null;

    unset($_SESSION['flash']);

    return $flashMessage;
}