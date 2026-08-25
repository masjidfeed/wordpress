<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Masjid_App\Dependencies\Symfony\Component\VarDumper\Cloner;

use Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\Caster;
use Masjid_App\Dependencies\Symfony\Component\VarDumper\Exception\ThrowingCasterException;
/**
 * AbstractCloner implements a generic caster mechanism for objects and resources.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
abstract class AbstractCloner implements ClonerInterface
{
    public static array $defaultCasters = ['__PHP_Incomplete_Class' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\Caster', 'castPhpIncompleteClass'], 'AddressInfo' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\AddressInfoCaster', 'castAddressInfo'], 'Socket' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SocketCaster', 'castSocket'], 'Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\CutStub' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\StubCaster', 'castStub'], 'Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\CutArrayStub' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\StubCaster', 'castCutArray'], 'Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ConstStub' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\StubCaster', 'castStub'], 'Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\EnumStub' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\StubCaster', 'castEnum'], 'Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ScalarStub' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\StubCaster', 'castScalar'], 'Fiber' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\FiberCaster', 'castFiber'], 'Closure' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castClosure'], 'Generator' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castGenerator'], 'ReflectionType' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castType'], 'ReflectionAttribute' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castAttribute'], 'ReflectionGenerator' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castReflectionGenerator'], 'ReflectionClass' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castClass'], 'ReflectionClassConstant' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castClassConstant'], 'ReflectionFunctionAbstract' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castFunctionAbstract'], 'ReflectionMethod' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castMethod'], 'ReflectionParameter' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castParameter'], 'ReflectionProperty' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castProperty'], 'ReflectionReference' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castReference'], 'ReflectionExtension' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castExtension'], 'ReflectionZendExtension' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ReflectionCaster', 'castZendExtension'], 'Masjid_App\Dependencies\Doctrine\Common\Persistence\ObjectManager' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'], 'Masjid_App\Dependencies\Doctrine\Common\Proxy\Proxy' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DoctrineCaster', 'castCommonProxy'], 'Masjid_App\Dependencies\Doctrine\ORM\Proxy\Proxy' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DoctrineCaster', 'castOrmProxy'], 'Masjid_App\Dependencies\Doctrine\ORM\PersistentCollection' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DoctrineCaster', 'castPersistentCollection'], 'Masjid_App\Dependencies\Doctrine\Persistence\ObjectManager' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'], 'DOMException' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DOMCaster', 'castException'], 'Masjid_App\Dependencies\Dom\Exception' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DOMCaster', 'castException'], 'DOMStringList' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'DOMNameList' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'DOMImplementation' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DOMCaster', 'castImplementation'], 'Masjid_App\Dependencies\Dom\Implementation' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DOMCaster', 'castImplementation'], 'DOMImplementationList' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'DOMNode' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'Masjid_App\Dependencies\Dom\Node' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'DOMNameSpaceNode' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'DOMDocument' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDocument'], 'Masjid_App\Dependencies\Dom\XMLDocument' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DOMCaster', 'castXMLDocument'], 'Masjid_App\Dependencies\Dom\HTMLDocument' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DOMCaster', 'castHTMLDocument'], 'DOMNodeList' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'Masjid_App\Dependencies\Dom\NodeList' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'DOMNamedNodeMap' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'Masjid_App\Dependencies\Dom\DTDNamedNodeMap' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'DOMXPath' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'Masjid_App\Dependencies\Dom\XPath' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'Masjid_App\Dependencies\Dom\HTMLCollection' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'Masjid_App\Dependencies\Dom\TokenList' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DOMCaster', 'castDom'], 'XMLReader' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\XmlReaderCaster', 'castXmlReader'], 'ErrorException' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ExceptionCaster', 'castErrorException'], 'Exception' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ExceptionCaster', 'castException'], 'Error' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ExceptionCaster', 'castError'], 'Masjid_App\Dependencies\Symfony\Bridge\Monolog\Logger' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'], 'Masjid_App\Dependencies\Symfony\Component\DependencyInjection\ContainerInterface' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'], 'Masjid_App\Dependencies\Symfony\Component\EventDispatcher\EventDispatcherInterface' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'], 'Masjid_App\Dependencies\Symfony\Component\HttpClient\AmpHttpClient' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castHttpClient'], 'Masjid_App\Dependencies\Symfony\Component\HttpClient\CurlHttpClient' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castHttpClient'], 'Masjid_App\Dependencies\Symfony\Component\HttpClient\NativeHttpClient' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castHttpClient'], 'Masjid_App\Dependencies\Symfony\Component\HttpClient\Response\AmpResponse' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castHttpClientResponse'], 'Masjid_App\Dependencies\Symfony\Component\HttpClient\Response\AmpResponseV4' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castHttpClientResponse'], 'Masjid_App\Dependencies\Symfony\Component\HttpClient\Response\AmpResponseV5' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castHttpClientResponse'], 'Masjid_App\Dependencies\Symfony\Component\HttpClient\Response\CurlResponse' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castHttpClientResponse'], 'Masjid_App\Dependencies\Symfony\Component\HttpClient\Response\NativeResponse' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castHttpClientResponse'], 'Masjid_App\Dependencies\Symfony\Component\HttpFoundation\Request' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castRequest'], 'Masjid_App\Dependencies\Symfony\Component\Uid\Ulid' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castUlid'], 'Masjid_App\Dependencies\Symfony\Component\Uid\Uuid' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castUuid'], 'Masjid_App\Dependencies\Symfony\Component\VarExporter\Internal\LazyObjectState' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SymfonyCaster', 'castLazyObjectState'], 'Masjid_App\Dependencies\Symfony\Component\VarDumper\Exception\ThrowingCasterException' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ExceptionCaster', 'castThrowingCasterException'], 'Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\TraceStub' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ExceptionCaster', 'castTraceStub'], 'Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\FrameStub' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ExceptionCaster', 'castFrameStub'], 'Masjid_App\Dependencies\Symfony\Component\VarDumper\Cloner\AbstractCloner' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'], 'Masjid_App\Dependencies\Symfony\Component\ErrorHandler\Exception\FlattenException' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ExceptionCaster', 'castFlattenException'], 'Masjid_App\Dependencies\Symfony\Component\ErrorHandler\Exception\SilencedErrorContext' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ExceptionCaster', 'castSilencedErrorContext'], 'Masjid_App\Dependencies\Imagine\Image\ImageInterface' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ImagineCaster', 'castImage'], 'Masjid_App\Dependencies\Ramsey\Uuid\UuidInterface' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\UuidCaster', 'castRamseyUuid'], 'Masjid_App\Dependencies\ProxyManager\Proxy\ProxyInterface' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ProxyManagerCaster', 'castProxy'], 'PHPUnit_Framework_MockObject_MockObject' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'], 'Masjid_App\Dependencies\PHPUnit\Framework\MockObject\MockObject' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'], 'Masjid_App\Dependencies\PHPUnit\Framework\MockObject\Stub' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'], 'Masjid_App\Dependencies\Prophecy\Prophecy\ProphecySubjectInterface' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'], 'Masjid_App\Dependencies\Mockery\MockInterface' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\StubCaster', 'cutInternals'], 'PDO' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\PdoCaster', 'castPdo'], 'PDOStatement' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\PdoCaster', 'castPdoStatement'], 'AMQPConnection' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\AmqpCaster', 'castConnection'], 'AMQPChannel' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\AmqpCaster', 'castChannel'], 'AMQPQueue' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\AmqpCaster', 'castQueue'], 'AMQPExchange' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\AmqpCaster', 'castExchange'], 'AMQPEnvelope' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\AmqpCaster', 'castEnvelope'], 'ArrayObject' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SplCaster', 'castArrayObject'], 'ArrayIterator' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SplCaster', 'castArrayIterator'], 'SplDoublyLinkedList' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SplCaster', 'castDoublyLinkedList'], 'SplFileInfo' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SplCaster', 'castFileInfo'], 'SplFileObject' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SplCaster', 'castFileObject'], 'SplHeap' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SplCaster', 'castHeap'], 'SplObjectStorage' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SplCaster', 'castObjectStorage'], 'SplPriorityQueue' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SplCaster', 'castHeap'], 'OuterIterator' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SplCaster', 'castOuterIterator'], 'WeakMap' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SplCaster', 'castWeakMap'], 'WeakReference' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SplCaster', 'castWeakReference'], 'Redis' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\RedisCaster', 'castRedis'], 'Masjid_App\Dependencies\Relay\Relay' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\RedisCaster', 'castRedis'], 'RedisArray' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\RedisCaster', 'castRedisArray'], 'RedisCluster' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\RedisCaster', 'castRedisCluster'], 'DateTimeInterface' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DateCaster', 'castDateTime'], 'DateInterval' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DateCaster', 'castInterval'], 'DateTimeZone' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DateCaster', 'castTimeZone'], 'DatePeriod' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DateCaster', 'castPeriod'], 'GMP' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\GmpCaster', 'castGmp'], 'MessageFormatter' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\IntlCaster', 'castMessageFormatter'], 'NumberFormatter' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\IntlCaster', 'castNumberFormatter'], 'IntlTimeZone' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\IntlCaster', 'castIntlTimeZone'], 'IntlCalendar' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\IntlCaster', 'castIntlCalendar'], 'IntlDateFormatter' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\IntlCaster', 'castIntlDateFormatter'], 'Memcached' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\MemcachedCaster', 'castMemcached'], 'Masjid_App\Dependencies\Ds\Collection' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DsCaster', 'castCollection'], 'Masjid_App\Dependencies\Ds\Map' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DsCaster', 'castMap'], 'Masjid_App\Dependencies\Ds\Pair' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DsCaster', 'castPair'], 'Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DsPairStub' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\DsCaster', 'castPairStub'], 'mysqli_driver' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\MysqliCaster', 'castMysqliDriver'], 'CurlHandle' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\CurlCaster', 'castCurl'], 'Masjid_App\Dependencies\Dba\Connection' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ResourceCaster', 'castDba'], ':dba' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ResourceCaster', 'castDba'], ':dba persistent' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ResourceCaster', 'castDba'], 'GdImage' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\GdCaster', 'castGd'], 'SQLite3Result' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\SqliteCaster', 'castSqlite3Result'], 'Masjid_App\Dependencies\PgSql\Lob' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\PgSqlCaster', 'castLargeObject'], 'Masjid_App\Dependencies\PgSql\Connection' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\PgSqlCaster', 'castLink'], 'Masjid_App\Dependencies\PgSql\Result' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\PgSqlCaster', 'castResult'], ':process' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ResourceCaster', 'castProcess'], ':stream' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ResourceCaster', 'castStream'], 'OpenSSLAsymmetricKey' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\OpenSSLCaster', 'castOpensslAsymmetricKey'], 'OpenSSLCertificateSigningRequest' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\OpenSSLCaster', 'castOpensslCsr'], 'OpenSSLCertificate' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\OpenSSLCaster', 'castOpensslX509'], ':persistent stream' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ResourceCaster', 'castStream'], ':stream-context' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\ResourceCaster', 'castStreamContext'], 'XmlParser' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\XmlResourceCaster', 'castXml'], 'RdKafka' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\RdKafkaCaster', 'castRdKafka'], 'Masjid_App\Dependencies\RdKafka\Conf' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\RdKafkaCaster', 'castConf'], 'Masjid_App\Dependencies\RdKafka\KafkaConsumer' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\RdKafkaCaster', 'castKafkaConsumer'], 'Masjid_App\Dependencies\RdKafka\Metadata\Broker' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\RdKafkaCaster', 'castBrokerMetadata'], 'Masjid_App\Dependencies\RdKafka\Metadata\Collection' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\RdKafkaCaster', 'castCollectionMetadata'], 'Masjid_App\Dependencies\RdKafka\Metadata\Partition' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\RdKafkaCaster', 'castPartitionMetadata'], 'Masjid_App\Dependencies\RdKafka\Metadata\Topic' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\RdKafkaCaster', 'castTopicMetadata'], 'Masjid_App\Dependencies\RdKafka\Message' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\RdKafkaCaster', 'castMessage'], 'Masjid_App\Dependencies\RdKafka\Topic' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\RdKafkaCaster', 'castTopic'], 'Masjid_App\Dependencies\RdKafka\TopicPartition' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\RdKafkaCaster', 'castTopicPartition'], 'Masjid_App\Dependencies\RdKafka\TopicConf' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\RdKafkaCaster', 'castTopicConf'], 'Masjid_App\Dependencies\FFI\CData' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\FFICaster', 'castCTypeOrCData'], 'Masjid_App\Dependencies\FFI\CType' => ['Masjid_App\Dependencies\Symfony\Component\VarDumper\Caster\FFICaster', 'castCTypeOrCData']];
    protected int $maxItems = 2500;
    protected int $maxString = -1;
    protected int $minDepth = 1;
    /**
     * @var array<string, list<callable>>
     */
    private array $casters = [];
    /**
     * @var callable|null
     */
    private $prevErrorHandler;
    private array $classInfo = [];
    private int $filter = 0;
    /**
     * @param callable[]|null $casters A map of casters
     *
     * @see addCasters
     */
    public function __construct(?array $casters = null)
    {
        $this->addCasters($casters ?? static::$defaultCasters);
    }
    /**
     * Adds casters for resources and objects.
     *
     * Maps resources or object types to a callback.
     * Use types as keys and callable casters as values.
     * Prefix types with `::`,
     * see e.g. self::$defaultCasters.
     *
     * @param array<string, callable> $casters A map of casters
     */
    public function addCasters(array $casters): void
    {
        foreach ($casters as $type => $callback) {
            $this->casters[$type][] = $callback;
        }
    }
    /**
     * Adds default casters for resources and objects.
     *
     * Maps resources or object types to a callback.
     * Use types as keys and callable casters as values.
     * Prefix types with `::`,
     * see e.g. self::$defaultCasters.
     *
     * @param array<string, callable> $casters A map of casters
     */
    public static function addDefaultCasters(array $casters): void
    {
        self::$defaultCasters = [...self::$defaultCasters, ...$casters];
    }
    /**
     * Sets the maximum number of items to clone past the minimum depth in nested structures.
     */
    public function setMaxItems(int $maxItems): void
    {
        $this->maxItems = $maxItems;
    }
    /**
     * Sets the maximum cloned length for strings.
     */
    public function setMaxString(int $maxString): void
    {
        $this->maxString = $maxString;
    }
    /**
     * Sets the minimum tree depth where we are guaranteed to clone all the items.  After this
     * depth is reached, only setMaxItems items will be cloned.
     */
    public function setMinDepth(int $minDepth): void
    {
        $this->minDepth = $minDepth;
    }
    /**
     * Clones a PHP variable.
     *
     * @param int $filter A bit field of Caster::EXCLUDE_* constants
     */
    public function cloneVar(mixed $var, int $filter = 0): Data
    {
        $this->prevErrorHandler = set_error_handler(function ($type, $msg, $file, $line, $context = []) {
            if (\E_RECOVERABLE_ERROR === $type || \E_USER_ERROR === $type) {
                // Cloner never dies
                throw new \ErrorException($msg, 0, $type, $file, $line);
            }
            if ($this->prevErrorHandler) {
                return ($this->prevErrorHandler)($type, $msg, $file, $line, $context);
            }
            return \false;
        });
        $this->filter = $filter;
        if ($gc = gc_enabled()) {
            gc_disable();
        }
        try {
            return new Data($this->doClone($var));
        } finally {
            if ($gc) {
                gc_enable();
            }
            restore_error_handler();
            $this->prevErrorHandler = null;
        }
    }
    /**
     * Effectively clones the PHP variable.
     */
    abstract protected function doClone(mixed $var): array;
    /**
     * Casts an object to an array representation.
     *
     * @param bool $isNested True if the object is nested in the dumped structure
     */
    protected function castObject(Stub $stub, bool $isNested): array
    {
        $obj = $stub->value;
        $class = $stub->class;
        if (str_contains($class, "@anonymous\x00")) {
            $stub->class = get_debug_type($obj);
        }
        if (isset($this->classInfo[$class])) {
            [$i, $parents, $hasDebugInfo, $fileInfo] = $this->classInfo[$class];
        } else {
            $i = 2;
            $parents = [$class];
            $hasDebugInfo = method_exists($class, '__debugInfo');
            foreach (class_parents($class) as $p) {
                $parents[] = $p;
                ++$i;
            }
            foreach (class_implements($class) as $p) {
                $parents[] = $p;
                ++$i;
            }
            $parents[] = '*';
            $r = new \ReflectionClass($class);
            $fileInfo = $r->isInternal() || $r->isSubclassOf(Stub::class) ? [] : ['file' => $r->getFileName(), 'line' => $r->getStartLine()];
            $this->classInfo[$class] = [$i, $parents, $hasDebugInfo, $fileInfo];
        }
        $stub->attr += $fileInfo;
        $a = Caster::castObject($obj, $class, $hasDebugInfo, $stub->class);
        try {
            while ($i--) {
                if (!empty($this->casters[$p = $parents[$i]])) {
                    foreach ($this->casters[$p] as $callback) {
                        $a = $callback($obj, $a, $stub, $isNested, $this->filter);
                    }
                }
            }
        } catch (\Exception $e) {
            $a = [(Stub::TYPE_OBJECT === $stub->type ? Caster::PREFIX_VIRTUAL : '') . '⚠' => new ThrowingCasterException($e)] + $a;
        }
        return $a;
    }
    /**
     * Casts a resource to an array representation.
     *
     * @param bool $isNested True if the object is nested in the dumped structure
     */
    protected function castResource(Stub $stub, bool $isNested): array
    {
        $a = [];
        $res = $stub->value;
        $type = $stub->class;
        try {
            if (!empty($this->casters[':' . $type])) {
                foreach ($this->casters[':' . $type] as $callback) {
                    $a = $callback($res, $a, $stub, $isNested, $this->filter);
                }
            }
        } catch (\Exception $e) {
            $a = [(Stub::TYPE_OBJECT === $stub->type ? Caster::PREFIX_VIRTUAL : '') . '⚠' => new ThrowingCasterException($e)] + $a;
        }
        return $a;
    }
}
