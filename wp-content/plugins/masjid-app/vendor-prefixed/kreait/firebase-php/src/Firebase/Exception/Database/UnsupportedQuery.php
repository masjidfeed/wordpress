<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Exception\Database;

use Masjid_App\Dependencies\Kreait\Firebase\Database\Query;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\DatabaseException;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\RuntimeException;
use Throwable;
final class UnsupportedQuery extends RuntimeException implements DatabaseException
{
    public function __construct(private readonly Query $query, string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
    public function getQuery(): Query
    {
        return $this->query;
    }
}
