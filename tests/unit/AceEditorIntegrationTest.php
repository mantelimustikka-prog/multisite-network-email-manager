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
        $this->assertStringContainsString('data-mnem-editor-toggle="1"', $contents);
        $this->assertStringContainsString('Switch to Visual Editor', $contents);
        $this->assertStringContainsString('Using Code Editor', $contents);
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

    public function test_admin_js_persists_campaign_editor_preference(): void
    {
        $path = __DIR__ . '/../../assets/admin.js';
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('mnem_editor_preference', $contents);
        $this->assertStringContainsString('data-mnem-editor-toggle-wrap', $contents);
        $this->assertStringContainsString('Switch to Code Editor', $contents);
    }
}
