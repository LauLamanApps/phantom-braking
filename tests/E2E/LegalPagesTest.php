<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LegalPagesTest extends WebTestCase
{
    #[TestWith(['nl', 'privacy', 'Privacyverklaring'])]
    #[TestWith(['nl', 'cookies', 'Cookieverklaring'])]
    #[TestWith(['nl', 'terms', 'Gebruiksvoorwaarden'])]
    #[TestWith(['en', 'privacy', 'Privacy statement'])]
    #[TestWith(['en', 'cookies', 'Cookie notice'])]
    #[TestWith(['en', 'terms', 'Terms of use'])]
    #[TestWith(['de', 'privacy', 'Datenschutzerklärung'])]
    #[TestWith(['de', 'cookies', 'Cookie-Hinweis'])]
    #[TestWith(['de', 'terms', 'Nutzungsbedingungen'])]
    #[TestWith(['fr', 'privacy', 'Politique de confidentialité'])]
    #[TestWith(['fr', 'cookies', 'Politique relative aux cookies'])]
    #[TestWith(['fr', 'terms', 'Conditions d’utilisation'])]
    public function testPagesRenderInEveryLanguage(string $locale, string $page, string $title): void
    {
        $client = self::createClient();
        $client->request('GET', sprintf('/%s/%s', $locale, $page));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('article h1', $title);
        // Operator details are not configured in the test env: the gap must be visible, not silently blank.
        self::assertSelectorExists('article .rounded-lg.border-yellow-300');
    }

    public function testFooterAndReportFormLinkToTheDocuments(): void
    {
        $client = self::createClient();

        $client->request('GET', '/en');
        foreach (['privacy', 'cookies', 'terms'] as $page) {
            self::assertSelectorExists(sprintf('footer a[href="/en/%s"]', $page));
        }

        $client->request('GET', '/en/report');
        self::assertSelectorExists('label a[href="/en/privacy"]');
        self::assertSelectorExists('label a[href="/en/terms"]');
    }

    public function testBrowsingSetsNoCookies(): void
    {
        $client = self::createClient();
        foreach (['/en', '/en/reports', '/en/report', '/en/privacy'] as $url) {
            $client->request('GET', $url);
            self::assertSame([], $client->getResponse()->headers->getCookies(), $url.' must not set cookies');
        }
    }

    public function testErrorMonitoringIsConfiguredNotToSendPersonalData(): void
    {
        self::bootKernel();
        $options = self::getContainer()->get(\Sentry\State\HubInterface::class)->getClient()?->getOptions();
        self::assertNotNull($options);
        self::assertSame('never', $options->getMaxRequestBodySize(), 'request bodies contain plates');
        self::assertFalse($options->shouldSendDefaultPii(), 'no IPs or cookies');
        // The configured before_send really is our scrubber (Sentry's default passes events through untouched).
        $event = \Sentry\Event::createEvent();
        $event->setMessage('GET https://opendata.rdw.nl/resource/m9d7-ebf2.json?kenteken=GVN31K failed');
        self::assertStringNotContainsString('GVN31K', (string) ($options->getBeforeSendCallback())($event, null)?->getMessage());
    }
}
