<?php
use Moni\Services\AuthService;

AuthService::logout();
header('Location: ' . route_path('login'));
exit;
