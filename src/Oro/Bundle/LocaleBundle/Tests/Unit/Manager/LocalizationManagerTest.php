<?php

namespace Oro\Bundle\LocaleBundle\Tests\Unit\Provider;

use Doctrine\Common\Persistence\ObjectRepository;

use Oro\Bundle\ConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\LocaleBundle\DependencyInjection\Configuration;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\LocaleBundle\Helper\LocalizationCacheHelper;
use Oro\Bundle\LocaleBundle\Manager\LocalizationManager;

use Oro\Component\Testing\Unit\EntityTrait;

class LocalizationManagerTest extends \PHPUnit_Framework_TestCase
{
    use EntityTrait;

    /** @var ObjectRepository|\PHPUnit_Framework_MockObject_MockObject */
    protected $repository;

    /** @var DoctrineHelper|\PHPUnit_Framework_MockObject_MockObject */
    protected $doctrineHelper;

    /** @var LocalizationManager */
    protected $manager;

    /** @var ConfigManager|\PHPUnit_Framework_MockObject_MockObject */
    protected $configManager;

    /** @var LocalizationCacheHelper|\PHPUnit_Framework_MockObject_MockObject */
    protected $localizationCacheHelper;

    /** @var Localization[]|array */
    protected $entities = [];

    public function setUp()
    {
        $this->repository = $this->getMockBuilder(ObjectRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->doctrineHelper = $this->getMockBuilder(DoctrineHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->configManager = $this->getMockBuilder(ConfigManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->localizationCacheHelper = $this->getMockBuilder(LocalizationCacheHelper::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var Localization[] $entities */
        $this->entities = [
            1 => $this->getEntity(Localization::class, ['id' => 1]),
            3 => $this->getEntity(Localization::class, ['id' => 3]),
            2 => $this->getEntity(Localization::class, ['id' => 2]),
        ];

        $this->manager = new LocalizationManager(
            $this->doctrineHelper,
            $this->configManager,
            $this->localizationCacheHelper
        );
    }

    public function tearDown()
    {
        unset(
            $this->repository,
            $this->manager,
            $this->configManager,
            $this->entities
        );
    }

    public function testGetLocalization()
    {
        /** @var Localization $entity */
        $entity = $this->getEntity(Localization::class, ['id' => 1]);

        $this->assertGetEntityRepositoryForClassIsCalled();
        $this->assertCacheReads(false);
        $this->assertRepositoryCalls();

        $result = $this->manager->getLocalization($entity->getId());

        $this->assertEquals($entity, $result);
    }

    public function testGetLocalizations()
    {
        $this->assertGetEntityRepositoryForClassIsCalled();
        $this->assertRepositoryCalls();
        $this->assertCacheReads(false);

        $result = $this->manager->getLocalizations();

        $this->assertEquals($this->entities, $result);
    }

    public function testGetLocalizationsCached()
    {
        $this->assertGetEntityRepositoryForClassIsNotCalled();
        $this->assertCacheReads($this->entities);

        $result = $this->manager->getLocalizations();

        $this->assertEquals($this->entities, $result);
    }

    public function testGetLocalizationsByIds()
    {
        $this->assertGetEntityRepositoryForClassIsCalled();
        $this->assertRepositoryCalls();
        $this->assertCacheReads(false);

        /** @var Localization[] $entities */
        $entities = [
            1 => $this->getEntity(Localization::class, ['id' => 1]),
            3 => $this->getEntity(Localization::class, ['id' => 3]),
        ];

        $ids = [1, '3'];

        $result = $this->manager->getLocalizations((array)$ids);

        $this->assertEquals($entities, $result);
    }

    public function testGetDefaultLocalization()
    {
        $this->assertGetEntityRepositoryForClassIsCalled();
        $this->assertCacheReads(false);

        $localization1 = $this->getEntity(Localization::class, ['id' => 1]);
        $localization2 = $this->getEntity(Localization::class, ['id' => 2]);

        $this->repository->expects($this->never())->method('find');
        $this->repository->expects($this->once())->method('findBy')
            ->willReturn([1 => $localization1, 2 => $localization2]);

        $this->configManager->expects($this->once())
            ->method('get')
            ->with(Configuration::getConfigKeyByName(Configuration::DEFAULT_LOCALIZATION))
            ->willReturn('1');

        $this->assertSame($localization1, $this->manager->getDefaultLocalization());
    }

    public function testGetFirstLocalizationWhenNoDefaultLocalizationExist()
    {
        $this->assertGetEntityRepositoryForClassIsCalled();
        $this->assertCacheReads(false);

        $localization1 = $this->getEntity(Localization::class, ['id' => 1]);
        $localization2 = $this->getEntity(Localization::class, ['id' => 2]);

        $this->configManager->expects($this->once())
            ->method('get')
            ->with(Configuration::getConfigKeyByName(Configuration::DEFAULT_LOCALIZATION))
            ->willReturn(false);

        $this->repository->expects($this->once())->method('findBy')
            ->willReturn([1 => $localization1, 2 => $localization2]);

        $this->assertSame($localization1, $this->manager->getDefaultLocalization());
    }

    public function testDefaultLocalizationIsNullWhenNoLocalizationsExist()
    {
        $this->assertGetEntityRepositoryForClassIsCalled();
        $this->assertCacheReads(false);
        $this->repository->expects($this->once())->method('findBy')->willReturn([]);

        $this->configManager->expects($this->once())
            ->method('get')
            ->with(Configuration::getConfigKeyByName(Configuration::DEFAULT_LOCALIZATION))
            ->willReturn(false);

        $this->assertNull($this->manager->getDefaultLocalization());
    }

    public function testGetFirstLocalizationWhenUnknownDefaultLocalizationReturnedFromConfig()
    {
        $this->assertGetEntityRepositoryForClassIsCalled();
        $this->assertCacheReads(false);

        $localization1 = $this->getEntity(Localization::class, ['id' => 1]);
        $localization2 = $this->getEntity(Localization::class, ['id' => 2]);

        $this->configManager->expects($this->once())
            ->method('get')
            ->with(Configuration::getConfigKeyByName(Configuration::DEFAULT_LOCALIZATION))
            ->willReturn('13');

        $this->repository->expects($this->once())->method('findBy')
            ->willReturn([1 => $localization1, 2 => $localization2]);

        $this->assertSame($localization1, $this->manager->getDefaultLocalization());
    }

    protected function assertRepositoryCalls()
    {
        $this->repository->expects($this->never())->method('find');
        $this->repository->expects($this->once())->method('findBy')->willReturn($this->entities);
    }

    /**
     * @param $results
     */
    protected function assertCacheReads($results)
    {
        $this->localizationCacheHelper->expects($this->once())
            ->method('get')
            ->willReturn($results);
    }

    private function assertGetEntityRepositoryForClassIsCalled()
    {
        $this->doctrineHelper->expects($this->once())
            ->method('getEntityRepositoryForClass')
            ->with(Localization::class)
            ->willReturn($this->repository);
    }

    private function assertGetEntityRepositoryForClassIsNotCalled()
    {
        $this->doctrineHelper->expects($this->never())
            ->method('getEntityRepositoryForClass');
    }
}
