<?php

/**
 * @copyright Copyright (c) 2018 Carsten Brandt <mail@cebe.cc> and contributors
 * @license https://github.com/cebe/php-openapi/blob/master/LICENSE
 */

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = (new Finder())
    ->ignoreDotFiles(false)
    ->ignoreVCSIgnored(true)
    ->in(__DIR__)
;

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR2' => true,
        'array_syntax' => ['syntax' => 'short'],
        'general_phpdoc_annotation_remove' => ['annotations' => ['author']],
        'header_comment' => [
            'comment_type' => 'PHPDoc',
            'header' => <<<COMMENT
                @copyright Copyright (c) 2018 Carsten Brandt <mail@cebe.cc> and contributors
                @license https://github.com/cebe/php-openapi/blob/master/LICENSE
                COMMENT,
        ]
    ])
    ->setFinder($finder);

return PhpCsFixer\Config::create()
    ->setRules([
        '@PSR2' => true,
        'array_syntax' => ['syntax' => 'short'],
        'general_phpdoc_annotation_remove' => ['annotations' => ['author']],
        'header_comment' => [
            'comment_type' => 'PHPDoc',
            'header' => <<<COMMENT
@copyright Copyright (c) 2018 Carsten Brandt <mail@cebe.cc> and contributors
@license https://github.com/cebe/php-openapi/blob/master/LICENSE
COMMENT
        ]
    ])
;
