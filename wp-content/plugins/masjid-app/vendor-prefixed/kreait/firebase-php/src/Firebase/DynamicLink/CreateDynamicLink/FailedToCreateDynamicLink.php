<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\DynamicLink\CreateDynamicLink;

use Masjid_App\Dependencies\Beste\Json;
use Masjid_App\Dependencies\Kreait\Firebase\DynamicLink\CreateDynamicLink;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\RuntimeException;
use Masjid_App\Dependencies\Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;
final class FailedToCreateDynamicLink extends RuntimeException
{
    private ?CreateDynamicLink $action = null;
    private ?ResponseInterface $response = null;
    public static function withActionAndResponse(CreateDynamicLink $action, ResponseInterface $response): self
    {
        $fallbackMessage = 'Failed to create dynamic link';
        try {
            $message = Json::decode((string) $response->getBody(), \true)['error']['message'] ?? $fallbackMessage;
        } catch (UnexpectedValueException) {
            $message = $fallbackMessage;
        }
        $error = new self($message);
        $error->action = $action;
        $error->response = $response;
        return $error;
    }
    public function action(): ?CreateDynamicLink
    {
        return $this->action;
    }
    public function response(): ?ResponseInterface
    {
        return $this->response;
    }
}
