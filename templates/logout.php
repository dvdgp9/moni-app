<?php
use Moni\Services\AuthService;
use Moni\Support\Flash;

try {
    AuthService::logout();
    Flash::add('success', 'Sesión cerrada.');
} catch (Throwable $e) {
    error_log('[logout] ' . $e->getMessage());
}
moni_redirect(route_path('login'));
