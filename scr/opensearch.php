<?php

return [
    'client' => [
        'host' => env('OPEN_SEARCH_HOST'),
        'port' => env('OPEN_SEARCH_PORT', '443'),
        'username' => env('OPEN_SEARCH_USERNAME'),
        'password' => env('OPEN_SEARCH_PASSWORD'),
    ],
//    'indices' => [
//        'default' => [
//            'settings' => [
//                'index' => [
//                    'number_of_shards' => 3,
//                ],
//            ],
//        ],
//        'table' => [
//            'mappings' => [
//                'properties' => [
//                    'id' => [
//                        'type' => 'keyword',
//                    ],
//                ],
//            ],
//        ],
//    ],
];
