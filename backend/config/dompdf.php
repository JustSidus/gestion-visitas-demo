<?php

return [
    'mode'                  => env('DOMPDF_MODE', 'utf-8'),
    'attributes'            => [
        'encryption'        => env('DOMPDF_ENCRYPTION', 'RC4'),
    ],
    'defines'               => [
        /**
         * The location of the DOMPDF font directory
         *
         * @var string
         */
        'font_dir'          => storage_path('fonts/'),

        /**
         * The location of the DOMPDF temporary directory
         *
         * @var string
         */
        'temp_dir'          => storage_path('framework/temp/'),

        /**
         * A flag to determine whether DomPDF should cache processed .ttf font file data
         * Caching font data improves performance
         *
         * @var boolean
         */
        'font_cache'        => storage_path('fonts/'),

        /**
         * The location of the DOMPDF log directory
         *
         * @var string
         */
        'log_dir'           => storage_path('logs/'),

        /**
         * A flag to determine whether DomPDF should log errors to file
         *
         * @var boolean
         */
        'enable_font_subsetting'  => true,
    ],
    'options'               => [
        /**
         * The default paper size.
         *
         * Note that when using custom sizes, the units must be in the same unit as specified in "size" => array.
         * It's recommended to use millimeters (mm) for the paper dimensions.
         *
         * @var array
         */
        'paper_size'        => env('DOMPDF_PAPER_SIZE', 'a4'),

        /**
         * The default paper orientation.
         *
         * @var string
         */
        'orientation'       => env('DOMPDF_ORIENTATION', 'portrait'),

        /**
         * Controls the use of PHP's eval() for variable interpolation, in templates
         * This is a security risk and should never be enabled for untrusted documents.
         *
         * @var bool
         */
        'enable_php'        => false,

        /**
         * Controls the use of CSS media queries, allowing documents to be printed correctly
         *
         * @var bool
         */
        'enable_css_float'  => false,

        /**
         * Controls the use of CSS page breaks, allowing documents to be printed on separate pages
         *
         * @var bool
         */
        'enable_javascript' => false,

        /**
         * 0 = automatic orientation
         * 1 = portrait
         * 2 = landscape
         *
         * @var int
         */
        'default_media_type' => env('DOMPDF_DEFAULT_MEDIA_TYPE', 'print'),

        /**
         * The default DPI setting
         * This may be useful in some environments
         *
         * @var int
         */
        'dpi'               => env('DOMPDF_DPI', 96),

        /**
         * A ratio used to increase the font size relative to the font-size CSS property
         * This is used to correct situations where text appears to small relative to the height of the block element
         *
         * @var float
         */
        'font_height_ratio' => 1.33,

        /**
         * Whether to postprocess the generated PDF to correct text positioning and spacing.
         *
         * @var bool
         */
        'postprocess'       => false,

        /**
         * An http authentication user name
         *
         * @var string
         */
        'http_user'         => env('DOMPDF_HTTP_USER', ''),

        /**
         * An http authentication password
         *
         * @var string
         */
        'http_password'     => env('DOMPDF_HTTP_PASSWORD', ''),

        /**
         * Controls whether hyperlinks are converted to actual pdf links
         *
         * @var bool
         */
        'enable_html5_parser' => true,
        
        /**
         * Allow remote resources (images, CSS, etc.)
         *
         * @var bool
         */
        'enable_remote' => true,
    ],
];
