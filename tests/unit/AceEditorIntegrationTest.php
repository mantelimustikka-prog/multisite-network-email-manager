<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class AceEditorIntegrationTest extends TestCase
{
    public function test_campaigns_view_uses_ace_editor_textarea_markup(): void
    {
        $path = __DIR__ . '/../../admin/views/campaigns.php';
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('data-mnem-ace="html"', $contents);
        $this->assertStringContainsString('mnem-ace-editor-source', $contents);
        $this->assertStringNotContainsString('wp_editor(', $contents);
    }

    public function test_dashboard_view_uses_ace_editor_textarea_markup(): void
    {
        $path = __DIR__ . '/../../admin/views/dashboard.php';
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('data-mnem-ace="html"', $contents);
        $this->assertStringContainsString('mnem-ace-editor-source', $contents);
    }
}
