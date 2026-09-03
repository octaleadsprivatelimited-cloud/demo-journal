<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RichTextSanitizer;
use PHPUnit\Framework\TestCase;

class RichTextSanitizerTest extends TestCase
{
    public function test_it_keeps_editorial_markup_and_removes_executable_content(): void
    {
        $html = <<<'HTML'
            <style>body{display:none}</style><script>alert(1)</script>
            <h2 onclick="run()">Finding</h2>
            <p><a href="javascript:alert(1)" target="_blank">bad link</a></p>
            <table><tr><td colspan="2">Evidence</td></tr></table>
            HTML;

        $clean = (new RichTextSanitizer)->sanitize($html);

        $this->assertStringContainsString('<h2>Finding</h2>', $clean);
        $this->assertStringContainsString('<table>', $clean);
        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
    }

    public function test_it_only_allows_privacy_preserving_video_embeds(): void
    {
        $html = <<<'HTML'
            <iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ" onload="alert(1)"></iframe>
            <iframe src="https://player.vimeo.com/video/76979871"></iframe>
            <iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>
            <iframe src="https://example.com/embed/76979871"></iframe>
            HTML;

        $clean = (new RichTextSanitizer)->sanitize($html);

        $this->assertStringContainsString('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $clean);
        $this->assertStringContainsString('https://player.vimeo.com/video/76979871', $clean);
        $this->assertStringContainsString('sandbox="allow-scripts allow-same-origin allow-presentation"', $clean);
        $this->assertStringNotContainsString('onload', $clean);
        $this->assertStringNotContainsString('https://www.youtube.com', $clean);
        $this->assertStringNotContainsString('https://example.com', $clean);
    }
}
