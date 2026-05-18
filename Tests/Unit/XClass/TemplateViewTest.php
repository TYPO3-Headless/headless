<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\XClass;

use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\View\HeadlessPhpView;
use RuntimeException;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\View\ViewFactoryData;

use function json_encode;

/**
 * Behavioural counterpart of the retired XClass `\FriendsOfTYPO3\Headless\XClass\TemplateView`.
 *
 * The Fluid XClass that used to override TemplateView's `render()` is gone in
 * 5.x — its job (rendering raw `.php` templates from a JSON controller) is
 * now done by {@see \FriendsOfTYPO3\Headless\View\HeadlessPhpView}, dispatched
 * via {@see \FriendsOfTYPO3\Headless\View\HeadlessViewFactory} when the caller
 * sets `format='php'` on its `ViewFactoryData`.
 *
 * The old test exercised four cases (template missing, template renders with
 * assigned variables, exception during template execution, controller-action
 * handling). The first three carry over verbatim against the new view; the
 * "action" case is dropped because `HeadlessPhpView` is action-less by design.
 */
class TemplateViewTest extends HeadlessUnitTestCase
{
    /** @var list<string> */
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

    public function testRendersFixtureWithAssignedVariables(): void
    {
        $root = $this->createTemplateRoot();
        $this->writeTemplate($root, 'Default.php', <<<'PHP'
            <?php echo json_encode(['testKey' => $testValue]);
            PHP);

        $view = new HeadlessPhpView(new ViewFactoryData(templateRootPaths: [$root]));
        $view->assign('testValue', 'TestingJsonValue');

        self::assertSame(json_encode(['testKey' => 'TestingJsonValue']), $view->render('Default'));
    }

    public function testThrowsWhenTemplateFileMissing(): void
    {
        $view = new HeadlessPhpView(new ViewFactoryData(
            templateRootPaths: [$this->createTemplateRoot()],
        ));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(1747300000);
        $view->render('Default');
    }

    public function testThrowsWhenNoTemplateRootIsConfigured(): void
    {
        $view = new HeadlessPhpView(new ViewFactoryData());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(1747300000);
        $view->render('Default');
    }

    public function testExceptionRaisedByTemplateBodyPropagates(): void
    {
        $root = $this->createTemplateRoot();
        $this->writeTemplate($root, 'DefaultException.php', <<<'PHP'
            <?php throw new \RuntimeException('Example exception in template');
            PHP);

        $view = new HeadlessPhpView(new ViewFactoryData(templateRootPaths: [$root]));

        $initialObLevel = ob_get_level();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Example exception in template');

        try {
            $view->render('DefaultException');
        } finally {
            self::assertSame($initialObLevel, ob_get_level(), 'output buffer must be cleaned up after a template exception');
        }
    }

    private function createTemplateRoot(): string
    {
        // Must live under publicPath/typo3temp/ to satisfy
        // GeneralUtility::isAllowedAbsPath used by HeadlessPhpView's resolver.
        $dir = Environment::getPublicPath() . '/typo3temp/var/tests/headless_xclass_view_' . uniqid('', true);
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
