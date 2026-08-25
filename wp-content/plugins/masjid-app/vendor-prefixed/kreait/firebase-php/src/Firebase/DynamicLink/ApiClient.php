<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\DynamicLink;

use Masjid_App\Dependencies\Beste\Json;
use Masjid_App\Dependencies\GuzzleHttp\ClientInterface;
use Masjid_App\Dependencies\Psr\Http\Client\ClientExceptionInterface;
use Masjid_App\Dependencies\Psr\Http\Message\RequestFactoryInterface;
use Masjid_App\Dependencies\Psr\Http\Message\RequestInterface;
use Masjid_App\Dependencies\Psr\Http\Message\ResponseInterface;
use Masjid_App\Dependencies\Psr\Http\Message\StreamFactoryInterface;
use const JSON_FORCE_OBJECT;
/**
 * @internal
 */
final class ApiClient
{
    public function __construct(private readonly ClientInterface $client, private readonly RequestFactoryInterface $requestFactory, private readonly StreamFactoryInterface $streamFactory)
    {
    }
    public function createDynamicLinkRequest(CreateDynamicLink $action): RequestInterface
    {
        return $this->requestFactory->createRequest('POST', 'https://firebasedynamiclinks.googleapis.com/v1/shortLinks')->withBody($this->streamFactory->createStream(Json::encode($action, JSON_FORCE_OBJECT)))->withHeader('Content-Type', 'application/json; charset=UTF-8');
    }
    public function createStatisticsRequest(GetStatisticsForDynamicLink $action): RequestInterface
    {
        $url = sprintf('https://firebasedynamiclinks.googleapis.com/v1/%s/linkStats?durationDays=%d', rawurlencode($action->dynamicLink()), $action->durationInDays());
        return $this->requestFactory->createRequest('GET', $url)->withHeader('Content-Type', 'application/json; charset=UTF-8');
    }
    public function createShortenLinkRequest(ShortenLongDynamicLink $action): RequestInterface
    {
        return $this->requestFactory->createRequest('POST', 'https://firebasedynamiclinks.googleapis.com/v1/shortLinks')->withBody($this->streamFactory->createStream(Json::encode($action, JSON_FORCE_OBJECT)))->withHeader('Content-Type', 'application/json; charset=UTF-8');
    }
    /**
     * @param array<string, mixed> $options
     *
     * @throws ClientExceptionInterface
     */
    public function send(RequestInterface $request, array $options = []): ResponseInterface
    {
        return $this->client->send($request, $options);
    }
}
