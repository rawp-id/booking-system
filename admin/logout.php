<?php
declare(strict_types=1);
require __DIR__ . '/../config/env.php';
require __DIR__ . '/../src/Auth.php';
use App\Auth;
session_start();
Auth::logout();
header('Location: login.php');
exit;
