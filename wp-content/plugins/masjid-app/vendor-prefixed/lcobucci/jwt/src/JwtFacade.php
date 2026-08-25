<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Lcobucci\JWT;

use Closure;
use DateTimeImmutable;
use Masjid_App\Dependencies\Lcobucci\JWT\Encoding\ChainedFormatter;
use Masjid_App\Dependencies\Lcobucci\JWT\Encoding\JoseEncoder;
use Masjid_App\Dependencies\Lcobucci\JWT\Signer\Key;
use Masjid_App\Dependencies\Lcobucci\JWT\Validation\Constraint;
use Masjid_App\Dependencies\Lcobucci\JWT\Validation\SignedWith;
use Masjid_App\Dependencies\Lcobucci\JWT\Validation\ValidAt;
use Masjid_App\Dependencies\Lcobucci\JWT\Validation\Validator;
use Masjid_App\Dependencies\Psr\Clock\ClockInterface as Clock;
use function assert;
final class JwtFacade
{
    private readonly Clock $clock;
    public function __construct(private readonly Parser $parser = new Token\Parser(new JoseEncoder()), ?Clock $clock = null)
    {
        $this->clock = $clock ?? new class implements Clock
        {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable();
            }
        };
    }
    /** @param Closure(Builder, DateTimeImmutable):Builder $customiseBuilder */
    public function issue(Signer $signer, Key $signingKey, Closure $customiseBuilder): UnencryptedToken
    {
        $builder = Token\Builder::new(new JoseEncoder(), ChainedFormatter::withUnixTimestampDates());
        $now = $this->clock->now();
        $builder = $builder->issuedAt($now)->canOnlyBeUsedAfter($now)->expiresAt($now->modify('+5 minutes'));
        return $customiseBuilder($builder, $now)->getToken($signer, $signingKey);
    }
    /** @param non-empty-string $jwt */
    public function parse(string $jwt, SignedWith $signedWith, ValidAt $validAt, Constraint ...$constraints): UnencryptedToken
    {
        $token = $this->parser->parse($jwt);
        assert($token instanceof UnencryptedToken);
        (new Validator())->assert($token, $signedWith, $validAt, ...$constraints);
        return $token;
    }
}
