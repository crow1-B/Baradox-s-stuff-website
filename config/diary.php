<?php

// The mood vocabulary is a closed set, like Extra Notes' tags: keys are what gets stored in
// diary_entries.mood, and removing one stops it rendering without rewriting any rows.
// Each key's colour lives in components.css ([data-mood]); add one there with a new key.

return [
    'moods' => [
        'radiant' => ['label' => 'Radiant', 'icon' => 'fa-sun'],
        'content' => ['label' => 'Content', 'icon' => 'fa-face-smile'],
        'calm' => ['label' => 'Calm', 'icon' => 'fa-leaf'],
        'tired' => ['label' => 'Tired', 'icon' => 'fa-moon'],
        'low' => ['label' => 'Low', 'icon' => 'fa-cloud'],
        'stormy' => ['label' => 'Stormy', 'icon' => 'fa-cloud-bolt'],
    ],
];
