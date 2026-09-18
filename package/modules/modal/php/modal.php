<?php

declare(strict_types=1);

namespace __COMPONENT__\local\adeluxe\modal;

use moodle_url;

final class modal
{
    public function __construct(
        private readonly string $title,
        private readonly moodle_url $url,
    ) {
    }

    public function export_for_template(): array
    {
        return [
            'title' => $this->title,
            'url' => $this->url->out(false),
        ];
    }
}
