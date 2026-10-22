<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    ...(is_file(__DIR__.'/local-providers.php') ? require __DIR__.'/local-providers.php' : []),
];
