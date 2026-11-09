<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LocalesTest extends WebTestCase
{
    #[TestWith(['de', 'Meldung'])]
    #[TestWith(['fr', 'signalement'])]
    public function testPublicPagesRenderTranslated(string $locale, string $word): void
    {
        $client = self::createClient();

        foreach (['', '/reports', '/report'] as $path) {
            $client->request('GET', '/'.$locale.$path);
            self::assertResponseIsSuccessful($locale.$path);
            self::assertSame($locale, $client->getCrawler()->filter('html')->attr('lang'));
            self::assertStringContainsStringIgnoringCase($word, $client->getCrawler()->filter('main')->text(), $locale.$path);
            self::assertSelectorCount(4, 'header a[hreflang]');
        }
    }

    public function testRootRedirectsToTheBrowserLanguage(): void
    {
        $client = self::createClient();
        $client->request('GET', '/', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr-BE,fr;q=0.9']);

        self::assertResponseRedirects('/fr');
    }

    public function testUnsupportedLocaleIs404(): void
    {
        $client = self::createClient();
        $client->request('GET', '/es/report');

        self::assertResponseStatusCodeSame(404);
    }
}
