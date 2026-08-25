<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\JWT\Action\CreateCustomToken;

use DateTimeInterface;
use Masjid_App\Dependencies\Kreait\Firebase\JWT\Action\CreateCustomToken;
use Masjid_App\Dependencies\Kreait\Firebase\JWT\Contract\Token;
use Masjid_App\Dependencies\Kreait\Firebase\JWT\Error\CustomTokenCreationFailed;
use Masjid_App\Dependencies\Kreait\Firebase\JWT\SecureToken;
use Masjid_App\Dependencies\Lcobucci\JWT\Configuration;
use Masjid_App\Dependencies\Lcobucci\JWT\Signer\Key\InMemory;
use Masjid_App\Dependencies\Lcobucci\JWT\Signer\Rsa\Sha256;
use Masjid_App\Dependencies\Psr\Clock\ClockInterface;
use Throwable;
/**
 * @internal
 */
final class WithLcobucciJWT implements Handler
{
    private readonly Configuration $config;
    /**
     * @param non-empty-string $clientEmail
     * @param non-empty-string $privateKey
     */
    public function __construct(private readonly string $clientEmail, string $privateKey, private readonly ClockInterface $clock)
    {
        $this->config = Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText($privateKey));
    }
    public function handle(CreateCustomToken $action): Token
    {
        $now = $this->clock->now();
        $builder = $this->config->builder()->issuedAt($now)->issuedBy($this->clientEmail)->expiresAt($now->add($action->timeToLive()->value()))->relatedTo($this->clientEmail)->permittedFor('https://identitytoolkit.googleapis.com/google.identity.identitytoolkit.v1.IdentityToolkit')->withClaim('uid', $action->uid());
        if ($tenantId = $action->tenantId()) {
            $builder = $builder->withClaim('tenant_id', $tenantId);
        }
        if (!empty($customClaims = $action->customClaims())) {
            $builder = $builder->withClaim('claims', $customClaims);
        }
        try {
            $token = $builder->getToken($this->config->signer(), $this->config->signingKey());
        } catch (Throwable $e) {
            throw CustomTokenCreationFailed::because($e->getMessage(), $e->getCode(), $e);
        }
        $claims = $token->claims()->all();
        foreach ($claims as &$claim) {
            if ($claim instanceof DateTimeInterface) {
                $claim = $claim->getTimestamp();
            }
        }
        unset($claim);
        $headers = $token->headers()->all();
        foreach ($headers as &$header) {
            if ($header instanceof DateTimeInterface) {
                $header = $header->getTimestamp();
            }
        }
        unset($header);
        return SecureToken::withValues($token->toString(), $headers, $claims);
    }
}
