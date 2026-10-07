<?php

declare(strict_types=1);

$language = 'LLL:EXT:be_filter/Resources/Private/Language/locallang.xlf:';

return [
    'ctrl' => [
        'title' => $language . 'preset',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'default_sortby' => 'title',
        'searchFields' => 'title,table_name',
        'enablecolumns' => ['disabled' => 'hidden'],
        'rootLevel' => 1,
        'security' => ['ignoreRootLevelRestriction' => true],
        // Saved filters are managed in the filter bar, not in a record list.
        'hideTable' => true,
        'typeicon_classes' => ['default' => 'actions-bookmark'],
    ],
    'types' => [
        '1' => ['showitem' => 'title, table_name, be_user, visibility, be_groups, filter_values, visible_filters, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access, hidden'],
    ],
    'columns' => [
        'hidden' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.visible',
            'config' => ['type' => 'check', 'renderType' => 'checkboxToggle', 'items' => [['label' => '', 'invertStateDisplay' => true]]],
        ],
        'title' => [
            'label' => $language . 'presets.name',
            'config' => ['type' => 'input', 'size' => 30, 'max' => 255, 'eval' => 'trim', 'required' => true],
        ],
        'table_name' => [
            'label' => $language . 'preset.table',
            'config' => ['type' => 'input', 'readOnly' => true],
        ],
        'be_user' => [
            'label' => $language . 'presets.owner',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'be_users',
                'readOnly' => true,
                'default' => 0,
            ],
        ],
        'visibility' => [
            'label' => $language . 'presets.visibility',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => $language . 'visibility.private', 'value' => 'private'],
                    ['label' => $language . 'visibility.groups', 'value' => 'groups'],
                    ['label' => $language . 'visibility.public', 'value' => 'public'],
                ],
                'default' => 'private',
            ],
        ],
        'be_groups' => [
            'label' => $language . 'presets.groups',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'foreign_table' => 'be_groups',
                'foreign_table_where' => 'ORDER BY be_groups.title',
                'size' => 5,
                'maxitems' => 99,
            ],
        ],
        'filter_values' => [
            'label' => $language . 'preset.values',
            'config' => ['type' => 'text', 'readOnly' => true, 'rows' => 5],
        ],
        'visible_filters' => [
            'label' => $language . 'preset.visibleFilters',
            'config' => ['type' => 'text', 'readOnly' => true, 'rows' => 3],
        ],
    ],
];
