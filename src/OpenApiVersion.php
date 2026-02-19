<?php

declare(strict_types=1);

namespace openapiphp\openapi;

enum OpenApiVersion: string
{
    case VERSION_3_0         = '3.0';
    case VERSION_3_1         = '3.1';
    case VERSION_UNSUPPORTED = 'unsupported';
}
