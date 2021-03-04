<?php

    use Github\Exception\MissingArgumentException;

    require 'vendor/autoload.php';
    include 'settings.php';
    include 'renderer.php';
    include 'authentication.php';

    set_exception_handler(function($e) {
        //header('HTTP/1.1 500 Internal Server Error');
        echo $e;
        die();
    });

    $input = file_get_contents('php://input');
    if (!isset($_SERVER['HTTP_X_HUB_SIGNATURE'])) {
        list($algo, $hash) = explode('=', $_SERVER['HTTP_X_HUB_SIGNATURE'], 2) + array('', '');
        if (!in_array($algo, hash_algos(), true)) {
            echo 'Hashing algorithm is not supported';
            die();
        }

        if (!hash_equals($hash, hash_hmac($algo, $input, WEBHOOK_SECRET))) {
            echo 'Wrong signature';
            die();
        }
    }

    $payload = json_decode($input);
    switch (strtolower($_SERVER['HTTP_X_GITHUB_EVENT'])) {
        case 'ping':
            echo 'pong';
            break;
        case 'push':
            if (strpos($payload->ref, 'rule-update') !== false) return;
            $client = authenticate($payload->installation->id);
            $modified_files = $payload->head_commit->modified;
            $renders = [];
            $file_cache = [];

            foreach ($modified_files as $filename) if (strpos($filename, "rules/") !== false) {
                $file = $client->repo()->contents()->show(REPOSITORY_USER, REPOSITORY_NAME, $filename, $payload->ref);
                $file_cache[$filename] = $file;
                $renders[$filename] = render($file);
            }

            if (count($renders) == 0) return;

            try {
                $branch = 'rule-update/' . date('Ymd-His');
                $base_branch = $client->repos()->branches(REPOSITORY_USER, REPOSITORY_NAME, RULES_BRANCH);
                $reference = $client->gitData()->references()->create(REPOSITORY_USER, REPOSITORY_NAME, [
                    'ref' => 'refs/heads/' . $branch,
                    'sha' => $base_branch['commit']['sha']
                ]);

                foreach ($renders as $path => $render) {
                    $rule_path = str_replace('rules/', '', str_replace('.json', '', $path)) . '.md';
                    $old_file = $client->repo()->contents()->show(REPOSITORY_USER, REPOSITORY_NAME, $rule_path, $reference['ref']);
                    if (!$old_file) continue;

                    $new_file = $client->repo()->contents()->update(REPOSITORY_USER, REPOSITORY_NAME, $rule_path, $render,
                        'Обновление правил для ' . $path, $old_file['sha'], $branch, [
                            'name' => 'RulesBot',
                            'email' => 'admin@bortexel.ru'
                        ]
                    );
                }

                $client->pullRequest()->create(REPOSITORY_USER, REPOSITORY_NAME, [
                    'base' => RULES_BRANCH,
                    'head' => $branch,
                    'title' => 'Изменения в правилах от ' . date('d.m.Y'),
                    'body' => 'Автоматически сгенерированный из JSON описания текст правил. Тексты сгенерированы, т.к. были изменены файлы `'
                        . join(array_keys($file_cache), '`, `') . '`. Изменения: ' . $payload->compare
                ]);
            } catch (MissingArgumentException $e) { }
            break;
        default:
            header('HTTP/1.0 404 Not Found');
            die();
    }