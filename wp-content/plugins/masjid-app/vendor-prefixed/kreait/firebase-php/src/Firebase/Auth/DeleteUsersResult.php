<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Auth;

use Masjid_App\Dependencies\Beste\Json;
use Masjid_App\Dependencies\Psr\Http\Message\ResponseInterface;
use function count;
use function is_countable;
final class DeleteUsersResult
{
    /**
     * @param list<array{
     *     index: int,
     *     localId: string,
     *     message: string
     * }> $rawErrors
     */
    private function __construct(private readonly int $successCount, private readonly int $failureCount, private readonly array $rawErrors)
    {
    }
    /**
     * @internal
     */
    public static function fromRequestAndResponse(DeleteUsersRequest $request, ResponseInterface $response): self
    {
        $data = Json::decode((string) $response->getBody(), \true);
        $errors = $data['errors'] ?? [];
        $failureCount = is_countable($errors) ? count($errors) : 0;
        $successCount = count($request->uids()) - $failureCount;
        return new self($successCount, $failureCount, $errors);
    }
    public function failureCount(): int
    {
        return $this->failureCount;
    }
    public function successCount(): int
    {
        return $this->successCount;
    }
    /**
     * @return list<array{
     *     index: int,
     *     localId: string,
     *     message: string
     * }>
     */
    public function rawErrors(): array
    {
        return $this->rawErrors;
    }
}
