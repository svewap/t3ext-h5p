<?php
return [
    'frontend' => [
        'michielroos/h5p-route-resolver' => [
            'target' => \MichielRoos\H5p\Middleware\H5pRouteResolver::class,
            'after' => [
                'typo3/cms-frontend/maintenance-mode'
            ],
            'before' => [
                'typo3/cms-frontend/base-redirect-resolver',
                'typo3/cms-frontend/static-route-resolver',
            ]
        ],
    ]
];
