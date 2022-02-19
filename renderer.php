<?php
    function render($file, $punishments = false): string {
        $content = json_decode(base64_decode($file['content']));
        $output = ["## $content->name"];

        foreach ($content->parts as $part) {
            if ($part->number) {
                $output[] = "### Раздел $part->number. $part->name.";
            } else $output[] = "### $part->name";
            if ($part->description) $output[] = $part->description . '  ';
            foreach ($part->rules as $rule) $output[] = renderRule($rule, $punishments);
            $output[] = "  ";
        }

        if ($content->last_update) $output[] = '*Последнее обновление: ' . date('d.m.Y', strtotime($content->last_update)) . '*';
        return join("\n", $output);
    }

    function renderRule($rule, bool $punishments = false, int $level = 0): string {
        $name = $rule->name ? "**$rule->name.**" : '•';
        $output = [str_repeat('&nbsp; ', $level) . "$name $rule->text  "];
        if ($rule->rules) foreach ($rule->rules as $subrule) $output[] = renderRule($subrule, $punishments, $level + 1);
        if ($rule->punishments && $punishments) {
            if (count($rule->punishments) > 1) {
                $output[] = str_repeat('&nbsp; ', $level + 1) . "**Наказания:**  ";
                foreach ($rule->punishments as $punishment) $output[] = str_repeat('&nbsp; ', $level + 1) . "• $punishment  ";
            } else $output[] = str_repeat('&nbsp; ', $level + 1) . "**Наказание:** " . $rule->punishments[0] . "  ";
        }

        return join("\n", $output);
    }