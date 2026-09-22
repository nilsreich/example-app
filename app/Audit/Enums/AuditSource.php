<?php

namespace App\Audit\Enums;

enum AuditSource: string
{
    case Web = 'web';
    case Ai = 'ai';
    case Cli = 'cli';
    case Api = 'api';
    case Entra = 'entra';
}
