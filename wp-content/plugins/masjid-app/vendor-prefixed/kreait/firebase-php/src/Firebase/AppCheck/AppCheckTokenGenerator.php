<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\AppCheck;

use Masjid_App\Dependencies\Beste\Clock\SystemClock;
use Masjid_App\Dependencies\Firebase\JWT\JWT;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\AppCheck\InvalidAppCheckTokenOptions;
use Masjid_App\Dependencies\Psr\Clock\ClockInterface;
/**
 * @internal
 *
 * @todo Add #[SensitiveParameter] attribute to the private key once the minimum required PHP version is >=8.2
 */
final class AppCheckTokenGenerator
{
    private const APP_CHECK_AUDIENCE = 'https://firebaseappcheck.googleapis.com/google.firebase.appcheck.v1.TokenExchangeService';
    private readonly ClockInterface $clock;
    /**
     * @param non-empty-string $clientEmail
     * @param non-empty-string $privateKey
     */
    public function __construct(private readonly string $clientEmail, private readonly string $privateKey, ?ClockInterface $clock = null)
    {
        $this->clock = $clock ?? SystemClock::create();
    }
    /**
     * @param non-empty-string $appId the Application ID to use for the generated token
     *
     * @throws InvalidAppCheckTokenOptions
     *
     * @return string the generated token
     */
    public function createCustomToken(string $appId, ?AppCheckTokenOptions $options = null): string
    {
        $now = $this->clock->now()->getTimestamp();
        $payload = ['iss' => $this->clientEmail, 'sub' => $this->clientEmail, 'app_id' => $appId, 'aud' => self::APP_CHECK_AUDIENCE, 'iat' => $now, 'exp' => $now + 300];
        if ($options?->ttl !== null) {
            $payload['ttl'] = $options->ttl . 's';
        }
        return JWT::encode($payload, $this->privateKey, 'RS256');
    }
}
