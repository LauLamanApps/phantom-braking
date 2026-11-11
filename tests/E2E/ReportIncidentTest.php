<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Incident\Domain\Command\ModerateFootageCommand;
use App\Incident\Domain\Command\ModeratePhotoCommand;
use App\Incident\Domain\Model\Incident;
use App\Incident\Domain\ValueObject\IncidentId;
use App\SharedKernel\Domain\Messenger\Bus\CommandBusInterface;
use App\Tests\Support\JpegFixture;
use App\Tests\Support\ResetsTestStorage;
use App\Vehicle\Domain\ValueObject\Country;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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

        self::assertResponseRedirects();
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
