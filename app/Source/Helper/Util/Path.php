<?php
declare(strict_types=1);

namespace TrayDigita\Streak\Source\Helper\Util;

use GuzzleHttp\Psr7\Uri;
use JetBrains\PhpStorm\ArrayShape;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Slim\App;
use TrayDigita\Streak\Source\Application;

class Path
{
    #[ArrayShape([
        'protocol' => "string|null",
        'username' => "string|null",
        'password' => "string|null",
        'host' => "string|null",
        'port' => "int|null",
        'path' => "string|null",
        'query' => "string|null",
        'fragment' => "string|null",
        'other' => "string|null"
    ])] public static function parseURL(string|Uri $uri) : array
    {
        $uri = (string) $uri;
        preg_match(
            '~^
                (?:(?P<protocol>[a-z]{3,63}):)?
                (?://
                    (?:
                        (?P<username>[^:]+)
                        (?:
                        :(?P<password>.+)
                        )?
                        @
                    )?
                    (?P<host>[^/:]+)
                    (?:[:](?P<port>[^/]+))?
                )?
                (?P<path>
                    /[^?#]+
                )?
                (?:
                \?
                    (?P<query>[^#]+)
                )?
                (?:
                    [#](?P<fragment>.+)
                )?$
            ~xi',
            $uri,
            $match
        );
        $port = $match['port']??null;
        $unknown = null;
        if ($port !== null && !is_numeric(trim($port))
            || preg_match('~[^0-9]~', trim(trim($port)))
        ) {
            $unknown = $port;
            $port = null;
        } else {
            $port = (int) trim($port);
        }

        return [
            'protocol' => $match['protocol']??null,
            'username' => $match['username']??null,
            'password' => $match['password']??null,
            'host'     => $match['host']??null,
            'port'     => $port,
            'path'     => $match['path']??null,
            'query'    => $match['query']??null,
            'fragment' => $match['fragment']??null,
            'other'    => $unknown,
        ];
    }

    public static function getBaseUri(
        Application|App $app,
        ServerRequestInterface $request
    ) : UriInterface {
        $basePath = $app->getBasePath();
        $uri = $request->getUri();
        return $uri
            ->withPath($basePath)
            ->withQuery('')
            ->withFragment('');
    }
}
