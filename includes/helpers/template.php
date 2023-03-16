<?php

use function App\sage;

function include_wc_view($path, $data = null)
{
    $plugin_root = plugin_dir_path(__DIR__);
    $full_path = $plugin_root . 'resources/views/' . str_replace('.', '/', $path) . '.blade.php';

    if (empty($data)) {
        return sage('blade')->render($full_path);
    }

    return sage('blade')->render($full_path, $data);
}
