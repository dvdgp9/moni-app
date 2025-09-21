<?php
use Moni\Services\AuthService;

AuthService::logout();
header('Location: /?page=login');
exit;
