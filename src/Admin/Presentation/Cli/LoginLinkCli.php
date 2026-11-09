<?php

declare(strict_types=1);

namespace App\Admin\Presentation\Cli;

use App\Admin\Infrastructure\Security\AdminUserProvider;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

/**
 * The way in for a new admin and the recovery route after losing every passkey: whoever can run this
 * on the server can log in as that admin, so the link is only printed here, never e-mailed.
 */
#[AsCommand(name: 'app:admin:login-link', description: 'Print a one-time login link (15 minutes) for an admin, e.g. to add a passkey.')]
final readonly class LoginLinkCli
{
    public function __construct(
        private AdminUserProvider $admins,
        // The admin firewall's handler: on the CLI there is no request to pick the firewall from.
        #[Autowire(service: 'security.authenticator.login_link_handler.admin')]
        private LoginLinkHandlerInterface $loginLinks,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('Name of the admin')] string $name): int
    {
        try {
            $admin = $this->admins->loadUserByIdentifier($name);
        } catch (UserNotFoundException) {
            $io->error(sprintf('No admin named "%s".', $name));

            return Command::FAILURE;
        }

        $link = $this->loginLinks->createLoginLink($admin);
        $io->writeln([
            'One-time login link, valid until '.$link->getExpiresAt()->format('H:i T').' (usable once):',
            '',
            '  '.$link->getUrl(),
            '',
            'It opens the passkey page. Anyone with this link can log in as this admin: don\'t share it.',
        ]);

        return Command::SUCCESS;
    }
}
