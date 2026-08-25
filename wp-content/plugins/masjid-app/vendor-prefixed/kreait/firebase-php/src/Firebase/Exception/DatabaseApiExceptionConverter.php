<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Exception;

use Masjid_App\Dependencies\Fig\Http\Message\StatusCodeInterface as StatusCode;
use Masjid_App\Dependencies\GuzzleHttp\Exception\RequestException;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\Database\ApiConnectionFailed;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\Database\DatabaseError;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\Database\DatabaseNotFound;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\Database\PermissionDenied;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\Database\PreconditionFailed;
use Masjid_App\Dependencies\Kreait\Firebase\Http\ErrorResponseParser;
use Masjid_App\Dependencies\Psr\Http\Client\NetworkExceptionInterface;
use Throwable;
/**
 * @internal
 */
class DatabaseApiExceptionConverter
{
    public function __construct(private readonly ErrorResponseParser $responseParser)
    {
    }
    public function convertException(Throwable $exception): DatabaseException
    {
        if ($exception instanceof RequestException) {
            return $this->convertGuzzleRequestException($exception);
        }
        if ($exception instanceof NetworkExceptionInterface) {
            return new ApiConnectionFailed('Unable to connect to the API: ' . $exception->getMessage(), $exception->getCode(), $exception);
        }
        return new DatabaseError($exception->getMessage(), $exception->getCode(), $exception);
    }
    private function convertGuzzleRequestException(RequestException $e): DatabaseException
    {
        $message = $e->getMessage();
        $code = $e->getCode();
        $response = $e->getResponse();
        if ($response !== null) {
            $message = $this->responseParser->getErrorReasonFromResponse($response);
            $code = $response->getStatusCode();
        }
        return match ($code) {
            StatusCode::STATUS_UNAUTHORIZED, StatusCode::STATUS_FORBIDDEN => new PermissionDenied($message, $code, $e),
            StatusCode::STATUS_PRECONDITION_FAILED => new PreconditionFailed($message, $code, $e),
            StatusCode::STATUS_NOT_FOUND => DatabaseNotFound::fromUri($e->getRequest()->getUri()),
            default => new DatabaseError($message, $code, $e),
        };
    }
}
