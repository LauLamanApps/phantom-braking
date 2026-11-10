<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SeoTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testRobotsTxtPointsAtTheSitemapAndKeepsCrawlersOutOfTheAdmin(): void
    {
        $this->client->request('GET', '/robots.txt');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=UTF-8');
        $body = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Disallow: /admin', $body);
        self::assertStringContainsString('Sitemap: http://localhost/sitemap.xml', $body);
    }

    public function testSitemapListsEveryPageInEveryLanguage(): void
    {
        $this->client->request('GET', '/sitemap.xml');

        self::assertResponseIsSuccessful();
        $xml = new \SimpleXMLElement((string) $this->client->getResponse()->getContent());
        $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xml->registerXPathNamespace('x', 'http://www.w3.org/1999/xhtml');
        $xpath = static fn (string $query): array => $xml->xpath($query) ?: [];
        $locs = array_map('strval', $xpath('//s:loc'));

        self::assertCount(9 * 4, $locs);
        self::assertContains('http://localhost/de/faq', $locs);
        self::assertContains('http://localhost/fr/privacy', $locs);
        self::assertNotContains('http://localhost/admin', $locs);
        self::assertSame('http://localhost/', (string) ($xpath('//s:url[s:loc="http://localhost/nl"]/x:link[@hreflang="x-default"]/@href')[0] ?? ''));
        self::assertCount(5, $xpath('//s:url[s:loc="http://localhost/nl/about"]/x:link'));
    }

    #[TestWith(['nl', 'nl_NL', 'Phantom braking melden en statistieken · Meldpunt spook-remmen'])]
    #[TestWith(['fr', 'fr_FR', 'Freinages fantômes : signalements et statistiques · Point de signalement des freinages fantômes'])]
    public function testHomePageHasSearchAndShareMetadata(string $locale, string $ogLocale, string $title): void
    {
        $crawler = $this->client->request('GET', '/'.$locale.'?country=NL');

        self::assertResponseIsSuccessful();
        self::assertPageTitleSame($title);
        self::assertSame('http://localhost/'.$locale, $crawler->filter('link[rel="canonical"]')->attr('href'));
        self::assertSame('http://localhost/', $crawler->filter('link[hreflang="x-default"]')->attr('href'));
        self::assertCount(5, $crawler->filter('link[rel="alternate"][hreflang]'));
        self::assertSame($ogLocale, $crawler->filter('meta[property="og:locale"]')->attr('content'));
        self::assertSame('website', $crawler->filter('meta[property="og:type"]')->attr('content'));
        self::assertStringStartsWith('http://localhost/assets/images/og/'.$locale.'-', (string) $crawler->filter('meta[property="og:image"]')->attr('content'));
        self::assertSame('summary_large_image', $crawler->filter('meta[name="twitter:card"]')->attr('content'));
        self::assertNotEmpty($crawler->filter('meta[property="og:description"]')->attr('content'));

        $data = json_decode($crawler->filter('script[type="application/ld+json"]')->text(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['Organization', 'WebSite'], array_column($data['@graph'], '@type'));
    }

    public function testPagingStaysInTheCanonicalUrlButFiltersDoNot(): void
    {
        $crawler = $this->client->request('GET', '/en/reports?make=TESLA&page=2');

        self::assertResponseIsSuccessful();
        self::assertSame('http://localhost/en/reports?page=2', $crawler->filter('link[rel="canonical"]')->attr('href'));
        self::assertSame('http://localhost/en/reports', $crawler->filter('link[hreflang="x-default"]')->attr('href'));
    }

    public function testEachPageHasItsOwnDescription(): void
    {
        $descriptions = [];
        foreach (['/en', '/en/reports', '/en/report', '/en/about', '/en/faq'] as $path) {
            $descriptions[] = $this->client->request('GET', $path)->filter('meta[name="description"]')->attr('content');
        }

        self::assertCount(5, array_unique($descriptions));
    }
}
