<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

final class ThemeTest extends WebTestCase
{
    public function testWithoutAChoiceTheDeviceDecides(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/en');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('html[data-theme]');
        self::assertSelectorExists('header button[data-theme-switch-choice-param="system"][aria-current]');
        self::assertSelectorTextContains('header button[data-theme-switch-choice-param="dark"]', 'Dark');
        self::assertSame(
            ['#ffffff', '#141110'],
            $crawler->filter('meta[name="theme-color"]')->each(static fn ($meta) => $meta->attr('content')),
        );
    }

    #[TestWith(['light', '#ffffff'])]
    #[TestWith(['dark', '#141110'])]
    public function testAChosenThemeIsRenderedByTheServer(string $theme, string $barColour): void
    {
        $client = self::createClient();
        $client->getCookieJar()->set(new Cookie('theme', $theme));
        $crawler = $client->request('GET', '/nl/reports');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf('html[data-theme="%s"]', $theme));
        self::assertSelectorExists(sprintf('header button[data-theme-switch-choice-param="%s"][aria-current]', $theme));
        self::assertSelectorNotExists('header button[data-theme-switch-choice-param="system"][aria-current]');
        self::assertSame(
            [$barColour, $barColour],
            $crawler->filter('meta[name="theme-color"]')->each(static fn ($meta) => $meta->attr('content')),
        );
    }

    public function testAnUnknownThemeCookieIsIgnored(): void
    {
        $client = self::createClient();
        $client->getCookieJar()->set(new Cookie('theme', '"><script>'));
        $client->request('GET', '/en');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('html[data-theme]');
    }
}
