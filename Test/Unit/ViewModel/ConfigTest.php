<?php
declare(strict_types=1);

namespace Etechflow\RichSnippets\Test\Unit\ViewModel;

use Etechflow\RichSnippets\Model\LicenseValidator;
use Etechflow\RichSnippets\ViewModel\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Etechflow\RichSnippets\ViewModel\Config
 */
class ConfigTest extends TestCase
{
    /**
     * @param array<string,bool> $flags
     */
    private function makeConfig(array $flags, bool $licensed = true): Config
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path, $scope = null): bool => (bool)($flags[$path] ?? false)
        );
        $license = $this->createMock(LicenseValidator::class);
        $license->method('isValid')->willReturn($licensed);
        return new Config($scopeConfig, $license);
    }

    public function testEmitsNothingWhenUnlicensed(): void
    {
        $config = $this->makeConfig([
            'etechflow_richsnippets/general/enabled' => true,
            'etechflow_richsnippets/product/enabled' => true,
        ], licensed: false);
        $this->assertFalse($config->isEnabled('general'), 'general must be false when unlicensed');
        $this->assertFalse($config->isEnabled('product'), 'area must be false when unlicensed');
    }

    public function testEmitsNothingWhenMasterSwitchOff(): void
    {
        $config = $this->makeConfig([
            'etechflow_richsnippets/general/enabled' => false,
            'etechflow_richsnippets/product/enabled' => true,
        ]);
        $this->assertFalse($config->isEnabled('general'), 'general must be false when master is off');
        $this->assertFalse($config->isEnabled('product'), 'area must be false when master is off');
    }

    public function testGeneralTrueWhenMasterOn(): void
    {
        $config = $this->makeConfig(['etechflow_richsnippets/general/enabled' => true]);
        $this->assertTrue($config->isEnabled('general'));
        $this->assertTrue($config->isEnabled(), 'default area is general');
    }

    public function testAreaRequiresBothMasterAndAreaFlags(): void
    {
        $config = $this->makeConfig([
            'etechflow_richsnippets/general/enabled'  => true,
            'etechflow_richsnippets/product/enabled'  => true,
            'etechflow_richsnippets/category/enabled' => false,
        ]);
        $this->assertTrue($config->isEnabled('product'), 'product on when master+area on');
        $this->assertFalse($config->isEnabled('category'), 'category off when its area flag is off');
    }
}
