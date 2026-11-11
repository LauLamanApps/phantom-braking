<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Info\Presentation\Controller\ShowFaqController;
use App\Info\Presentation\Controller\ShowGlossaryController;
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

    #[TestWith(['nl', 'Begrippenlijst', 'Kop-staartbotsing'])]
    #[TestWith(['en', 'Glossary', 'Rear-end collision'])]
    #[TestWith(['de', 'Glossar', 'Auffahrunfall'])]
    #[TestWith(['fr', 'Glossaire', 'Collision par l’arrière'])]
    public function testGlossaryExplainsEveryTermAlphabeticallyWithStructuredData(string $locale, string $title, string $term): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/'.$locale.'/glossary');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $title);
        self::assertSelectorExists(sprintf('header a[href="/%s/glossary"][aria-current]', $locale));
        self::assertSelectorCount(\count(ShowGlossaryController::TERMS), 'dl > div[id]');
        self::assertSelectorTextContains('#rear_end dt', $term);
        self::assertSelectorTextNotContains('main dl', 'glossary.');

        $terms = $crawler->filter('dl dt')->each(static fn ($dt) => mb_strtolower($dt->text()));
        $sorted = $terms;
        sort($sorted);
        self::assertSame($sorted, $terms);

        $data = json_decode($crawler->filter('script[type="application/ld+json"]')->text(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('DefinedTermSet', $data['@type']);
        self::assertCount(\count(ShowGlossaryController::TERMS), $data['hasDefinedTerm']);
        self::assertStringEndsWith('/'.$locale.'/glossary#rear_end', array_column($data['hasDefinedTerm'], 'url', 'name')[$term]);
    }

    public function testMenuHoldsFaqAndAboutAndTheFooterTheLegalPagesChangelogAndSource(): void
    {
        $client = self::createClient();
        $client->request('GET', '/en');

        foreach (['/en/changelog', '/en/privacy', '/en/cookies', '/en/terms', 'https://github.com/LauLamanApps/phantom-braking'] as $href) {
            self::assertSelectorExists(sprintf('footer a[href="%s"]', $href));
        }
        foreach (['/en/faq', '/en/glossary', '/en/about'] as $href) {
            self::assertSelectorExists(sprintf('header a[href="%s"]', $href));
            self::assertSelectorNotExists(sprintf('footer a[href="%s"]', $href));
        }
    }

    public function testChangelogShowsTheReleasesFromChangelogMd(): void
    {
        $client = self::createClient();
        $client->request('GET', '/nl/changelog');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Wijzigingen');
        self::assertSelectorExists('article h2:contains("1.0.0")');
        self::assertSelectorExists('article h3:contains("Added")');
        // The file's own title is not repeated on the page (an empty "Unreleased" section is left out too).
        self::assertSelectorNotExists('article h1:contains("Changelog")');
    }
}
