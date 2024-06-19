<?php

session_start();
if (isset($_COOKIE['Credentials'])) {
    unset($_COOKIE['Credentials']);
    session_destroy();
    setcookie('Credentials', "", time()-3600, '/', "domain", false, false);
    header('Location: login.php');
    exit;
}
