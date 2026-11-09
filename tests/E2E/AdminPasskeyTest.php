<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Admin\Domain\Command\CreateAdminCommand;
use App\Admin\Domain\Model\AdminPasskey;
use App\Admin\Infrastructure\Security\AdminUserProvider;
use App\Admin\Infrastructure\Webauthn\PasskeyCredentialRepository;
use App\SharedKernel\Domain\Messenger\Bus\CommandBusInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

/**
 * Passwordless admin login. The browser/authenticator half of the WebAuthn ceremony (signing a challenge)
 * can't run in PHPUnit; it was checked in Chrome with a virtual authenticator. These tests cover our side:
 * endpoints, options, storage of passkeys, the one-time login link and passkey management.
 */
final class AdminPasskeyTest extends WebTestCase
{
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
        self::getContainer()->get(CommandBusInterface::class)->dispatch(new CreateAdminCommand(self::NAME));
    }

    public function testLoginOptionsAskForAUserVerifiedPasskeyOfThisSite(): void
    {
        $options = $this->postJson('/admin/login/options');

        self::assertResponseIsSuccessful();
        self::assertSame('localhost', $options['rpId']);
        self::assertSame('required', $options['userVerification']);
        self::assertGreaterThanOrEqual(43, \strlen($options['challenge'])); // 32 random bytes, base64url
        self::assertSame([], $options['allowCredentials']); // discoverable: nothing about admins is revealed
    }

    public function testAForgedPasskeyResponseDoesNotLogIn(): void
    {
        $this->postJson('/admin/login/options');
        $this->postJson('/admin/login/passkey', ['id' => 'AAAA', 'rawId' => 'AAAA', 'type' => 'public-key', 'response' => ['clientDataJSON' => 'e30', 'authenticatorData' => 'AAAA', 'signature' => 'AAAA']]);

        self::assertGreaterThanOrEqual(400, $this->client->getResponse()->getStatusCode());
        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('/admin/login');
    }

    public function testLoginLinkWorksOnceAndLeadsToAddingAPasskey(): void
    {
        $tester = new CommandTester($this->console()->find('app:admin:login-link'));
        self::assertSame(0, $tester->execute(['name' => self::NAME]));
        self::assertSame(1, preg_match('#http://localhost(/admin/login/link\?\S+)#', $tester->getDisplay(), $m));
        $link = $m[1];

        // Opening the link only shows a button, so link previews can't use it up.
        $crawler = $this->client->request('GET', $link);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('/admin/login');

        $crawler = $this->client->request('GET', $link);
        $this->client->submit($crawler->filter('form')->form());
        self::assertResponseRedirects('/admin/passkeys');
        $this->client->followRedirect();
        self::assertSelectorExists('[role=status]'); // "you don't have a passkey yet"
        self::assertSelectorExists('[data-passkey-mode-value=register]');

        $this->client->request('GET', '/admin/logout');
        $crawler = $this->client->request('GET', $link);
        $this->client->submit($crawler->filter('form')->form());
        self::assertResponseRedirects('/admin/login');
        $this->client->followRedirect();
        self::assertSelectorExists('p.bg-brand-50[role=alert]');
    }

    public function testCreatingAnAdminPrintsALoginLinkAndUnknownAdminsGetNone(): void
    {
        $application = $this->console();
        $create = new CommandTester($application->find('app:admin:create'));
        self::assertSame(0, $create->execute(['name' => 'Nieuwe Beheerder']));
        self::assertStringContainsString('http://localhost/admin/login/link?', $create->getDisplay());

        $link = new CommandTester($application->find('app:admin:login-link'));
        self::assertSame(1, $link->execute(['name' => 'Niemand']));
    }

    public function testRegistrationOptionsAreForTheLoggedInAdminAndHideExistingPasskeys(): void
    {
        $existing = $this->storePasskey();
        $this->login();

        $options = $this->postJson('/admin/passkeys/options');

        self::assertResponseIsSuccessful();
        self::assertSame(self::NAME, $options['user']['name']);
        self::assertSame('localhost', $options['rp']['id']);
        self::assertSame('required', $options['authenticatorSelection']['residentKey']);
        self::assertSame('required', $options['authenticatorSelection']['userVerification']);
        self::assertCount(1, $options['excludeCredentials']);
        self::assertSame(rtrim(strtr(base64_encode($existing->publicKeyCredentialId), '+/', '-_'), '='), $options['excludeCredentials'][0]['id']);
    }

    public function testRegistrationEndpointsNeedALoggedInAdmin(): void
    {
        $this->client->request('POST', '/admin/passkeys/options', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseRedirects('/admin/login');
    }

    public function testPasskeysAreStoredUpdatedOnLoginAndTheLastOneCannotBeRemoved(): void
    {
        $first = $this->storePasskey();
        $repository = self::getContainer()->get(PasskeyCredentialRepository::class);

        // A login: the bundle saves the record it loaded, with the verified counter.
        $loaded = $repository->findOneByCredentialId($first->publicKeyCredentialId);
        self::assertInstanceOf(AdminPasskey::class, $loaded);
        $loaded->counter = 7;
        $repository->saveCredentialRecord($loaded);
        $this->em->clear();
        $reloaded = $repository->findOneByCredentialId($first->publicKeyCredentialId);
        self::assertInstanceOf(AdminPasskey::class, $reloaded);
        self::assertSame(7, $reloaded->counter);
        self::assertNotNull($reloaded->getLastUsedAt());

        $this->login();
        $this->client->request('GET', '/admin/passkeys');
        self::assertSelectorCount(1, 'main li');
        self::assertSelectorNotExists('main li form'); // no remove button on the only passkey

        $second = $this->storePasskey();
        $crawler = $this->client->request('GET', '/admin/passkeys');
        self::assertSelectorCount(2, 'main li form');
        $token = $this->csrfToken($crawler);
        $this->client->submit($crawler->filter(sprintf('form[action="/admin/passkeys/%s/remove"]', $second->getId()))->form());
        $this->client->followRedirect();
        self::assertSelectorCount(1, 'main li');

        // Even a crafted request can't remove the last one.
        $this->client->request('POST', sprintf('/admin/passkeys/%s/remove', $reloaded->getId()), ['_token' => $token]);
        self::assertResponseRedirects('/admin/passkeys');
        $this->client->followRedirect();
        self::assertSelectorCount(1, 'main li');
        self::assertSelectorExists('[role=status]');
    }

    public function testAnAdminCannotRemoveSomeoneElsesPasskey(): void
    {
        self::getContainer()->get(CommandBusInterface::class)->dispatch(new CreateAdminCommand('Andere Beheerder'));
        $theirs = $this->storePasskey('Andere Beheerder');
        $this->storePasskey('Andere Beheerder');

        $this->storePasskey();
        $this->storePasskey();
        $this->login();
        $crawler = $this->client->request('GET', '/admin/passkeys');
        $this->client->request('POST', sprintf('/admin/passkeys/%s/remove', $theirs->getId()), ['_token' => $this->csrfToken($crawler)]);

        self::assertResponseStatusCodeSame(404);
    }

    private function storePasskey(string $name = self::NAME): AdminPasskey
    {
        $admin = self::getContainer()->get(AdminUserProvider::class)->loadUserByIdentifier($name);
        $repository = self::getContainer()->get(PasskeyCredentialRepository::class);
        $credentialId = random_bytes(16);
        $repository->saveCredentialRecord(CredentialRecord::create(
            $credentialId, 'public-key', ['internal'], 'none', EmptyTrustPath::create(),
            Uuid::fromString('00000000-0000-0000-0000-000000000000'), random_bytes(77), (string) $admin->getAdminUserId(), 0,
        ));
        $stored = $repository->findOneByCredentialId($credentialId);
        self::assertInstanceOf(AdminPasskey::class, $stored);

        return $stored;
    }

    private function login(): void
    {
        $this->client->loginUser(self::getContainer()->get(AdminUserProvider::class)->loadUserByIdentifier(self::NAME), 'admin');
    }

    private function console(): Application
    {
        self::assertNotNull(self::$kernel);

        return new Application(self::$kernel);
    }

    private function csrfToken(Crawler $crawler): string
    {
        return (string) $crawler->filter('input[name=_token]')->first()->attr('value');
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function postJson(string $url, array $body = []): array
    {
        $this->client->request('POST', $url, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: json_encode($body, \JSON_THROW_ON_ERROR));

        return json_decode((string) $this->client->getResponse()->getContent(), true) ?? [];
    }
}
