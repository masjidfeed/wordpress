<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Auth\SendActionLink;

use Masjid_App\Dependencies\Beste\Json;
use InvalidArgumentException;
use Masjid_App\Dependencies\Kreait\Firebase\Auth\SendActionLink;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\AuthException;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\RuntimeException;
use Masjid_App\Dependencies\Psr\Http\Message\ResponseInterface;
final class FailedToSendActionLink extends RuntimeException implements AuthException
{
    private ?SendActionLink $action = null;
    private ?ResponseInterface $response = null;
    public static function withActionAndResponse(SendActionLink $action, ResponseInterface $response): self
    {
        $fallbackMessage = 'Failed to send action link';
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
    public function action(): ?SendActionLink
    {
        return $this->action;
    }
    public function response(): ?ResponseInterface
    {
        return $this->response;
    }
}
