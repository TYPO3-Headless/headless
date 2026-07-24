<?php

/*
 * This file is part of the "headless" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FriendsOfTYPO3\Headless\Tests\Unit\Form\Finisher;

use FriendsOfTYPO3\Headless\Form\Finisher\JsonRedirectFinisher;
use FriendsOfTYPO3\Headless\Tests\Unit\HeadlessUnitTestCase;
use FriendsOfTYPO3\Headless\Utility\HeadlessFrontendUrlInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\RequestInterface;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Form\Domain\Finishers\FinisherContext;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Service\TranslationService;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

use function json_decode;

class JsonRedirectFinisherTest extends HeadlessUnitTestCase
{
    public function testReturnsJsonWithRelativeRedirectUrlAndMessage(): void
    {
        $contentObjectRenderer = $this->createMock(ContentObjectRenderer::class);
        $contentObjectRenderer->expects(self::once())->method('typoLink_URL')
            ->with(['parameter' => 2, 'additionalParams' => '&', 'forceAbsoluteUrl' => 1])
            ->willReturn('https://backend.tld/thanks');

        $finisherContext = $this->getFinisherContext($contentObjectRenderer);
        $finisherContext->expects(self::once())->method('cancel');

        $result = $this->getFinisher(['pageUid' => 2, 'message' => 'Thanks!'])->execute($finisherContext);

        self::assertSame(
            ['redirectUrl' => '/thanks', 'statusCode' => 303, 'message' => 'Thanks!'],
            json_decode((string)$result, true)
        );
    }

    public function testDefaultsToFirstPageWithoutMessage(): void
    {
        $contentObjectRenderer = $this->createMock(ContentObjectRenderer::class);
        $contentObjectRenderer->expects(self::once())->method('typoLink_URL')
            ->with(['parameter' => 1, 'additionalParams' => '&', 'forceAbsoluteUrl' => 1])
            ->willReturn('https://backend.tld/');

        $result = $this->getFinisher([])->execute($this->getFinisherContext($contentObjectRenderer));

        self::assertSame(
            ['redirectUrl' => 'https://backend.tld/', 'statusCode' => 303, 'message' => null],
            json_decode((string)$result, true)
        );
    }

    public function testSameSiteOnlyBlocksCrossSiteRedirect(): void
    {
        $contentObjectRenderer = $this->createMock(ContentObjectRenderer::class);
        $contentObjectRenderer->expects(self::once())->method('typoLink_URL')
            ->with(['parameter' => 1, 'additionalParams' => '&', 'forceAbsoluteUrl' => 1])
            ->willReturn('https://backend.tld/');

        $finisherContext = $this->getFinisherContext(
            $contentObjectRenderer,
            $this->getSite('main'),
            $this->getSiteFinderFor(5, $this->getSite('other'))
        );

        $this->getFinisher(['pageUid' => 5, 'sameSiteOnly' => true])->execute($finisherContext);
    }

    public function testSameSiteOnlyKeepsTargetWithinCurrentSite(): void
    {
        $contentObjectRenderer = $this->createMock(ContentObjectRenderer::class);
        $contentObjectRenderer->expects(self::once())->method('typoLink_URL')
            ->with(['parameter' => 5, 'additionalParams' => '&', 'forceAbsoluteUrl' => 1])
            ->willReturn('https://backend.tld/target');

        $finisherContext = $this->getFinisherContext(
            $contentObjectRenderer,
            $this->getSite('main'),
            $this->getSiteFinderFor(5, $this->getSite('main'))
        );

        $this->getFinisher(['pageUid' => 5, 'sameSiteOnly' => true])->execute($finisherContext);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function getFinisher(array $options): JsonRedirectFinisher
    {
        $translationService = $this->createMock(TranslationService::class);
        $translationService->method('translateFinisherOption')->willReturnArgument(3);

        $finisher = new JsonRedirectFinisher();
        $finisher->setFinisherIdentifier('JsonRedirect');
        $finisher->injectTranslationService($translationService);
        $finisher->setOptions($options);

        return $finisher;
    }

    private function getFinisherContext(
        ContentObjectRenderer&MockObject $contentObjectRenderer,
        ?Site $site = null,
        ?SiteFinder $siteFinder = null
    ): FinisherContext&MockObject {
        $serverRequest = (new ServerRequest())->withAttribute('currentContentObject', $contentObjectRenderer);

        if ($site !== null) {
            $serverRequest = $serverRequest->withAttribute('site', $site);
        }

        $extbaseRequest = $this->createMock(RequestInterface::class);
        $extbaseRequest->method('getAttribute')->willReturnCallback(
            static fn(string $name) => $name === 'extbase.request.originalRequest' ? $serverRequest : null
        );

        $formRuntime = $this->createMock(FormRuntime::class);
        $formRuntime->method('getRequest')->willReturn($extbaseRequest);

        $finisherContext = $this->createMock(FinisherContext::class);
        $finisherContext->method('getFormRuntime')->willReturn($formRuntime);

        $urlUtility = $this->createMock(HeadlessFrontendUrlInterface::class);
        $urlUtility->method('withRequest')->willReturnSelf();
        $urlUtility->method('prepareRelativeUrlIfPossible')->willReturnCallback(
            static fn(string $url): string => $url === 'https://backend.tld/thanks' ? '/thanks' : $url
        );

        $container = new Container();
        $container->set(UriBuilder::class, $this->createMock(UriBuilder::class));
        $container->set(HeadlessFrontendUrlInterface::class, $urlUtility);

        if ($siteFinder !== null) {
            $container->set(SiteFinder::class, $siteFinder);
        }

        GeneralUtility::setContainer($container);

        return $finisherContext;
    }

    private function getSite(string $identifier): Site&MockObject
    {
        $site = $this->createMock(Site::class);
        $site->method('getIdentifier')->willReturn($identifier);

        return $site;
    }

    private function getSiteFinderFor(int $pageUid, Site $targetSite): SiteFinder&MockObject
    {
        $siteFinder = $this->createMock(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->with($pageUid)->willReturn($targetSite);

        return $siteFinder;
    }
}
