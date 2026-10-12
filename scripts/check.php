<?php
// Run inside the PHP container: php scripts/check.php
$commands = [
    'php bin/console doctrine:database:create --env=test --if-not-exists',
    'php bin/console doctrine:migrations:migrate --env=test --no-interaction',
    'php bin/console doctrine:schema:validate',
    'php bin/console doctrine:schema:validate --env=test',
    'composer check',
];
foreach ($commands as $command) {
    passthru($command, $exitCode);
    if ($exitCode !== 0) { exit($exitCode); }
}
