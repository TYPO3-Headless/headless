<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\View;

use FriendsOfTYPO3\Headless\View\HeadlessPhpView;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class HeadlessPhpViewTest extends UnitTestCase
{
    /** @var string[] */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
            $dir = dirname($file);
            if (is_dir($dir) && str_starts_with($dir, Environment::getPublicPath() . '/typo3temp/')) {
                @rmdir($dir);
            }
        }
        $this->tempFiles = [];
        parent::tearDown();
    }

    public function testRendersAssignedVariablesFromTemplateRoot(): void
    {
        $root = $this->createTemplateRoot();
        $this->writeTemplate($root, 'Greeting.php', '<?php echo "hello {$name}";');

        $view = new HeadlessPhpView(new ViewFactoryData(templateRootPaths: [$root]));
        $view->assign('name', 'world');

        self::assertSame('hello world', $view->render('Greeting'));
    }

    public function testAssignMultipleMergesVariables(): void
    {
        $root = $this->createTemplateRoot();
        $this->writeTemplate($root, 'Pair.php', '<?php echo $a . "|" . $b;');

        $view = new HeadlessPhpView(new ViewFactoryData(templateRootPaths: [$root]));
        $view->assign('a', 'first');
        $view->assignMultiple(['b' => 'second']);

        self::assertSame('first|second', $view->render('Pair'));
    }

    public function testRendersDirectTemplatePathAndFilename(): void
    {
        $root = $this->createTemplateRoot();
        $file = $this->writeTemplate($root, 'Direct.php', '<?php echo "direct";');

        $view = new HeadlessPhpView(new ViewFactoryData(templatePathAndFilename: $file));

        self::assertSame('direct', $view->render());
    }

    public function testLastTemplateRootWins(): void
    {
        $primary = $this->createTemplateRoot();
        $override = $this->createTemplateRoot();
        $this->writeTemplate($primary, 'Same.php', '<?php echo "primary";');
        $this->writeTemplate($override, 'Same.php', '<?php echo "override";');

        $view = new HeadlessPhpView(new ViewFactoryData(templateRootPaths: [$primary, $override]));

        self::assertSame('override', $view->render('Same'));
    }

    #[DataProvider('unsafeNamesProvider')]
    public function testRejectsPathTraversalAttempts(string $unsafeName): void
    {
        $root = $this->createTemplateRoot();
        // A file that an attacker would target if traversal worked.
        // For "../escape" the resolver would build "{root}/../escape.php".
        $outsideDir = dirname(rtrim($root, '/'));
        $outsideFile = $outsideDir . '/escape.php';
        file_put_contents($outsideFile, '<?php echo "pwned";');
        $this->tempFiles[] = $outsideFile;

        $view = new HeadlessPhpView(new ViewFactoryData(templateRootPaths: [$root]));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(1747300000);
        try {
            $view->render($unsafeName);
        } finally {
            @unlink($outsideFile);
        }
    }

    /**
     * @return array<string, array{0:string}>
     */
    public static function unsafeNamesProvider(): array
    {
        return [
            'parent segment'           => ['../escape'],
            'embedded parent segment'  => ['foo/../../escape'],
            'absolute path'            => ['/etc/passwd'],
            'backslash separator'      => ['..\\escape'],
            'NUL byte'                 => ["escape\0"],
            'stream wrapper'           => ['phar://attacker.phar/payload'],
            'file wrapper'             => ['file:///etc/passwd'],
            'double slash'             => ['foo//../escape'],
        ];
    }

    public function testThrowsWhenTemplateMissing(): void
    {
        $view = new HeadlessPhpView(new ViewFactoryData(
            templateRootPaths: [$this->createTemplateRoot()],
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(1747300000);
        $view->render('Does/Not/Exist');
    }

    public function testCleansOutputBufferWhenTemplateThrows(): void
    {
        $root = $this->createTemplateRoot();
        $this->writeTemplate(
            $root,
            'Broken.php',
            '<?php echo "leaked"; throw new \RuntimeException("boom");'
        );

        $view = new HeadlessPhpView(new ViewFactoryData(templateRootPaths: [$root]));

        $initialObLevel = ob_get_level();

        try {
            $view->render('Broken');
            self::fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
            self::assertSame($initialObLevel, ob_get_level(), 'output buffer must be cleaned up');
        }
    }

    private function createTemplateRoot(): string
    {
        // Must live under publicPath/typo3temp/ so it satisfies
        // GeneralUtility::isAllowedAbsPath (publicPath / projectPath / lockRootPath).
        $dir = Environment::getPublicPath() . '/typo3temp/var/tests/headless_view_' . uniqid('', true);
        mkdir($dir, 0777, true);
        return $dir . '/';
    }

    private function writeTemplate(string $root, string $name, string $contents): string
    {
        $file = $root . $name;
        file_put_contents($file, $contents);
        $this->tempFiles[] = $file;
        return $file;
    }
}
