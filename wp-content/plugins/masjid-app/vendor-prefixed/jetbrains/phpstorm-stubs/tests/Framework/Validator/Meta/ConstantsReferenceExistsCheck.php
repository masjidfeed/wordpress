<?php

namespace Masjid_App\Dependencies\StubTests\Framework\Validator\Meta;

use Masjid_App\Dependencies\StubTests\Framework\MetaFile\MetaReference;
use Masjid_App\Dependencies\StubTests\Framework\MetaFile\MetaReferenceType;
use Masjid_App\Dependencies\StubTests\Framework\Storage\StubDataQueryInterface;
use Masjid_App\Dependencies\StubTests\Framework\Validator\Contracts\CheckInterface;
use Masjid_App\Dependencies\StubTests\Framework\Validator\Contracts\CheckResultSet;
use Masjid_App\Dependencies\StubTests\Framework\Validator\Services\EntityLookupService;
final class ConstantsReferenceExistsCheck implements CheckInterface
{
    private EntityLookupService $entityLookup;
    public function __construct(?EntityLookupService $entityLookup = null)
    {
        $this->entityLookup = $entityLookup ?? new EntityLookupService();
    }
    public function supports(string $phpVersion): bool
    {
        return \true;
    }
    public function run(StubDataQueryInterface $stubs, string $entityId, string $phpVersion): CheckResultSet
    {
        $results = new CheckResultSet();
        [$type, $fqn] = MetaReference::parseEntityId($entityId);
        $found = match ($type) {
            MetaReferenceType::CLASS_CONST => $this->findClassConstant($stubs, $fqn),
            MetaReferenceType::GLOBAL_CONST => $this->findGlobalConstant($stubs, $fqn),
            default => \false,
        };
        if ($found) {
            $results->addSuccess($entityId);
        } else {
            $results->addFailure($entityId, "Meta file references {$type->value} '{$fqn}' which does not exist in stubs");
        }
        return $results;
    }
    private function findClassConstant(StubDataQueryInterface $stubs, string $fqn): bool
    {
        $separatorPos = strrpos($fqn, '::');
        if ($separatorPos === \false) {
            return \false;
        }
        $classId = substr($fqn, 0, $separatorPos);
        $constName = substr($fqn, $separatorPos + 2);
        $classLike = $this->entityLookup->findClassById($stubs, $classId) ?? $this->entityLookup->findInterfaceById($stubs, $classId) ?? $this->entityLookup->findEnumById($stubs, $classId);
        if ($classLike === null) {
            return \false;
        }
        foreach ($classLike->getConstants() as $constant) {
            if ($constant->getName() === $constName) {
                return \true;
            }
        }
        return \false;
    }
    private function findGlobalConstant(StubDataQueryInterface $stubs, string $fqn): bool
    {
        return $this->entityLookup->findConstantById($stubs, $fqn) !== null;
    }
}
