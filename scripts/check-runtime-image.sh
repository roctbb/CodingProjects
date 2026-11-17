#!/usr/bin/env sh
set -eu

# No app environment, network or production volumes are needed for this check.
image=${1:?Usage: scripts/check-runtime-image.sh IMAGE}
docker run --rm -i --network none --entrypoint sh "$image" -s <<'SHELL'
set -eu
php-fpm -t
for tool in gcc g++ cpp make phpize composer node npm; do
    if command -v "$tool" >/dev/null 2>&1; then
        echo "Unexpected build tool in runtime: $tool" >&2
        exit 1
    fi
done
if ldd /usr/local/bin/php /usr/local/sbin/php-fpm /usr/local/lib/php/extensions/*/*.so | grep -q 'not found'; then
    echo 'Missing runtime library' >&2
    exit 1
fi
php <<'PHP'
<?php
require '/var/www/html/vendor/autoload.php';

function check(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

foreach (['ctype', 'curl', 'dom', 'fileinfo', 'gd', 'iconv', 'mbstring', 'openssl',
    'pcntl', 'pdo_pgsql', 'pdo_sqlite', 'posix', 'redis', 'SimpleXML', 'sodium',
    'sqlite3', 'xml', 'xmlreader', 'xmlwriter', 'Zend OPcache', 'zip', 'zlib'] as $extension) {
    check(extension_loaded($extension), "Missing PHP extension: $extension");
}
check(mb_strtoupper('проверка', 'UTF-8') === 'ПРОВЕРКА', 'Multibyte text failed');
check(iconv('UTF-8', 'CP1251', 'тест') !== false, 'Character conversion failed');
$xml = new DOMDocument();
check($xml->loadXML('<check>ok</check>'), 'XML parsing failed');
$image = imagecreatetruecolor(2, 2);
foreach (['imagepng', 'imagejpeg', 'imagewebp'] as $encode) {
    ob_start();
    check($encode($image), "$encode failed");
    check(strlen(ob_get_clean()) > 0, "$encode produced no image");
}
$path = tempnam(sys_get_temp_dir(), 'runtime-zip-');
try {
    $zip = new ZipArchive();
    check($zip->open($path, ZipArchive::OVERWRITE) === true, 'ZIP creation failed');
    check($zip->addFromString('check.txt', 'ok') && $zip->close(), 'ZIP write failed');
    check($zip->open($path) === true && $zip->getFromName('check.txt') === 'ok', 'ZIP read failed');
    $zip->close();
} finally {
    unlink($path);
}
$pdo = new PDO('sqlite::memory:');
check((int) $pdo->query('SELECT 1')->fetchColumn() === 1, 'SQLite failed');
check(in_array('pgsql', PDO::getAvailableDrivers(), true), 'PostgreSQL driver missing');
$key = random_bytes(32);
$iv = random_bytes(16);
$cipher = openssl_encrypt('ok', 'aes-256-cbc', $key, 0, $iv);
check($cipher !== false && openssl_decrypt($cipher, 'aes-256-cbc', $key, 0, $iv) === 'ok', 'OpenSSL failed');
check(is_readable(openssl_get_cert_locations()['default_cert_file']), 'CA certificate bundle missing');
check(in_array('https', curl_version()['protocols'], true), 'cURL HTTPS support missing');
check(is_file('/var/www/html/public/build/manifest.json'), 'Frontend build missing');
echo 'Runtime checks passed on PHP ', PHP_VERSION, PHP_EOL;
PHP
SHELL
