<?php

// projects.root_path is stored RELATIVE. These prefixes turn it into a real path:
// container_root for reading from disk inside Sail, wsl_root for the editor link and copy-path.
// container_root is the read-only bind mount in compose.yaml (laravel.test → volumes).

return [
    'container_root' => '/projects-root',
    'wsl_root' => '/home/baradox/projects',
    'wsl_distro' => 'Ubuntu',

    'ignore' => [
        // version control
        '.git', '.svn', '.hg',
        // dependencies
        'node_modules', 'vendor', 'bower_components',
        // python
        '__pycache__', '.venv', 'venv', 'env', '.tox',
        '.pytest_cache', '.mypy_cache', '.ipynb_checkpoints',
        // build output
        'dist', 'build', 'out', 'target', 'bin', 'obj', '.next', '.nuxt',
        // laravel/php
        'storage/framework', 'bootstrap/cache', '.phpunit.cache',
        // caches & coverage
        '.cache', '.parcel-cache', '.turbo', 'coverage',
        // editor & os
        '.idea', '.vs', '.DS_Store', 'Thumbs.db',
    ],

    'ignore_extensions' => ['pyc', 'o', 'class', 'exe', 'dll', 'so', 'log', 'swp'],
];
