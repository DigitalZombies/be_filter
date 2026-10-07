<?php

use DigitalZombies\BeFilter\Controller\FilterController;

return [
    'be_filter_options' => [
        'path' => '/be-filter/options',
        'target' => FilterController::class . '::optionsAction',
    ],
    'be_filter_apply' => [
        'path' => '/be-filter/apply',
        'methods' => ['POST'],
        'target' => FilterController::class . '::applyAction',
    ],
    'be_filter_layout' => [
        'path' => '/be-filter/layout',
        'methods' => ['POST'],
        'target' => FilterController::class . '::layoutAction',
    ],
    'be_filter_presets' => [
        'path' => '/be-filter/presets',
        'target' => FilterController::class . '::presetsAction',
    ],
    'be_filter_preset_save' => [
        'path' => '/be-filter/preset/save',
        'methods' => ['POST'],
        'target' => FilterController::class . '::savePresetAction',
    ],
    'be_filter_preset_delete' => [
        'path' => '/be-filter/preset/delete',
        'methods' => ['POST'],
        'target' => FilterController::class . '::deletePresetAction',
    ],
    'be_filter_columns' => [
        'path' => '/be-filter/columns',
        'methods' => ['POST'],
        'target' => FilterController::class . '::columnsAction',
    ],
];
