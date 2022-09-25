<?php

namespace SayHello\GitInstaller\Package\Provider;

use SayHello\GitInstaller\Helpers;

class Gitlab extends Provider
{
    public static $provider = 'gitlab';

    public static function validateUrl($url)
    {
        $parsed = self::parseGitlabUrl($url);
        return $parsed['host'] === 'gitlab.com' && isset($parsed['id']);
    }

    private static function parseGitlabUrl($url)
    {
        $parsed = parse_url($url);
        $parsed['params'] = array_values(
            array_filter(
                explode('/', $parsed['path']),
                function ($e) {
                    return $e !== '';
                }
            )
        );


        return [
            'host' => $parsed['host'],
            'id' => urlencode(implode('/', $parsed['params'])),
            'repo' => end($parsed['params']),
        ];
    }

    public static function getInfos($url)
    {
        if (!self::validateUrl($url)) {
            return new \WP_Error(
                'invalid_url',
                sprintf(__('"%s" is not a valid Gitlab repository', 'shgi'), $url)
            );
        }

        $parsedUrl = self::parseGitlabUrl($url);
        // https://gitlab.com/api/v4/projects/say-hello%2Fplugins%2Fhello-cookies
        $apiUrl = 'https://gitlab.com/api/v4/projects/' . $parsedUrl['id'];
        $auth = self::authenticateRequest($apiUrl);

        $response = Helpers::getRestJson($auth[0], $auth[1]);
        if (is_wp_error($response)) return $response;

        $branches = self::getBranches($parsedUrl['id']);

        if (is_wp_error($branches)) return $branches;

        return [
            'key' => $parsedUrl['repo'],
            'name' => $response['name'],
            'private' => $response['visibility'] === 'private',
            'provider' => self::$provider,
            'branches' => $branches,
            'baseUrl' => $response['web_url'],
            'apiUrl' => $apiUrl,
        ];
    }

    private static function getBranches($id)
    {
        $apiUrl = 'https://gitlab.com/api/v4/projects/' . $id;
        $apiBranchesUrl = "{$apiUrl}/repository/branches";
        $auth = self::authenticateRequest($apiBranchesUrl);
        $response = Helpers::getRestJson($auth[0], $auth[1]);
        if (is_wp_error($response)) return $response;

        $branches = [];
        foreach ($response as $branch) {
            $branches[$branch['name']] = [
                'name' => $branch['name'],
                'url' => $branch['web_url'],
                'zip' => trailingslashit($apiUrl) . 'repository/archive.zip?sha=' . $branch['name'],
                'default' => $branch['default'],
            ];
        }
        return $branches;
    }

    private static function getRepoFolderFiles($id, $branch, $folder = '')
    {
        $auth = self::authenticateRequest("https://gitlab.com/api/v4/projects/{$id}/repository/tree/?ref={$branch}&recursive=1&per_page=999");
        $response = Helpers::getRestJson($auth[0], $auth[1]);
        $files = array_values(
            array_filter(
                $response,
                function ($element) use ($folder) {
                    if ($element['type'] !== 'blob') return false;
                    if (!str_starts_with($element['path'], $folder)) return false;
                    if ($element['path'] === 'style.css') return true;
                    $relativePath = substr($element['path'], strlen($folder));
                    if (str_contains($relativePath, '/')) return false;
                    return str_ends_with($relativePath, '.php');
                }
            )
        );

        return array_map(function ($element) use ($folder, $id, $branch) {
            $path = urlencode($element['path']);
            $auth = self::authenticateRequest("https://gitlab.com/api/v4/projects/{$id}/repository/files/{$path}?ref={$branch}");
            $response = Helpers::getRestJson($auth[0], $auth[1]);
            return [
                'file_name' => $response['file_path'],
                'content' => base64_decode($response['content']),
            ];
        }, $files);
    }

    public static function validateDir($url, $branch, $dir)
    {
        $parsed = self::parseGitlabUrl($url);
        return self::getRepoFolderFiles($parsed['id'], $branch, $dir);
    }

    public static function authenticateRequest($url, $args = [])
    {
        $gitlabToken = sayhelloGitInstaller()->Settings->getSingleSettingValue('git-packages-gitlab-token');
        if ($gitlabToken) {
            $gitlabToken = Provider::trimString($gitlabToken);
            if (strpos($url, 'private_token=') === false) {
                if (strpos($url, '?') === false) {
                    $url = $url . '?private_token=' . $gitlabToken;
                } else {
                    $url = $url . '&private_token=' . $gitlabToken;
                }
            }
        }

        return [$url, $args];
    }

    public static function export()
    {
        return new class {
            public function name()
            {
                return 'Gitlab';
            }

            public function hasToken()
            {
                return boolval(sayhelloGitInstaller()->Settings->getSingleSettingValue('git-packages-gitlab-token'));
            }

            public function validateUrl($url)
            {
                return Gitlab::validateUrl($url);
            }

            public function getInfos($url)
            {
                return Gitlab::getInfos($url);
            }

            public function authenticateRequest($url, $args = [])
            {
                return Gitlab::authenticateRequest($url, $args);
            }

            public function validateDir($url, $branch, $dir = '')
            {
                return Gitlab::validateDir($url, $branch, $dir);
            }
        };
    }
}
