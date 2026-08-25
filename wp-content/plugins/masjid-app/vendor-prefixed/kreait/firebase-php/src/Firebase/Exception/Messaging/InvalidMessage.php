<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Exception\Messaging;

use Masjid_App\Dependencies\Kreait\Firebase\Exception\HasErrors;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\MessagingException;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\RuntimeException;
final class InvalidMessage extends RuntimeException implements MessagingException
{
    use HasErrors;
    /**
     * @internal
     *
     * @param array<mixed> $errors
     */
    public function withErrors(array $errors): self
    {
        $new = new self($this->getMessage(), $this->getCode(), $this->getPrevious());
        $new->errors = $errors;
        return $new;
    }
}
