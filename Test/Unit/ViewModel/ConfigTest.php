<?php
declare(strict_types=1);

namespace Etechflow\RichSnippets\Test\Unit\ViewModel;

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
    private function makeConfig(array $flags): Config
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path, $scope = null): bool => (bool)($flags[$path] ?? false)
        );
        return new Config($scopeConfig);
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
