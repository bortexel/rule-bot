<?php
    use Firebase\JWT\JWT;
    use GuzzleHttp\Client;

    function authenticate(int $installation_id): Github\Client {
        $builder = new Github\HttpClient\Builder(new Client);
        $github = new Github\Client($builder, 'machine-man-preview');

        $jwt = JWT::encode([
            'iss' => APP_ID,
            'iat' => time(),
            'exp' => time() + 600
        ], file_get_contents(PRIVATE_KEY_PATH), 'RS256');

        $github->authenticate($jwt, null, Github\Client::AUTH_JWT);
        $token = $github->apps()->createInstallationToken($installation_id);
        $github->authenticate($token['token'], null, Github\Client::AUTH_ACCESS_TOKEN);
        return $github;
    }