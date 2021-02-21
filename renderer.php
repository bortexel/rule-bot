<?php
    function render($file): string {
        $content = json_decode(base64_decode($file['content']));
        $output = ["## $content->name"];

        foreach ($content->parts as $part) {
            $output[] = "### $part->number. $part->name";
            foreach ($part->rules as $rule) $output[] = renderRule($rule);
            $output[] = "  ";
        }

        if ($content->last_update) $output[] = '*Последнее обновление: ' . date('d.m.Y', strtotime($content->last_update)) . '*';
        return join("\n", $output);
    }

    function renderRule($rule, int $level = 0): string {
        $output = [str_repeat('&nbsp; ', $level) . "**$rule->name.** $rule->text  "];
        if ($rule->rules) foreach ($rule->rules as $subrule) $output[] = renderRule($subrule, $level + 1);
        return join("\n", $output);
    }