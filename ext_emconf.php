<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Support extension for IDE integration',
    'description' => 'Provides information for use with IDE tools',
    'category' => 'plugin',
    'author' => 'Manfred Egger',
    'author_email' => '',
    'state' => 'stable',
    'clearCacheOnLoad' => false,
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '11.5.0-12.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
