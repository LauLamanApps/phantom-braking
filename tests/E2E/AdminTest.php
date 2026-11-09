<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Admin\Domain\Command\CreateAdminCommand;
use App\Admin\Infrastructure\Security\AdminUserProvider;
use App\Incident\Domain\Command\RequestMediaDeletionCommand;
use App\Incident\Domain\Model\Incident;
use App\SharedKernel\Domain\Messenger\Bus\CommandBusInterface;
use App\Tests\Support\JpegFixture;
use App\Tests\Support\ResetsTestStorage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AdminTest extends WebTestCase
{
    use ResetsTestStorage;

    private const string NAME = 'Moderator';

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
        self::getContainer()->get(CommandBusInterface::class)->dispatch(new CreateAdminCommand(self::NAME));
    }

    public function testAdminRequiresLogin(): void
    {
        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('/admin/login');

        $this->client->request('GET', '/admin/login');
        self::assertSelectorExists('[data-controller=passkey] button');
        self::assertSelectorNotExists('input[type=password]');

        $this->login();
        $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('header', self::NAME);
    }

    public function testModeratorMasksApprovesAndDeletesOnRequest(): void
    {
        $incident = $this->reportWithPhoto();
        $photo = $incident->getPhotos()[0];
        $storage = self::getContainer()->getParameter('kernel.project_dir').'/var/storage/footage_test/';
        $this->login();

        // The review queue lists it, and the storage card counts the photo (original + public copy).
        $crawler = $this->client->request('GET', '/admin');
        self::assertSelectorExists(sprintf('a[href="/admin/reports/%s"]', $incident->getId()));
        self::assertSelectorTextContains('#storage', "Foto's · 2 bestanden");
        self::assertSelectorTextContains('#storage', "Video's · 0 bestanden");

        // Actions need the CSRF token: without it nothing happens (the firewall sends you to the login page).
        $this->client->request('POST', sprintf('/admin/photos/%s/approve', $photo->getId()));
        self::assertResponseRedirects('/admin/login');
        self::assertFalse($this->refresh($incident)->getPhotos()[0]->isPublic());

        // Mask the plate area, then approve.
        $crawler = $this->client->request('GET', sprintf('/admin/reports/%s', $incident->getId()));
        $form = $crawler->filter(sprintf('#photo-%s form[action$="/masks"]', $photo->getId()))->form(['masks' => '[[0, 0, 0.5, 0.5]]']);
        $this->client->submit($form);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Maskering opgeslagen');

        $public = imagecreatefromjpeg($storage.$this->refresh($incident)->getPhotos()[0]->getPublishedPath());
        self::assertNotFalse($public);
        $colour = imagecolorat($public, 20, 20);
        self::assertIsInt($colour);
        self::assertLessThan(30, imagecolorsforindex($public, $colour)['red'], 'masked area is black in the public copy');

        $this->client->submit($crawler->filter(sprintf('#photo-%s form[action$="/approve"]', $photo->getId()))->form());
        $this->client->request('GET', '/photos/'.$photo->getId());
        self::assertResponseIsSuccessful();

        // The reporter asks for deletion; the admin approves; the files are gone.
        self::getContainer()->get(CommandBusInterface::class)->dispatch(new RequestMediaDeletionCommand($incident->getId(), 'TE123S'));
        $files = [$photo->getPath(), $this->refresh($incident)->getPhotos()[0]->getPublishedPath()];
        $crawler = $this->client->request('GET', '/admin');
        $this->client->submit($crawler->filter(sprintf('form[action="/admin/reports/%s/deletion/approve"]', $incident->getId()))->form());
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'zijn verwijderd');

        foreach ($files as $file) {
            self::assertFileDoesNotExist($storage.$file);
        }
        self::assertFalse($this->refresh($incident)->hasMedia());
        $this->client->request('GET', '/photos/'.$photo->getId());
        self::assertResponseStatusCodeSame(404);
    }

    /** The passkey ceremony itself is covered by AdminPasskeyTest; here we only need a logged-in moderator. */
    private function login(): void
    {
        $this->client->loginUser(self::getContainer()->get(AdminUserProvider::class)->loadUserByIdentifier(self::NAME), 'admin');
    }

    private function refresh(Incident $incident): Incident
    {
        $this->em->clear();
        $fresh = $this->em->getRepository(Incident::class)->find($incident->getId());
        self::assertInstanceOf(Incident::class, $fresh);

        return $fresh;
    }

    private function reportWithPhoto(): Incident
    {
        $crawler = $this->client->request('GET', '/en/report');
        $form = $crawler->selectButton('Send report')->form([
            'report_incident[country]' => 'NL',
            'report_incident[licensePlate]' => 'TE123S',
            'report_incident[occurredAt]' => (new \DateTimeImmutable('-2 days'))->format('Y-m-d\TH:i'),
            'report_incident[incidentCountry]' => 'NL',
            'report_incident[roadType]' => 'motorway',
            'report_incident[roadSituation]' => 'under_viaduct',
            'report_incident[severity]' => 'emergency',
            'report_incident[speedBefore]' => '120',
            'report_incident[trigger]' => 'shadow',
            'report_incident[assistSystem]' => 'acc',
            'report_incident[consequence]' => 'near_rear_end',
            'report_incident[weather]' => 'dry',
            'report_incident[light]' => 'daylight',
            'report_incident[recurrence]' => 'first',
            'report_incident[dealerResponse]' => 'not_contacted',
            'report_incident[privacyConsent]' => '1',
        ]);
        $this->client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), ['report_incident' => [
            'photos' => [new UploadedFile(JpegFixture::create(400, 300), 'damage.jpg', 'image/jpeg', test: true)],
        ]]);
        self::assertResponseRedirects();

        $incident = $this->em->getRepository(Incident::class)->findOneBy([]);
        self::assertInstanceOf(Incident::class, $incident);

        return $incident;
    }
}
