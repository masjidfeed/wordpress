<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Exception\Messaging;

use DateTimeImmutable;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\HasErrors;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\MessagingException;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\RuntimeException;
final class QuotaExceeded extends RuntimeException implements MessagingException
{
    use HasErrors;
    private ?DateTimeImmutable $retryAfter = null;
    /**
     * @internal
     *
     * @param array<mixed> $errors
     */
    public function withErrors(array $errors): self
    {
        $new = new self($this->getMessage(), $this->getCode(), $this->getPrevious());
        $new->errors = $errors;
        $new->retryAfter = $this->retryAfter;
        return $new;
    }
    public function withRetryAfter(DateTimeImmutable $retryAfter): self
    {
        $new = new self($this->getMessage(), $this->getCode(), $this->getPrevious());
        $new->errors = $this->errors;
        $new->retryAfter = $retryAfter;
        return $new;
    }
    public function retryAfter(): ?DateTimeImmutable
    {
        return $this->retryAfter;
    }
}
