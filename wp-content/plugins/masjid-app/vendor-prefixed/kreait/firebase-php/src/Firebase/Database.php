<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase;

use Masjid_App\Dependencies\GuzzleHttp\Psr7\Uri;
use Masjid_App\Dependencies\Kreait\Firebase\Database\ApiClient;
use Masjid_App\Dependencies\Kreait\Firebase\Database\Reference;
use Masjid_App\Dependencies\Kreait\Firebase\Database\RuleSet;
use Masjid_App\Dependencies\Kreait\Firebase\Database\Transaction;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\InvalidArgumentException;
use Masjid_App\Dependencies\Psr\Http\Message\UriInterface;
use function ltrim;
use function sprintf;
use function trim;
/**
 * @internal
 */
final class Database implements Contract\Database
{
    public function __construct(private readonly UriInterface $uri, private readonly ApiClient $client)
    {
    }
    public function getReference(?string $path = null): Reference
    {
        if ($path === null || trim($path) === '') {
            $path = '/';
        }
        $path = '/' . ltrim($path, '/');
        try {
            return new Reference($this->uri->withPath($path), $this->client);
        } catch (\InvalidArgumentException $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }
    public function getReferenceFromUrl($uri): Reference
    {
        $uri = $uri instanceof UriInterface ? $uri : new Uri($uri);
        if (($givenHost = $uri->getHost()) !== $dbHost = $this->uri->getHost()) {
            throw new InvalidArgumentException(sprintf('The given URI\'s host "%s" is not covered by the database for the host "%s".', $givenHost, $dbHost));
        }
        return $this->getReference($uri->getPath());
    }
    public function getRuleSet(): RuleSet
    {
        $rules = $this->client->get('/.settings/rules');
        return RuleSet::fromArray($rules);
    }
    public function updateRules(RuleSet $ruleSet): void
    {
        $this->client->updateRules('/.settings/rules', $ruleSet);
    }
    public function runTransaction(callable $callable): mixed
    {
        $transaction = new Transaction($this->client);
        return $callable($transaction);
    }
}
