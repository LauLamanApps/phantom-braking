<?php

declare(strict_types=1);

namespace Deployer;

require 'recipe/symfony.php';

// Deployed to its own Debian 12 LXC on Proxmox (nginx + PHP-FPM + PostgreSQL + supervisor),
// behind the existing reverse proxy. Server setup: docs/deployment.md and .deploy/.

// Public repository: the server clones over HTTPS, so your SSH agent is never forwarded to it.
set('repository', 'https://github.com/LauLamanApps/phantom-braking.git');
set('http_user', 'www-data');
set('keep_releases', 5);

// var/tailwind: the Tailwind binary is downloaded once, not on every deploy.
// var/storage: upload staging (media itself lives in S3).
// .env.local (recipe default): the production secrets, placed once in shared/.
add('shared_dirs', ['var/tailwind', 'var/storage']);

// No Sentry events from console commands run during the deploy itself.
set('bin/console', function () {
    return 'SENTRY_DSN= {{bin/php}} {{release_or_current_path}}/bin/console';
});

// Hosts

// The real target (an internal address) stays out of this public repository: copy deploy.local.php.dist
// to deploy.local.php (git-ignored) and fill it in.
$local = is_file(__DIR__.'/deploy.local.php') ? require __DIR__.'/deploy.local.php' : [];
$hostname = \is_array($local) && \is_string($local['hostname'] ?? null) ? $local['hostname']
    : throw new \RuntimeException('Deploy target missing: copy deploy.local.php.dist to deploy.local.php and set the hostname.');

host('phantombraking.eu')
    ->setLabels(['env' => 'prod'])
    ->setHostname($hostname)
    ->setRemoteUser('deployer')
    ->set('deploy_path', '/var/www/phantombraking.eu');

// Tasks

// version.json: the release version and build (short commit hash), read by VersionEnvVarLoader as APP_VERSION/APP_BUILD
// for the Sentry release and the footer. The version is the deployed tag (v1.2.0 → 1.2.0); a branch deploy gets the
// latest tag it contains, or the commit before the first tag. Releases are listed in CHANGELOG.md.
task('deploy:set_app_version', function () {
    $tag = input()->getOption('tag') ?: null;
    $build = trim(runLocally('git rev-parse --short HEAD'));
    $latestTag = trim(runLocally('git describe --tags --abbrev=0 --match "v[0-9]*" 2>/dev/null || true'));
    $version = ltrim($tag ?: ($latestTag ?: $build), 'v');
    $json = json_encode(['version' => $version, 'build' => $build], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);

    writeln("<info>Writing version.json: version={$version}, build={$build}</info>");
    run('echo '.escapeshellarg((string) $json).' > {{release_path}}/version.json');
});

task('deploy:build-css', function () {
    run('cd {{release_or_current_path}} && {{bin/console}} tailwind:build --minify');
});

task('deploy:compile-assets', function () {
    run('cd {{release_or_current_path}} && {{bin/console}} asset-map:compile');
});

// Clear OPcache after the symlink switch so PHP-FPM serves the new release.
task('deploy:opcache-reset', function () {
    run('sudo systemctl reload php8.5-fpm');
});

// The worker keeps the old code in memory until it is restarted.
task('deploy:restart-messenger', function () {
    run('sudo supervisorctl restart phantom-braking-messenger:');
});

// Hooks

before('deploy:vendors', 'deploy:set_app_version');
after('deploy:cache:clear', 'database:migrate');
after('database:migrate', 'deploy:build-css');
after('deploy:build-css', 'deploy:compile-assets');
after('deploy:symlink', 'deploy:opcache-reset');
after('deploy:symlink', 'deploy:restart-messenger');
after('deploy:failed', 'deploy:unlock');
