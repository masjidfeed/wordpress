<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Auth;

use Masjid_App\Dependencies\Kreait\Firebase\Exception\InvalidArgumentException;
use Masjid_App\Dependencies\Kreait\Firebase\Value\Uid;
use Stringable;
/**
 * @internal
 */
final class DeleteUsersRequest
{
    private const MAX_BATCH_SIZE = 1000;
    private function __construct(
        /** @var list<string> $uids */
        private readonly array $uids,
        private readonly bool $enabledUsersShouldBeForceDeleted
    )
    {
    }
    /**
     * @param iterable<Stringable|string> $uids
     */
    public static function withUids(iterable $uids, bool $forceDeleteEnabledUsers = \false): self
    {
        $validatedUids = [];
        $count = 0;
        foreach ($uids as $uid) {
            $validatedUids[] = Uid::fromString($uid)->value;
            ++$count;
            if ($count > self::MAX_BATCH_SIZE) {
                throw new InvalidArgumentException('Only ' . self::MAX_BATCH_SIZE . ' users can be deleted at a time');
            }
        }
        return new self($validatedUids, $forceDeleteEnabledUsers);
    }
    /**
     * @return string[]
     */
    public function uids(): array
    {
        return $this->uids;
    }
    public function enabledUsersShouldBeForceDeleted(): bool
    {
        return $this->enabledUsersShouldBeForceDeleted;
    }
}
