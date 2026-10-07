<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Backend Filter',
    'description' => 'TCA defined filters, saved filter presets and drag and drop column configuration for the TYPO3 record list.',
    'category' => 'be',
    'author' => 'Digital Zombies',
    'author_email' => 'andras.otto@digital-zombies.com',
    'state' => 'stable',
    'license' => 'GPL-2.0-or-later',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
