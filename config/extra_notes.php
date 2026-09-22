<?php
return [
    'tags' => [
        'study'   => ['label' => 'Study',   'icon' => 'fa-solid fa-book-bookmark'],
        'company' => ['label' => 'Company', 'icon' => 'fa-solid fa-building'],
        'team'    => ['label' => 'Team',    'icon' => 'fa-solid fa-users'],
        'coding'  => ['label' => 'Coding',  'icon' => 'fa-solid fa-code'],
        'ideas'   => ['label' => 'Ideas',   'icon' => 'fa-solid fa-lightbulb'],
        'fun'     => ['label' => 'Fun',     'icon' => 'fa-solid fa-face-laugh-squint'],
    ],

    // At most this many tags per message.
    'max_tags' => 3,

    // How many messages one page of the stream holds
    'page_size' => 50,

    // Upper bound on a bulk selection.
    'max_bulk' => 500,
];
