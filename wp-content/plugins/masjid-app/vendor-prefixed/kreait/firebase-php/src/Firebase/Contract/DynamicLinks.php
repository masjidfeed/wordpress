<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Contract;

use InvalidArgumentException;
use Masjid_App\Dependencies\Kreait\Firebase\DynamicLink;
use Masjid_App\Dependencies\Kreait\Firebase\DynamicLink\CreateDynamicLink;
use Masjid_App\Dependencies\Kreait\Firebase\DynamicLink\CreateDynamicLink\FailedToCreateDynamicLink;
use Masjid_App\Dependencies\Kreait\Firebase\DynamicLink\DynamicLinkStatistics;
use Masjid_App\Dependencies\Kreait\Firebase\DynamicLink\GetStatisticsForDynamicLink;
use Masjid_App\Dependencies\Kreait\Firebase\DynamicLink\ShortenLongDynamicLink;
use Masjid_App\Dependencies\Kreait\Firebase\DynamicLink\ShortenLongDynamicLink\FailedToShortenLongDynamicLink;
use Stringable;
/**
 * @deprecated 7.14.0 Firebase Dynamic Links is deprecated and should not be used in new projects. The service will
 *                    shut down on August 25, 2025. The component will remain in the SDK until then, but as the
 *                    Firebase service is deprecated, this component is also deprecated
 *
 * @see https://firebase.google.com/support/dynamic-links-faq Dynamic Links Deprecation FAQ
 *
 * @see https://firebase.google.com/docs/dynamic-links/rest Create Dynamic Links with the REST API
 *
 * @phpstan-import-type CreateDynamicLinkShape from CreateDynamicLink
 * @phpstan-import-type ShortenLongDynamicLinkShape from ShortenLongDynamicLink
 */
interface DynamicLinks
{
    /**
     * @param Stringable|non-empty-string|CreateDynamicLink|CreateDynamicLinkShape $url
     *
     * @throws InvalidArgumentException
     * @throws FailedToCreateDynamicLink
     */
    public function createUnguessableLink(Stringable|string|CreateDynamicLink|array $url): DynamicLink;
    /**
     * @param Stringable|non-empty-string|CreateDynamicLink|CreateDynamicLinkShape $url
     *
     * @throws InvalidArgumentException
     * @throws FailedToCreateDynamicLink
     */
    public function createShortLink(Stringable|string|CreateDynamicLink|array $url): DynamicLink;
    /**
     * @param Stringable|non-empty-string|CreateDynamicLink|CreateDynamicLinkShape $actionOrParametersOrUrl
     *
     * @throws InvalidArgumentException
     * @throws FailedToCreateDynamicLink
     */
    public function createDynamicLink(Stringable|string|CreateDynamicLink|array $actionOrParametersOrUrl, ?string $suffixType = null): DynamicLink;
    /**
     * @param Stringable|non-empty-string|ShortenLongDynamicLink|ShortenLongDynamicLinkShape $longDynamicLinkOrAction
     *
     * @throws InvalidArgumentException
     * @throws FailedToShortenLongDynamicLink
     */
    public function shortenLongDynamicLink(Stringable|string|ShortenLongDynamicLink|array $longDynamicLinkOrAction, ?string $suffixType = null): DynamicLink;
    /**
     * @throws InvalidArgumentException
     * @throws GetStatisticsForDynamicLink\FailedToGetStatisticsForDynamicLink
     */
    public function getStatistics(Stringable|string|GetStatisticsForDynamicLink $dynamicLinkOrAction, ?int $durationInDays = null): DynamicLinkStatistics;
}
