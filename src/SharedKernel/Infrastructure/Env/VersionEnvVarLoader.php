<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Env;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\EnvVarLoaderInterface;

/**
 * APP_VERSION and APP_BUILD for Sentry releases and the footer. A deploy writes version.json into the release
 * (deploy:set_app_version in deploy.php); without it (dev, tests) they come from the git working copy.
 */
final readonly class VersionEnvVarLoader implements EnvVarLoaderInterface
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    /** @return array{APP_VERSION: string, APP_BUILD: string} */
    public function loadEnvVars(): array
    {
        $path = $this->projectDir.'/version.json';
        if (is_file($path)) {
            $data = json_decode((string) file_get_contents($path), true);

            return [
                'APP_VERSION' => \is_array($data) && \is_string($data['version'] ?? null) ? $data['version'] : '',
                'APP_BUILD' => \is_array($data) && \is_string($data['build'] ?? null) ? $data['build'] : '',
            ];
        }

        $dir = escapeshellarg($this->projectDir);
        $branch = trim((string) @shell_exec("git -C $dir rev-parse --abbrev-ref HEAD 2>/dev/null"));
        $hash = trim((string) @shell_exec("git -C $dir rev-parse --short HEAD 2>/dev/null"));

        return [
            'APP_VERSION' => 'dev-'.('' !== $branch ? $branch : 'unknown'),
            'APP_BUILD' => '' !== $hash ? $hash : 'dev',
        ];
    }
}
