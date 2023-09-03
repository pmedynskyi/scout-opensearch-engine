<?php

return [
    'client' => [
        'hosts' => [env('OPEN_SEARCH_HOST') . ':443'],
        'BasicAuthentication' => [
            env('OPEN_SEARCH_USERNAME'),
            env('OPEN_SEARCH_PASSWORD'),
        ],
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
