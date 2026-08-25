<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Contract;

use Masjid_App\Dependencies\Kreait\Firebase\Database\Reference;
use Masjid_App\Dependencies\Kreait\Firebase\Database\RuleSet;
use Masjid_App\Dependencies\Kreait\Firebase\Database\Transaction;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\DatabaseException;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\InvalidArgumentException;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\OutOfRangeException;
use Masjid_App\Dependencies\Psr\Http\Message\UriInterface;
/**
 * The Firebase Realtime Database.
 *
 * @see https://firebase.google.com/docs/reference/rest/database
 */
interface Database
{
    public const SERVER_TIMESTAMP = ['.sv' => 'timestamp'];
    /**
     * Returns a Reference to the root or the specified path.
     *
     * @throws InvalidArgumentException
     */
    public function getReference(?string $path = null): Reference;
    /**
     * Returns a reference to the root or the path specified in url.
     *
     * @param string|UriInterface $uri
     *
     * @throws InvalidArgumentException If the URL is invalid
     * @throws OutOfRangeException If the URL is not in the same domain as the current database
     */
    public function getReferenceFromUrl($uri): Reference;
    /**
     * Retrieve Firebase Database Rules.
     *
     * @see https://firebase.google.com/docs/database/rest/app-management#retrieving-firebase-realtime-database-rules
     *
     * @throws DatabaseException
     */
    public function getRuleSet(): RuleSet;
    /**
     * Update Firebase Database Rules.
     *
     * @see https://firebase.google.com/docs/database/rest/app-management#updating-firebase-realtime-database-rules
     *
     * @throws DatabaseException
     */
    public function updateRules(RuleSet $ruleSet): void;
    /**
     * @param callable(Transaction $transaction):mixed $callable
     */
    public function runTransaction(callable $callable): mixed;
}
