<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Auth\CreateActionLink;

use Masjid_App\Dependencies\Beste\Json;
use InvalidArgumentException;
use Masjid_App\Dependencies\Kreait\Firebase\Auth\CreateActionLink;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\AuthException;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\RuntimeException;
use Masjid_App\Dependencies\Psr\Http\Message\ResponseInterface;
final class FailedToCreateActionLink extends RuntimeException implements AuthException
{
    private ?CreateActionLink $action = null;
    private ?ResponseInterface $response = null;
    public static function withActionAndResponse(CreateActionLink $action, ResponseInterface $response): self
    {
        $fallbackMessage = 'Failed to create action link';
        try {
            $message = Json::decode((string) $response->getBody(), \true)['error']['message'] ?? $fallbackMessage;
        } catch (InvalidArgumentException) {
            $message = $fallbackMessage;
        }
        $error = new self($message);
        $error->action = $action;
        $error->response = $response;
        return $error;
    }
    public function action(): ?CreateActionLink
    {
        return $this->action;
    }
    public function response(): ?ResponseInterface
    {
        return $this->response;
    }
}
