<?php

namespace App\Logging;

use App\Support\LogRedactor;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class RedactSensitiveDataProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(context: LogRedactor::redact($record->context));
    }
}
