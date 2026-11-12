<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Incident\Domain\Command\ModerateFootageCommand;
use App\Incident\Domain\Command\ModeratePhotoCommand;
use App\Incident\Domain\Command\RecordMeasuredConditionsCommand;
use App\Incident\Domain\Event\IncidentReportedEvent;
use App\Incident\Domain\Model\Incident;
use App\Incident\Domain\Service\WeatherHistoryInterface;
use App\Incident\Domain\ValueObject\IncidentId;
use App\SharedKernel\Domain\Messenger\Bus\CommandBusInterface;
use App\SharedKernel\Domain\Model\RenamedId;
use App\Tests\Double\FakeWeatherHistory;
use App\Tests\Support\JpegFixture;
use App\Tests\Support\ResetsTestStorage;
use App\Vehicle\Domain\ValueObject\Country;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class ReportIncidentTest extends WebTestCase
{
    use ResetsTestStorage;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        self::resetTestStorage();
    }

    public function testHomePageWithoutReportsShowsEmptyState(): void
    {
        $this->client->request('GET', '/nl');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Nog geen meldingen');
    }

    public function testReportWithFootageIsStoredWithoutPlateAndShowsInStatistics(): void
    {
        $crawler = $this->client->request('GET', '/en/report');
        self::assertResponseIsSuccessful();
        // No separate checkbox: uploading is the consent, and the form says so next to the upload fields.
        self::assertSelectorNotExists('input[name="report_incident[mediaPublicationConsent]"]');
        self::assertSelectorTextContains('main', 'By uploading files, you consent to them being published after review.');

        $video = sys_get_temp_dir().'/phantom-test-'.uniqid().'.mp4';
        copy(__DIR__.'/../fixtures/dashcam.mp4', $video);

        $this->submitWithFiles($crawler, $this->validValues('te-123-s'), [
            'footage' => new UploadedFile($video, 'clip.mp4', 'video/mp4', test: true),
            'photos' => [
                new UploadedFile(JpegFixture::create(400, 300, 6), 'IMG_0001.jpg', 'image/jpeg', test: true),
                new UploadedFile(JpegFixture::create(300, 200), 'IMG_0002.jpg', 'image/jpeg', test: true),
            ],
        ]);

        // A 2024 car must have AEB, so the reporter is first asked to report it to the ILT too.
        self::assertResponseRedirects();
        $this->client->followRedirect();
        $this->client->submitForm('Not now');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'TESLA');
        self::assertSelectorTextContains('body', 'being reviewed');
        self::assertSelectorTextNotContains('body', 'TE123S');

        /** @var Incident $incident */
        $incident = $this->em->getRepository(Incident::class)->findOneBy([]);
        self::assertSame('MODEL 3', $incident->getVehicle()->getModel());
        self::assertSame('A2', $incident->getLocation()->getRoadNumber());
        self::assertSame(64, \strlen($incident->getPlateFingerprint()));
        self::assertNotSame('TE123S', $incident->getPlateFingerprint());
        self::assertTrue($incident->getFootage()->exists());
        self::assertFileExists(self::getContainer()->getParameter('kernel.project_dir').'/var/storage/footage_test/'.$incident->getFootage()->getPath());

        // Photos: stored re-encoded and upright, without any of the original metadata, hidden until approved.
        $photos = $incident->getPhotos();
        self::assertCount(2, $photos);
        $storageDir = self::getContainer()->getParameter('kernel.project_dir').'/var/storage/footage_test/';
        $stored = (string) file_get_contents($storageDir.$photos[0]->getPath());
        self::assertStringNotContainsString(JpegFixture::SECRET, $stored);
        self::assertStringNotContainsString('Exif', $stored);
        self::assertSame([300, 400], [$photos[0]->getWidth(), $photos[0]->getHeight()]);
        self::assertSame([], glob(self::getContainer()->getParameter('kernel.project_dir').'/var/storage/staging/'.$incident->getId().'*') ?: []);

        $this->client->request('GET', '/photos/'.$photos[0]->getId());
        self::assertResponseStatusCodeSame(404);
        self::getContainer()->get(CommandBusInterface::class)->dispatch(new ModeratePhotoCommand($photos[0]->getId(), true));
        $this->client->request('GET', '/photos/'.$photos[0]->getId());
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/jpeg');
        $this->client->request('GET', '/photos/'.$photos[1]->getId());
        self::assertResponseStatusCodeSame(404);

        $page = $this->client->request('GET', '/en/reports/'.$incident->getId());
        self::assertSelectorCount(1, 'img[src^="/photos/"]');
        self::assertSelectorTextContains('main', '1 photo was submitted that is not (yet) public.');

        // Link previews use the approved, plate-masked photo; the sitemap lists the report in every language.
        self::assertSame('article', $page->filter('meta[property="og:type"]')->attr('content'));
        self::assertSame('http://localhost/photos/'.$photos[0]->getId(), $page->filter('meta[property="og:image"]')->attr('content'));
        self::assertStringStartsWith('Phantom braking in a TESLA', (string) $page->filter('meta[name="description"]')->attr('content'));
        self::assertStringNotContainsStringIgnoringCase('te-123-s', (string) $this->client->getResponse()->getContent());
        $this->client->request('GET', '/sitemap.xml');
        self::assertSame(4, substr_count((string) $this->client->getResponse()->getContent(), '/reports/'.$incident->getId().'</loc>'));

        // Footage is hidden until approved.
        $this->client->request('GET', '/footage/'.$incident->getId());
        self::assertResponseStatusCodeSame(404);

        self::getContainer()->get(CommandBusInterface::class)->dispatch(new ModerateFootageCommand($incident->getId(), true));
        $this->client->request('GET', '/footage/'.$incident->getId());
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'video/mp4');

        // Video seeking: byte ranges are served as 206 with the right bytes.
        $video = (string) file_get_contents(__DIR__.'/../fixtures/dashcam.mp4');
        $this->client->request('GET', '/footage/'.$incident->getId(), server: ['HTTP_RANGE' => 'bytes=10-19']);
        self::assertResponseStatusCodeSame(206);
        self::assertResponseHeaderSame('Content-Range', sprintf('bytes 10-19/%d', \strlen($video)));
        self::assertSame(substr($video, 10, 10), $this->client->getInternalResponse()->getContent());
        $this->client->request('GET', '/footage/'.$incident->getId(), server: ['HTTP_RANGE' => 'bytes=999999-']);
        self::assertResponseStatusCodeSame(416);

        // Statistics reflect the report.
        $this->client->request('GET', '/en');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'endangered the car behind');
        self::assertSelectorTextContains('body', 'TESLA');

        $this->client->request('GET', '/api/incidents/map?country=NL');
        $points = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(1, $points);
        self::assertSame(52.0907, $points[0]['latitude']);

        // Deletion request on the report page: a wrong plate does nothing, the right plate files a request.
        $page = $this->client->request('GET', '/en/reports/'.$incident->getId());
        $this->client->submit($page->filter('#withdraw form')->form(), ['withdraw_media[licensePlate]' => 'VW001X']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', "This plate doesn't belong to this report.");

        $page = $this->client->request('GET', '/en/reports/'.$incident->getId());
        $this->client->submit($page->filter('#withdraw form')->form(), ['withdraw_media[licensePlate]' => 'te 123 s']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', "We've received your request.");
        self::assertSelectorNotExists('#withdraw', 'one request at a time');
        // Until an admin approves, the media stays as it was.
        $this->client->request('GET', '/photos/'.$photos[0]->getId());
        self::assertResponseIsSuccessful();
        $this->em->clear();
        $incident = $this->em->getRepository(Incident::class)->find($incident->getId());
        self::assertInstanceOf(Incident::class, $incident);
        self::assertNotNull($incident->getMediaDeletionRequestedAt());
    }

    public function testReportGetsTheMeasuredWeatherAndTheSunInTheBackground(): void
    {
        $this->report('vw001x', ['report_incident[occurredAt]' => '2026-07-01T14:00']);

        // The lookup runs in the worker, not while the reporter waits.
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $events = array_map(static fn (Envelope $e): object => $e->getMessage(), $transport->getSent());
        self::assertCount(1, $events);
        self::assertInstanceOf(IncidentReportedEvent::class, $events[0]);

        self::getContainer()->get(CommandBusInterface::class)->dispatch(new RecordMeasuredConditionsCommand($events[0]->incidentId));
        $weather = self::weatherHistory();
        // 14:00 in the Netherlands in summer is 12:00 UTC; the point stays as reported, the adapter rounds it.
        self::assertSame('2026-07-01T12:00:00+00:00', $weather->lookups[0][2]->setTimezone(new \DateTimeZone('UTC'))->format('c'));

        $this->client->request('GET', '/en/reports/'.$events[0]->incidentId);
        self::assertSelectorTextContains('main', 'Measured weather');
        self::assertSelectorTextContains('main', '4.2 °C');
        self::assertSelectorTextContains('main', '35 km/h');
        self::assertMatchesRegularExpression('/Sun \(calculated\)\s*6\d° high, in the south/', (string) $this->client->getCrawler()->filter('main')->text());
        self::assertSelectorExists('main a[href="https://open-meteo.com/"]');

        $this->client->request('GET', '/en');
        self::assertSelectorTextContains('main', 'Measured conditions');
        self::assertSelectorTextContains('main', '(1 of 1 report)');
    }

    public function testConditionsLookupSuggestsWeatherAndLightForTheForm(): void
    {
        $this->client->disableReboot(); // keep the weather double between requests
        $crawler = $this->client->request('GET', '/en/report');
        self::assertSame('/api/conditions-lookup', $crawler->filter('form[name="report_incident"]')->attr('data-conditions-lookup-url-value'));

        $this->client->jsonRequest('POST', '/api/conditions-lookup', ['latitude' => 52.09, 'longitude' => 5.12, 'occurredAt' => '2026-07-01T14:00', 'country' => 'NL']);
        self::assertResponseIsSuccessful();
        $conditions = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('rain', $conditions['weather']); // 0.6 mm in that hour
        self::assertSame('daylight', $conditions['light']);
        self::assertGreaterThan(55, $conditions['sun']['elevation']);
        self::assertSame(4.2, $conditions['measured']['temperature']);

        // Without the weather service, the sun still helps.
        $weather = self::weatherHistory();
        $weather->down = true;
        $this->client->jsonRequest('POST', '/api/conditions-lookup', ['latitude' => 52.09, 'longitude' => 5.12, 'occurredAt' => '2026-11-12T08:30', 'country' => 'NL']);
        $conditions = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertNull($conditions['weather']);
        self::assertSame('low_sun', $conditions['light']);

        $this->client->jsonRequest('POST', '/api/conditions-lookup', ['latitude' => 52.09, 'longitude' => 5.12, 'occurredAt' => 'yesterday', 'country' => 'US']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testEveryModelHasItsOwnPage(): void
    {
        foreach (['vw001x', 'vw001x', 'vw001x', 'te-123-s'] as $plate) {
            $this->report($plate);
        }

        $crawler = $this->client->request('GET', '/en/vehicles');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('a[href="/en/vehicles/volkswagen/id-3"]'));
        self::assertCount(1, $crawler->filter('a[href="/en/vehicles/tesla/model-3"]'));

        $crawler = $this->client->request('GET', '/en/vehicles/volkswagen/id-3');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'VOLKSWAGEN ID.3');
        self::assertSelectorTextContains('main', '3 reports of phantom braking on the VOLKSWAGEN ID.3, from 1 vehicle.');
        self::assertStringStartsWith('index', (string) $crawler->filter('meta[name="robots"]')->attr('content'));
        self::assertSame('/api/incidents/map?make=VOLKSWAGEN&model=ID.3&v=3', $crawler->filter('[data-controller="incident-map"]')->attr('data-incident-map-url-value'));
        self::assertCount(3, $crawler->filter('main a[href^="/en/reports/"]'));

        // One report is too thin for search engines, but still a page for visitors.
        $crawler = $this->client->request('GET', '/en/vehicles/tesla/model-3');
        self::assertResponseIsSuccessful();
        self::assertSame('noindex, follow', $crawler->filter('meta[name="robots"]')->attr('content'));

        $this->client->request('GET', '/en/vehicles/volkswagen/golf');
        self::assertResponseStatusCodeSame(404);

        // The statistics table and a report page link to it.
        $this->client->request('GET', '/en');
        self::assertSelectorExists('main a[href="/en/vehicles/volkswagen/id-3"]');

        $this->client->request('GET', '/sitemap.xml');
        $sitemap = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('<loc>http://localhost/nl/vehicles/volkswagen/id-3</loc>', $sitemap);
        self::assertStringNotContainsString('tesla/model-3', $sitemap);
    }

    public function testFiltersApplyToDashboardListAndMap(): void
    {
        $this->report('TE123S', ['report_incident[roadType]' => 'motorway']);
        $this->report('VW001X', ['report_incident[roadType]' => 'urban', 'report_incident[roadSituation]' => 'intersection', 'report_incident[roadNumber]' => '']);

        $crawler = $this->client->request('GET', '/en/reports?make=tesla');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1 + p', '1 report');
        self::assertSelectorTextContains('main', 'TESLA');
        self::assertCount(0, $crawler->filter('main a[href*="/reports/"]:contains("VOLKSWAGEN")'));
        // The filter bar offers both makes (with counts) and the models of the selected make.
        self::assertSelectorExists('select[name=make] option[value="VOLKSWAGEN"]');
        self::assertSelectorExists('select[name=model] option[value="MODEL 3"]');

        $this->client->request('GET', '/en?road_type=urban');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.stat-value', '1');
        self::assertSelectorExists('a[href="/en/reports?road_type=urban"]');

        $this->client->request('GET', '/api/incidents/map?make=VOLKSWAGEN');
        $points = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertCount(1, $points);
        self::assertSame('ID.3', $points[0]['model']);

        // The dashboard versions the (cached) map URL with the report count, so a new report busts the cache.
        $mapUrl = $this->client->request('GET', '/en?make=VOLKSWAGEN')
            ->filter('[data-controller="incident-map"]')->attr('data-incident-map-url-value');
        self::assertSame('/api/incidents/map?make=VOLKSWAGEN&v=1', $mapUrl);
        $this->client->request('GET', (string) $mapUrl);
        self::assertCount(1, json_decode((string) $this->client->getResponse()->getContent(), true));

        $this->client->request('GET', '/en?period=30d&from=2001-01-01&country=XX&road_type=moon');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.stat-value', '2');

        $this->client->request('GET', '/en?from=2001-01-01&to=2001-12-31');
        self::assertSelectorTextContains('body', 'No reports match this selection.');
    }

    public function testFiltersOnTheCountryWhereItHappenedNotTheRegistration(): void
    {
        $this->report('TE123S'); // Dutch car, in the Netherlands (form default)

        // One incident country so far: no country selector yet.
        $this->client->request('GET', '/en');
        self::assertSelectorNotExists('select[name=country]');
        self::assertSelectorTextContains('main', 'Phantom braking in Netherlands');

        // The same kind of Dutch car, but on the German A9.
        $this->report('VW001X', ['report_incident[incidentCountry]' => 'DE', 'report_incident[roadNumber]' => 'A9']);
        /** @var Incident $german */
        $german = $this->em->getRepository(Incident::class)->findOneBy(['location.roadNumber' => 'A9']);
        self::assertSame(Country::Netherlands, $german->getCountry(), 'registration stays NL');
        self::assertSame('DE', $german->getLocation()->getCountry()->code);

        $crawler = $this->client->request('GET', '/en');
        self::assertSelectorExists('select[name=country] option[value="DE"]');
        self::assertSelectorTextContains('main', 'Phantom braking in Europe');
        // Roads are counted per incident country and coloured like that country's signs.
        self::assertStringContainsString('bg-red-700', (string) $crawler->filter('[data-road="NL-A2"] .rounded')->attr('class'));
        self::assertStringContainsString('bg-blue-700', (string) $crawler->filter('[data-road="DE-A9"] .rounded')->attr('class'));

        $this->client->request('GET', '/en?country=DE');
        self::assertSelectorTextContains('main', 'Phantom braking in Germany');
        self::assertSelectorTextContains('.stat-value', '1');
        self::assertSelectorExists('select[name=make] option[value="VOLKSWAGEN"]');
        self::assertSelectorNotExists('select[name=make] option[value="TESLA"]');

        // The report form still only offers registration countries with a register, but any European incident country.
        $this->client->request('GET', '/en/report');
        self::assertSelectorExists('select[name="report_incident[country]"] option[value="NL"]');
        self::assertSelectorNotExists('select[name="report_incident[country]"] option[value="DE"]');
        self::assertSelectorExists('select[name="report_incident[incidentCountry]"] option[value="BE"]');
        self::assertSelectorExists('select[name="report_incident[incidentCountry]"] option[value="NL"][selected]');
    }

    public function testReporterOfACarWithMandatoryAebIsAskedToReportToTheAuthority(): void
    {
        $this->report('TE123S', ['report_incident[description]' => 'Braked hard under the viaduct & nothing was there.']);
        /** @var Incident $incident */
        $incident = $this->em->getRepository(Incident::class)->findOneBy([]);
        self::assertResponseRedirects('/en/reports/'.$incident->getId().'/report-to-authority');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Report it to the ILT as well');
        // The e-loket form needs a session started on ilent.nl, so the reporter is guided there instead of deep-linked.
        self::assertSelectorExists('a[href="https://www.ilent.nl/meldformulieren"]');
        self::assertSelectorTextContains('main', 'Choose the form "Overige onderwerpen".');
        $text = $this->client->getCrawler()->filter('textarea')->text(normalizeWhitespace: false);
        self::assertStringContainsString('Vehicle: TESLA MODEL 3', $text);
        self::assertStringContainsString('Type approval: e4*2018/858*00217*07', $text);
        self::assertStringContainsString('Braked hard under the viaduct & nothing was there.', $text);
        self::assertStringContainsString('http://localhost/en/reports/'.$incident->getId(), $text);
        self::assertStringNotContainsStringIgnoringCase('TE123S', (string) $this->client->getResponse()->getContent());

        // The text to copy is offered in Dutch or English only, whatever the site language.
        $this->client->click($this->client->getCrawler()->filter('nav[aria-label="Language of the text"] a')->selectLink('Dutch')->link());
        $text = $this->client->getCrawler()->filter('textarea')->text(normalizeWhitespace: false);
        self::assertStringContainsString('Voertuig: TESLA MODEL 3', $text);
        self::assertStringContainsString('http://localhost/nl/reports/'.$incident->getId(), $text);
        self::assertSelectorTextContains('h1', 'Report it to the ILT as well');
        $this->client->request('GET', '/de/reports/'.$incident->getId().'/report-to-authority');
        self::assertSelectorTextContains('h1', 'Melde es auch der ILT');
        self::assertSelectorTextContains('nav[aria-label="Sprache des Textes"]', 'Niederländisch');
        self::assertStringContainsString('Vehicle: TESLA MODEL 3', $this->client->getCrawler()->filter('textarea')->text(normalizeWhitespace: false));
        $this->client->request('GET', '/de/reports/'.$incident->getId().'/report-to-authority?text=fr');
        self::assertStringContainsString('Vehicle: TESLA MODEL 3', $this->client->getCrawler()->filter('textarea')->text(normalizeWhitespace: false));

        $this->client->request('GET', '/en/reports/'.$incident->getId().'/report-to-authority');
        $this->client->submitForm('I reported it to the ILT');
        self::assertResponseRedirects('/en/reports/'.$incident->getId());
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', "We've added that to your report.");
        self::assertSelectorTextContains('main', 'Thank you for your report!');

        $this->em->clear();
        $incident = $this->em->getRepository(Incident::class)->find($incident->getId());
        self::assertInstanceOf(Incident::class, $incident);
        self::assertTrue($incident->isReportedToAuthority());
        self::assertNotNull($incident->getReportedToAuthorityViaPromptAt(), 'records that the prompt worked');

        // The prompt was for that one visit only.
        $this->client->request('GET', '/en/reports/'.$incident->getId().'/report-to-authority');
        self::assertResponseRedirects('/en/reports/'.$incident->getId());
    }

    public function testOnlyTheReporterCanAnswerTheAuthorityPrompt(): void
    {
        $this->report('TE123S');
        /** @var Incident $incident */
        $incident = $this->em->getRepository(Incident::class)->findOneBy([]);

        // Anyone else (another session) is sent to the public report page, and cannot mark it as reported.
        $this->client->getCookieJar()->clear();
        $this->client->request('POST', '/en/reports/'.$incident->getId().'/report-to-authority', ['authority_prompt' => ['reported' => '']]);
        self::assertResponseRedirects('/en/reports/'.$incident->getId());
        $this->em->clear();
        self::assertFalse($this->em->getRepository(Incident::class)->find($incident->getId())?->isReportedToAuthority());
    }

    public function testNoAuthorityPromptForOlderCarsOrWhenAlreadyReported(): void
    {
        $this->report('VW001X'); // first registered in 2022: AEB not mandatory
        self::assertResponseRedirects();
        self::assertStringEndsNotWith('/report-to-authority', (string) $this->client->getResponse()->headers->get('Location'));

        $this->report('TE123S', ['report_incident[reportedToAuthority]' => '1']);
        self::assertStringEndsNotWith('/report-to-authority', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testLinksWithARenamedIdRedirectToTheNewOne(): void
    {
        $this->report('VW001X');
        /** @var Incident $incident */
        $incident = $this->em->getRepository(Incident::class)->findOneBy([]);
        $old = '0b6e2f3c-1a4d-4c8e-9f2a-3b5c7d9e1f20';
        $this->em->persist(new RenamedId($old, (string) $incident->getId()));
        $this->em->flush();

        $this->client->request('GET', '/de/reports/'.$old.'?utm_source=kassa');
        self::assertResponseStatusCodeSame(301);
        self::assertResponseRedirects('/de/reports/'.$incident->getId().'?utm_source=kassa');

        // Only for pages that weren't found, and never for a form being sent.
        $this->client->request('GET', '/en/reports/00000000-0000-4000-8000-000000000000');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', '/en/reports/'.$old.'/request-media-deletion');
        self::assertNotSame(301, $this->client->getResponse()->getStatusCode());
    }

    public function testUnknownPlateIsReportedOnTheField(): void
    {
        $crawler = $this->client->request('GET', '/en/report');
        $this->client->submit($crawler->selectButton('Send report')->form(), $this->validValues('ZZ999Z'));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'This plate is not in the RDW register.');
        self::assertSame(0, $this->em->getRepository(Incident::class)->count([]));
    }

    public function testTeslaAssistedDrivingIsOnlyAcceptedForATesla(): void
    {
        $crawler = $this->client->request('GET', '/en/report');
        self::assertSelectorExists('select[name="report_incident[assistSystem]"] option[value="tesla_tad"][data-make="TESLA"][hidden]');

        $this->client->request($this->formMethod($crawler), '/en/report', $this->phpValues($crawler, 'VW001X', ['assistSystem' => 'tesla_tad']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'This system only exists on a Tesla.');
        self::assertSame(0, $this->em->getRepository(Incident::class)->count([]));

        $crawler = $this->client->request('GET', '/en/report');
        $this->client->request($this->formMethod($crawler), '/en/report', $this->phpValues($crawler, 'TE123S', ['assistSystem' => 'tesla_tad']));
        self::assertResponseRedirects();
        self::assertSame(1, $this->em->getRepository(Incident::class)->count(['assistSystem' => 'tesla_tad']));
    }

    public function testValidationErrors(): void
    {
        $crawler = $this->client->request('GET', '/en/report');
        $values = $this->validValues('TE123S');
        $values['report_incident[speedAfter]'] = '150';
        unset($values['report_incident[privacyConsent]']);

        // privacyConsent is left out: the checkbox is unticked by default.
        $this->client->submit($crawler->selectButton('Send report')->form(), $values);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'cannot be higher than the speed before braking');
        self::assertSelectorTextContains('body', 'You must agree');
    }

    public function testRejectsNonImagesAndTooManyPhotos(): void
    {
        $text = (string) tempnam(sys_get_temp_dir(), 'txt_');
        file_put_contents($text, 'definitely not a photo');
        $crawler = $this->client->request('GET', '/en/report');
        $this->submitWithFiles($crawler, $this->validValues('TE123S'), [
            'photos' => [new UploadedFile($text, 'holiday.jpg', 'image/jpeg', test: true)],
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Please upload photos only');

        $crawler = $this->client->request('GET', '/en/report');
        $this->submitWithFiles($crawler, $this->validValues('TE123S'), [
            'photos' => array_map(static fn (): UploadedFile => new UploadedFile(JpegFixture::create(10, 10), 'p.jpg', 'image/jpeg', test: true), range(1, 7)),
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'This collection should contain 6 elements or less.');
        self::assertSame(0, $this->em->getRepository(Incident::class)->count([]));
    }

    public function testVehicleLookupApi(): void
    {
        $this->client->jsonRequest('POST', '/api/vehicles/lookup', ['country' => 'NL', 'plate' => 'vw-001-x']);
        self::assertResponseIsSuccessful();
        self::assertSame('ID.3', json_decode((string) $this->client->getResponse()->getContent(), true)['model']);
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');

        $this->client->jsonRequest('POST', '/api/vehicles/lookup', ['country' => 'NL', 'plate' => 'nope00']);
        self::assertResponseStatusCodeSame(404);

        // Plates are never accepted in the URL.
        $this->client->request('GET', '/api/vehicles/lookup');
        self::assertResponseStatusCodeSame(405);
        $this->client->jsonRequest('POST', '/api/vehicles/lookup', ['country' => 'XX', 'plate' => 'AB123C']);
        self::assertResponseStatusCodeSame(422);
    }

    public function testRoadLookupSuggestsRoadDetailsForAMapPoint(): void
    {
        $this->client->jsonRequest('POST', '/api/road-lookup', ['latitude' => 52.4137, 'longitude' => 4.8643]);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertSame(
            ['country' => 'NL', 'roadNumber' => 'A10', 'roadType' => 'motorway', 'roadSituation' => 'tunnel_inside', 'speedLimit' => 100, 'name' => 'Coentunnel'],
            json_decode((string) $this->client->getResponse()->getContent(), true),
        );

        $this->client->jsonRequest('POST', '/api/road-lookup', ['latitude' => 120, 'longitude' => 4.8]);
        self::assertResponseStatusCodeSame(422);

        $this->client->request('GET', '/api/road-lookup?latitude=52&longitude=5');
        self::assertResponseStatusCodeSame(405);
    }

    public function testUnknownIncidentIs404(): void
    {
        $this->client->request('GET', '/en/reports/'.IncidentId::generate());
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/en/reports/not-a-uuid');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * DomCrawler cannot put several files into one `multiple` input, so files go straight into the request.
     *
     * @param array<string, string> $values
     * @param array<string, mixed>  $files  keyed by form field (footage, photos)
     */
    private function submitWithFiles(Crawler $crawler, array $values, array $files): void
    {
        $form = $crawler->selectButton('Send report')->form($values);
        $this->client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), ['report_incident' => $files]);
    }

    private function formMethod(Crawler $crawler): string
    {
        return $crawler->selectButton('Send report')->form()->getMethod();
    }

    /**
     * Raw POST values, bypassing DomCrawler's check that a select only gets an enabled option (as a crafted request would).
     *
     * @param array<string, string> $overrides field name => value
     *
     * @return array<string, mixed>
     */
    private function phpValues(Crawler $crawler, string $plate, array $overrides): array
    {
        $values = $crawler->selectButton('Send report')->form($this->validValues($plate))->getPhpValues();
        $values['report_incident'] = array_merge($values['report_incident'], $overrides);

        return $values;
    }

    /** The test container swaps Open-Meteo for this double (services_test.yaml); PHPStan only sees the dev container. */
    private static function weatherHistory(): FakeWeatherHistory
    {
        /** @var FakeWeatherHistory $weather */
        $weather = self::getContainer()->get(WeatherHistoryInterface::class);

        return $weather;
    }

    /** @param array<string, string> $overrides */
    private function report(string $plate, array $overrides = []): void
    {
        $crawler = $this->client->request('GET', '/en/report');
        $this->client->submit($crawler->selectButton('Send report')->form(), array_merge($this->validValues($plate), $overrides));
        self::assertResponseRedirects();
    }

    /** @return array<string, string> */
    private function validValues(string $plate): array
    {
        return [
            'report_incident[country]' => 'NL',
            'report_incident[licensePlate]' => $plate,
            'report_incident[occurredAt]' => (new \DateTimeImmutable('-2 days'))->format('Y-m-d\TH:i'),
            'report_incident[roadType]' => 'motorway',
            'report_incident[roadSituation]' => 'under_viaduct',
            'report_incident[roadNumber]' => 'a2',
            'report_incident[latitude]' => '52.09071',
            'report_incident[longitude]' => '5.12142',
            'report_incident[severity]' => 'emergency',
            'report_incident[speedBefore]' => '120',
            'report_incident[speedAfter]' => '45',
            'report_incident[trigger]' => 'viaduct_gantry',
            'report_incident[assistSystem]' => 'acc',
            'report_incident[warningShown]' => '1',
            'report_incident[consequence]' => 'near_rear_end',
            'report_incident[weather]' => 'dry',
            'report_incident[light]' => 'low_sun',
            'report_incident[recurrence]' => 'few',
            'report_incident[dealerResponse]' => 'no_fault_found',
            'report_incident[privacyConsent]' => '1',
        ];
    }
}
