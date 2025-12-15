<?php
declare(strict_types=1);

/**
 * Wrapper para soportar: /index.php?r=/news
 * Convierte r en la ruta real y luego carga Slim (public/index.php)
 */
if (isset($_GET['r'])) {
    $r = (string)$_GET['r'];
    if ($r === '') $r = '/';
    if ($r[0] !== '/') $r = '/' . $r;

    // quitar r del query
    unset($_GET['r']);
    $query = http_build_query($_GET);

    $_SERVER['REQUEST_URI']  = $r . ($query ? ('?' . $query) : '');
    $_SERVER['QUERY_STRING'] = $query;
    $_SERVER['PATH_INFO']    = $r;

    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF']    = '/index.php' . $r;
}

require __DIR__ . '/public/index.php';
