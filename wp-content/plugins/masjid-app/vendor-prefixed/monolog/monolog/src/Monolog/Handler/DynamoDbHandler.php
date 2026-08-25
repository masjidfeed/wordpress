<?php

declare (strict_types=1);
/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Masjid_App\Dependencies\Monolog\Handler;

use Masjid_App\Dependencies\Aws\Sdk;
use Masjid_App\Dependencies\Aws\DynamoDb\DynamoDbClient;
use Masjid_App\Dependencies\Monolog\Formatter\FormatterInterface;
use Masjid_App\Dependencies\Aws\DynamoDb\Marshaler;
use Masjid_App\Dependencies\Monolog\Formatter\ScalarFormatter;
use Masjid_App\Dependencies\Monolog\Level;
use Masjid_App\Dependencies\Monolog\LogRecord;
/**
 * Amazon DynamoDB handler (http://aws.amazon.com/dynamodb/)
 *
 * @link https://github.com/aws/aws-sdk-php/
 * @author Andrew Lawson <adlawson@gmail.com>
 */
class DynamoDbHandler extends AbstractProcessingHandler
{
    public const DATE_FORMAT = 'Y-m-d\TH:i:s.uO';
    protected DynamoDbClient $client;
    protected string $table;
    protected Marshaler $marshaler;
    public function __construct(DynamoDbClient $client, string $table, int|string|Level $level = Level::Debug, bool $bubble = \true)
    {
        $this->marshaler = new Marshaler();
        $this->client = $client;
        $this->table = $table;
        parent::__construct($level, $bubble);
    }
    /**
     * @inheritDoc
     */
    protected function write(LogRecord $record): void
    {
        $filtered = $this->filterEmptyFields($record->formatted);
        $formatted = $this->marshaler->marshalItem($filtered);
        $this->client->putItem(['TableName' => $this->table, 'Item' => $formatted]);
    }
    /**
     * @param  mixed[] $record
     * @return mixed[]
     */
    protected function filterEmptyFields(array $record): array
    {
        return array_filter($record, function ($value) {
            return [] !== $value;
        });
    }
    /**
     * @inheritDoc
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        return new ScalarFormatter(self::DATE_FORMAT);
    }
}
