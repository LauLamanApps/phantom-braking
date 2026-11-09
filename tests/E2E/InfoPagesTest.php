<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Info\Presentation\Controller\ShowFaqController;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InfoPagesTest extends WebTestCase
{
    #[TestWith(['nl', 'Over het meldpunt'])]
    #[TestWith(['en', 'About'])]
    public function testAboutPageHoldsWhatUsedToBeInTheFooter(string $locale, string $title): void
    {
        $client = self::createClient();
        $client->request('GET', '/'.$locale.'/about');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $title);
        self::assertSelectorExists('main a[href="https://opendata.rdw.nl"]');
        self::assertSelectorExists('main a[href="https://github.com/LauLamanApps/phantom-braking"]');
    }

    public function testFaqListsEveryQuestionWithStructuredData(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/en/faq');

        self::assertResponseIsSuccessful();
        $count = \count(array_merge(...array_values(ShowFaqController::QUESTIONS)));
        self::assertSelectorCount($count, 'details.faq-item');
        self::assertSelectorExists('details#plate');
        self::assertSelectorExists('details#withdraw a[href="/en/reports"]');

        $data = json_decode($crawler->filter('script[type="application/ld+json"]')->text(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('FAQPage', $data['@type']);
        self::assertCount($count, $data['mainEntity']);
        self::assertSame('Do you store my licence plate?', $data['mainEntity'][array_search('plate', array_merge(...array_values(ShowFaqController::QUESTIONS)), true)]['name']);
        self::assertStringNotContainsString('<a', $data['mainEntity'][0]['acceptedAnswer']['text']);
    }

    public function testFooterLinksToAboutFaqLegalAndSource(): void
    {
        $client = self::createClient();
        $client->request('GET', '/en');

        foreach (['/en/about', '/en/faq', '/en/privacy', '/en/cookies', '/en/terms', 'https://github.com/LauLamanApps/phantom-braking'] as $href) {
            self::assertSelectorExists(sprintf('footer a[href="%s"]', $href));
        }
        self::assertSelectorExists('header a[href="/en/faq"]');
    }
}
