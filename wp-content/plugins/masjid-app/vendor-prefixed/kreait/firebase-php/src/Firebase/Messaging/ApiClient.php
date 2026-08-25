<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Messaging;

use Masjid_App\Dependencies\GuzzleHttp\ClientInterface;
use Masjid_App\Dependencies\GuzzleHttp\Pool;
use Masjid_App\Dependencies\GuzzleHttp\Promise\PromiseInterface;
use Iterator;
use Masjid_App\Dependencies\Psr\Http\Message\RequestInterface;
/**
 * @internal
 */
class ApiClient
{
    public function __construct(private readonly ClientInterface $client, private readonly string $projectId, private readonly RequestFactory $requestFactory)
    {
    }
    public function createSendRequestForMessage(Message $message, bool $validateOnly): RequestInterface
    {
        return $this->requestFactory->createRequest($message, $this->projectId, $validateOnly);
    }
    /**
     * @param list<RequestInterface>|Iterator<RequestInterface> $requests
     * @param array<string, mixed> $config
     */
    public function pool(array|Iterator $requests, array $config): PromiseInterface
    {
        $pool = new Pool($this->client, $requests, $config);
        return $pool->promise();
    }
}
