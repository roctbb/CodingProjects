<?php
namespace App\Services\CodeChecking;

use App\CodeSubmission;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

class Repositories
{
    private ClientInterface $http;
    public function __construct(private Artifacts $artifacts, ?ClientInterface $http = null)
    {
        $this->http = $http ?? new Client(['connect_timeout' => 3, 'timeout' => 60, 'allow_redirects' => false,
            'headers' => ['User-Agent' => 'CodingProjects-Submission', 'Accept' => 'application/vnd.github+json']]);
    }

    public function parse(mixed $url, mixed $ref = null): array
    {
        abort_unless(is_string($url) && strlen($url) <= 1000, 422, 'Invalid GitHub URL.');
        $parts = parse_url($url);
        abort_unless($parts && ($parts['scheme'] ?? '') === 'https' && ($parts['host'] ?? '') === 'github.com'
            && !isset($parts['port']) && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['query'])
            && !isset($parts['fragment']) && preg_match('#^/([A-Za-z0-9_-]+)/([A-Za-z0-9_.-]+?)(?:\.git)?(?:/tree/(.+))?/?$#D', $parts['path'] ?? '', $match), 422, 'Use a public github.com repository URL.');
        $owner = $match[1]; $repo = $match[2];
        abort_if(in_array($repo, ['.', '..'], true), 422);
        $ref = $ref ?? (isset($match[3]) ? rawurldecode(rtrim($match[3], '/')) : 'HEAD');
        abort_unless(is_string($ref) && strlen($ref) >= 1 && strlen($ref) <= 200 && !preg_match('/[\x00-\x20\x7f]/', $ref), 422, 'Invalid repository ref.');
        return ['provider' => 'github', 'url' => 'https://github.com/'.$owner.'/'.$repo, 'owner' => $owner, 'repository' => $repo, 'ref' => $ref];
    }

    public function resolve(array $metadata): string
    {
        $base = 'https://api.github.com/repos/'.$metadata['owner'].'/'.$metadata['repository'];
        $repo = $this->http->request('GET', $base);
        $details = json_decode((string) $repo->getBody(), true, 32, JSON_THROW_ON_ERROR);
        abort_unless(($details['private'] ?? true) === false, 422, 'Only public repositories are supported.');
        $response = $this->http->request('GET', $base.'/commits/'.rawurlencode($metadata['ref']));
        $value = json_decode((string) $response->getBody(), true, 64, JSON_THROW_ON_ERROR);
        abort_unless(is_string($value['sha'] ?? null) && preg_match('/^[a-f0-9]{40}$/D', $value['sha']), 422, 'Unable to resolve repository commit.');
        return $value['sha'];
    }

    public function archive(array $metadata): string
    {
        $response = $this->http->request('GET', 'https://api.github.com/repos/'.$metadata['owner'].'/'.$metadata['repository'].'/zipball/'.$metadata['commit']);
        abort_unless($response->getStatusCode() === 302, 422, 'GitHub did not return an archive.');
        $location = $response->getHeaderLine('Location');
        $url = parse_url($location);
        abort_unless($url && ($url['scheme'] ?? '') === 'https' && ($url['host'] ?? '') === 'codeload.github.com'
            && !isset($url['port']) && !isset($url['user']) && !isset($url['pass']) && !isset($url['fragment'])
            && str_starts_with($url['path'] ?? '', '/'.$metadata['owner'].'/'.$metadata['repository'].'/'), 422, 'Unexpected archive origin.');
        $response = $this->http->request('GET', $location, ['stream' => true]);
        abort_unless($response->getStatusCode() === 200, 422, 'Unable to download archive.');
        $temporary = tempnam(sys_get_temp_dir(), 'cp-repository-');
        $output = fopen($temporary, 'wb'); $size = 0;
        try {
            $body = $response->getBody();
            while (!$body->eof()) {
                $chunk = $body->read(65536); $size += strlen($chunk);
                abort_if($size > 52428800, 422, 'Repository archive exceeds 50 MiB.');
                if (fwrite($output, $chunk) !== strlen($chunk)) throw new \RuntimeException('Unable to save repository.');
            }
            fclose($output);
            return $temporary;
        } catch (\Throwable $exception) {
            if (is_resource($output)) fclose($output);
            @unlink($temporary);
            throw $exception;
        }
    }

    public function capture(CodeSubmission $submission, array $config): void
    {
        if ($submission->preparation_state === 'ready') return;
        $metadata = $submission->source_metadata;
        if (empty($metadata['commit'])) {
            $commit = $this->resolve($metadata);
            // A retry always uses this commit, even if the branch has moved.
            $submission->refresh();
            $metadata = $submission->source_metadata;
            $metadata['commit'] ??= $commit;
            $submission->update(['source_metadata' => $metadata]);
        }
        $path = $this->archive($metadata);
        try {
            $files = $this->unpack($path, $config);
            $metadata['archive'] = $this->artifacts->putFile($path);
            $submission->update(['files' => $files, 'source_metadata' => $metadata, 'preparation_state' => 'ready']);
        } finally { @unlink($path); }
    }

    public function unpack(string $path, array $config): array
    {
        $archive = new \ZipArchive();
        abort_unless($archive->open($path) === true, 422, 'Invalid repository archive.');
        $files = []; $total = 0; $root = null;
        try {
            abort_if($archive->numFiles > 10000, 422, 'Repository contains too many entries.');
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $stat = $archive->statIndex($index);
                $name = $stat['name'];
                if (str_ends_with($name, '/')) continue;
                $parts = explode('/', $name, 2);
                abort_unless(count($parts) === 2, 422, 'Invalid archive root.');
                $root ??= $parts[0];
                abort_unless($root === $parts[0], 422, 'Invalid archive root.');
                $name = Paths::validate($parts[1]);
                if (preg_match('#(^|/)(node_modules|vendor|\.git|\.venv|venv|__pycache__|\.env)(/|$)#', $name)) continue;
                $archive->getExternalAttributesIndex($index, $system, $attributes);
                abort_if((($attributes >> 16) & 0170000) === 0120000, 422, 'Repository symlinks are not supported.');
                $total += $stat['size'];
                abort_if($stat['size'] > ($config['max_file_bytes'] ?? 52428800) || $total > ($config['max_total_bytes'] ?? 52428800)
                    || count($files) >= ($config['max_files'] ?? 100), 422, 'Repository exceeds task limits.');
                $contents = $archive->getFromIndex($index, $stat['size'] + 1);
                abort_unless(is_string($contents) && strlen($contents) === $stat['size'], 422, 'Unable to read repository file.');
                $files[] = ['name' => $name, 'artifact' => $this->artifacts->put($contents)];
            }
        } finally { $archive->close(); }
        abort_unless($files, 422, 'Repository has no files.');
        return $files;
    }
}
