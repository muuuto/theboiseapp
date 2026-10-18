<?php

namespace Tests\Unit;

use App\Support\SafeHtml;
use PHPUnit\Framework\TestCase;

class SafeHtmlTest extends TestCase
{
    public function test_keeps_tinymce_formatting(): void
    {
        $html = '<p style="font-size: 18pt;">Ciao <strong>a</strong> <em>tutti</em></p><ul><li>uno</li></ul>'
            . '<table><tbody><tr><td>cella</td></tr></tbody></table>';

        $this->assertEquals($html, SafeHtml::clean($html));
    }

    public function test_keeps_links_and_hearts(): void
    {
        $clean = SafeHtml::clean('check <a href="https://www.youtube.com/watch?v=dQw4w9WgXcQ">here</a> <3');

        $this->assertStringContainsString('href="https://www.youtube.com/watch?v&#61;dQw4w9WgXcQ"', $clean);
        $this->assertStringContainsString('&lt;3', $clean);
    }

    public function test_removes_scripts_and_event_handlers(): void
    {
        $clean = SafeHtml::clean(
            '<p onclick="alert(1)">hi</p><script>alert(2)</script><a href="javascript:alert(3)">x</a><img src=x onerror=alert(4)>'
        );

        $this->assertStringNotContainsString('alert', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringContainsString('<p>hi</p>', $clean);
    }

    public function test_handles_empty_values(): void
    {
        $this->assertSame('', SafeHtml::clean(null));
        $this->assertSame('', SafeHtml::clean(''));
    }
}
