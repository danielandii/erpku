<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Edit to point to the correct path for your OpenAPI spec
    |--------------------------------------------------------------------------
    */
    'default' => 'default',

    'documentations' => [
        'default' => [
            'api' => [
                'title' => 'NexERP Modular API',
            ],

            'routes' => [
                /*
                 * Route for accessing api documentation interface
                 */
                'api' => 'api/documentation',
            ],
            'paths' => [
                /*
                 * Edit to include full URL in ui and change the base path if needed.
                 */
                'use_absolute_path' => env('L5_SWAGGER_USE_ABSOLUTE_PATH', true),

                /*
                 * Path to export view for (json, yaml)
                 */
                'docs_json' => 'api-docs.json',
                'docs_yaml' => 'api-docs.yaml',

                /*
                  * Set this to `true` in development mode so that docs would
                  * get regenerated on each request. Set to `false` to disable
                  * in production environments.
                  */
                'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', false),

                /*
                 * Set this to `true` to generate a copy of documentation always
                 * when application is in debug mode
                 */
                'generate_yaml_copy' => env('L5_SWAGGER_GENERATE_YAML_COPY', false),

                /*
                 * Absolute path to directory where to export swagger
                 */
                'docs' => storage_path('api-docs'),

                /*
                 * Absolute path to the base directory of your server
                 */
                'base' => env('L5_SWAGGER_BASE_PATH', null),

                /*
                 * Absolute paths to directory containing the swagger annotations are stored.
                 */
                'annotations' => [
                    base_path('app'),
                ],
            ],
        ],
    ],

    'defaults' => [
        'routes' => [
            /*
             * Route for accessing parsed swagger annotations.
             */
            'docs' => 'docs',

            /*
             * Route for Oauth2 authentication callback.
             */
            'oauth2_callback' => 'api/oauth2-callback',

            /*
             * Middleware allows to prevent unexpected access to API documentation
             */
            'middleware' => [
                'api'  => [],
                'asset'=> [],
                'docs' => [],
                'oauth2_callback' => [],
            ],

            /*
             * Route Group options
             */
            'group_options' => [],
        ],

        'paths' => [
            /*
             * Absolute path to the view file to be rendered.
             */
            'views' => base_path('resources/views/vendor/l5-swagger'),

            /*
             * Edit to set the api's base path
             */
            'base' => env('L5_SWAGGER_BASE_PATH', null),

            /*
             * Edit to set path where swagger ui assets should be stored
             */
            'swagger_ui_assets_path' => env('L5_SWAGGER_UI_ASSETS_PATH', 'vendor/swagger-api/swagger-ui/dist/'),

            /*
             * Absolute path to directory where to export swagger
             */
            'docs' => storage_path('api-docs'),

            /*
             * Absolute path to export views
             */
            'views' => base_path('resources/views/vendor/l5-swagger'),
        ],

        'scanOptions' => [
            'default_processors_configuration' => [],

            /**
             * analyser: defaults to \OpenApi\StaticAnalyser .
             *
             * @see \OpenApi\Analyser
             */
            'analyser' => null,

            /**
             * analysis: defaults to a new \OpenApi\Analysis .
             *
             * @see \OpenApi\Analysis
             */
            'analysis' => null,

            /**
             * Custom query path processors classes.
             *
             * @link https://github.com/zircote/swagger-php/tree/master/Examples/schema-query-parameter-processor
             *
             * @see \OpenApi\Processors
             */
            'processors' => [],

            /**
             * pattern: string       $pattern File pattern(s) to scan (default: *.php) .
             *
             * @see \OpenApi\scan
             */
            'pattern' => null,

            /*
             * Absolute path to folders that you would like to exclude from swagger generation
             */
            'exclude' => [],

            /*
             * Allows viewing private (not all) routes
             */
            'open_api_spec_version' => env('L5_SWAGGER_OPEN_API_SPEC_VERSION', \L5Swagger\Generator::OPEN_API_DEFAULT_SPEC_VERSION),
        ],

        /*
         * API security definitions. Will be generated into the swagger documentation.
         * @see https://swagger.io/docs/specification/authentication
         */
        'securityDefinitions' => [
            'securitySchemes' => [
                'bearerAuth' => [
                    'type'         => 'http',
                    'scheme'       => 'bearer',
                    'bearerFormat' => 'JWT',
                ],
                'apiKeyAuth' => [
                    'type' => 'apiKey',
                    'in'   => 'header',
                    'name' => 'X-Api-Key',
                ],
            ],
            'security' => [],
        ],

        /*
         * Set this to `true` in development mode so that docs would get regenerated on each request.
         * Set to `false` to disable in production environments.
         */
        'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', false),

        /*
         * Set this to `true` to generate a copy of documentation always when application is in debug mode
         */
        'generate_yaml_copy' => env('L5_SWAGGER_GENERATE_YAML_COPY', false),

        /*
         * Edit to trust the proxy's ip address - needed for AWS Load Balancer
         * string[]
         */
        'proxy' => false,

        /*
         * Configs plugin allows to fetch external configs instead of passing them to SwaggerUIBundle.
         * See more at: https://github.com/swagger-api/swagger-ui#configs-plugin
         */
        'additional_config_url' => null,

        /*
         * Apply a sort to the operation list of each API. It can be 'alpha' (sort by paths alphanumerically),
         * 'method' (sort by HTTP method).
         * Default is the order returned by the server unchanged.
         */
        'operations_sort' => env('L5_SWAGGER_OPERATIONS_SORT', null),

        /*
         * Pass the validatorUrl parameter to SwaggerUi init on the JS side.
         * A null value here disables the validator.
         */
        'validator_url' => null,

        /*
         * Uncomment to add your custom UI configuration options
         * @see https://swagger.io/docs/open-source-tools/swagger-ui/usage/configuration/
         */
        'ui' => [
            'display' => [
                'dark_mode'     => env('L5_SWAGGER_UI_DARK_MODE', false),
                'doc_expansion' => env('L5_SWAGGER_UI_DOC_EXPANSION', 'none'),
                'filter'        => env('L5_SWAGGER_UI_FILTERS', true),
            ],
            'authorization' => [
                'persist_authorization' => env('L5_SWAGGER_UI_PERSIST_AUTHORIZATION', true),
            ],
        ],

        /*
         * Constants which can be used in annotations
         */
        'constants' => [
            'L5_SWAGGER_CONST_HOST' => env('L5_SWAGGER_CONST_HOST', 'http://localhost:8000'),
        ],
    ],
];
