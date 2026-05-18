<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\View;

use RuntimeException;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewInterface;

use function array_replace;
use function array_reverse;
use function extract;
use function is_file;
use function ltrim;
use function ob_end_clean;
use function ob_get_clean;
use function ob_get_level;
use function ob_start;
use function preg_match;
use function realpath;
use function rtrim;
use function str_starts_with;

final class HeadlessPhpView implements ViewInterface
{
    /** @var array<string, mixed> */
    private array $variables = [];

    /** @var list<string>|null */
    private ?array $resolvedRoots = null;

    public function __construct(private readonly ViewFactoryData $data) {}

    public function assign(string $key, mixed $value): self
    {
        $this->variables[$key] = $value;
        return $this;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function assignMultiple(array $values): self
    {
        $this->variables = array_replace($this->variables, $values);
        return $this;
    }

    public function render(string $templateFileName = ''): string
    {
        $templateFile = $this->resolvePhpTemplate($templateFileName);
        if ($templateFile === null) {
            throw new RuntimeException(
                'Headless PHP template "' . $templateFileName . '" could not be resolved.',
                1747300000
            );
        }

        try {
            extract($this->variables, EXTR_SKIP);
            ob_start();
            include $templateFile;
            return (string)ob_get_clean();
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            throw $e;
        }
    }

    private function resolvePhpTemplate(string $name): ?string
    {
        if ($name === '') {
            return $this->resolveDirectFile();
        }

        if (!$this->isSafeTemplateName($name)) {
            return null;
        }

        $resolvedRoots = $this->resolvedTemplateRoots();
        if ($resolvedRoots === []) {
            return null;
        }

        $relative = ltrim($name, '/') . '.php';

        foreach (array_reverse($resolvedRoots) as $root) {
            $candidate = $root . '/' . $relative;
            if (!is_file($candidate)) {
                continue;
            }
            $real = realpath($candidate);
            if ($real === false) {
                continue;
            }
            if ($real === $root || str_starts_with($real, $root . '/')) {
                return $real;
            }
        }

        return null;
    }

    private function resolveDirectFile(): ?string
    {
        $direct = $this->data->templatePathAndFilename;
        if ($direct === null || $direct === '') {
            return null;
        }
        // getFileAbsFileName resolves EXT:, asserts allowed roots and runs validPathStr.
        $absolute = GeneralUtility::getFileAbsFileName($direct);
        if ($absolute === '' || !is_file($absolute)) {
            return null;
        }
        $real = realpath($absolute);
        return $real === false ? null : $real;
    }

    private function isSafeTemplateName(string $name): bool
    {
        if ($name[0] === '/'
            || preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*:#', $name) === 1) {
            return false;
        }
        return GeneralUtility::validPathStr($name);
    }

    /**
     * @return list<string> canonical absolute roots without trailing slash
     */
    private function resolvedTemplateRoots(): array
    {
        if ($this->resolvedRoots !== null) {
            return $this->resolvedRoots;
        }
        $roots = [];
        foreach ($this->data->templateRootPaths ?? [] as $root) {
            if (!is_string($root) || $root === '') {
                continue;
            }
            $absolute = GeneralUtility::getFileAbsFileName($root);
            if ($absolute === '') {
                continue;
            }
            $real = realpath($absolute);
            if ($real === false) {
                continue;
            }
            $roots[] = rtrim($real, '/');
        }
        return $this->resolvedRoots = $roots;
    }
}
