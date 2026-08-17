<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class CKEditorIntegrationTest extends TestCase
{
    public function test_campaigns_view_uses_ckeditor_textarea_markup(): void
    {
        $path = __DIR__ . '/../../admin/views/campaigns.php';
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('data-mnem-ckeditor="1"', $contents);
        $this->assertStringContainsString('data-mnem-ckeditor-height="450px"', $contents);
        $this->assertStringNotContainsString('data-mnem-ace=', $contents);
        $this->assertStringNotContainsString('mnem-ace-editor-source', $contents);
        $this->assertStringNotContainsString('data-mnem-editor-toggle=', $contents);
        $this->assertStringNotContainsString('wp_editor(', $contents);
    }

    public function test_dashboard_view_uses_ckeditor_textarea_markup(): void
    {
        $path = __DIR__ . '/../../admin/views/dashboard.php';
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('data-mnem-ckeditor="1"', $contents);
        $this->assertStringNotContainsString('data-mnem-ace=', $contents);
        $this->assertStringNotContainsString('mnem-ace-editor-source', $contents);
    }

    public function test_admin_js_initialises_ckeditor_via_window_ckeditor(): void
    {
        $path = __DIR__ . '/../../assets/admin.js';
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('window.CKEDITOR', $contents);
        $this->assertStringContainsString('ClassicEditor', $contents);
        $this->assertStringContainsString('sourceEditing', $contents);
        $this->assertStringNotContainsString('initAceEditors', $contents);
        $this->assertStringNotContainsString('data-mnem-editor-toggle-wrap', $contents);
    }
}
