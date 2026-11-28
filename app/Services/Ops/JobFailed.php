<?php
declare(strict_types=1);

namespace App\Services\Ops;

use RuntimeException;

/** a job that ended badly on purpose: $message is a short code for the record, $summary the counts to keep with it */
final class JobFailed extends RuntimeException
{
    public function __construct(string $code, public readonly array $summary = [])
    {
        parent::__construct($code);
    }
}
