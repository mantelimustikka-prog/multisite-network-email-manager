<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class CKEditorIntegrationTest extends TestCase
{
    public function test_campaigns_view_uses_quill_editor_markup(): void
    {
        $path = __DIR__ . '/../../admin/views/campaigns.php';
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('data-mnem-quill="1"', $contents);
        $this->assertStringContainsString('mnem-quill-editor', $contents);
        $this->assertStringContainsString('mnem-quill-source', $contents);
        $this->assertStringNotContainsString('data-mnem-ckeditor', $contents);
        $this->assertStringNotContainsString('wp_editor(', $contents);
    }

    public function test_dashboard_view_uses_quill_editor_markup(): void
    {
        $path = __DIR__ . '/../../admin/views/dashboard.php';
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('data-mnem-quill="1"', $contents);
        $this->assertStringContainsString('mnem-quill-editor', $contents);
        $this->assertStringContainsString('mnem-quill-source', $contents);
        $this->assertStringNotContainsString('data-mnem-ckeditor', $contents);
    }

    public function test_admin_js_initializes_quill(): void
    {
        $path = __DIR__ . '/../../assets/admin.js';
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('window.Quill', $contents);
        $this->assertStringContainsString('data-mnem-quill', $contents);
        $this->assertStringNotContainsString('window.CKEDITOR', $contents);
        $this->assertStringNotContainsString('data-mnem-ckeditor', $contents);
    }

    public function test_settings_header_footer_view_uses_quill_editor_markup(): void
    {
        $path = __DIR__ . '/../../admin/views/settings-header-footer.php';
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('data-mnem-quill="1"', $contents);
        $this->assertStringContainsString('mnem-quill-editor', $contents);
        $this->assertStringNotContainsString('data-mnem-ckeditor', $contents);
    }

    public function test_email_templates_view_uses_quill_editor_markup(): void
    {
        $path = __DIR__ . '/../../admin/views/email-templates.php';
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('data-mnem-quill="1"', $contents);
        $this->assertStringContainsString('mnem-quill-editor', $contents);
        $this->assertStringNotContainsString('wp_editor(', $contents);
        $this->assertStringNotContainsString('data-mnem-ckeditor', $contents);
    }

    public function test_network_admin_enqueues_quill_not_ckeditor(): void
    {
        $path = __DIR__ . '/../../admin/class-network-admin.php';
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents);
        $this->assertStringContainsString('cdn.quilljs.com', $contents);
        $this->assertStringContainsString('quill.core.css', $contents);
        $this->assertStringContainsString('quill.snow.css', $contents);
        $this->assertStringContainsString('quill.js', $contents);
        $this->assertStringNotContainsString('cdn.ckeditor.com', $contents);
        $this->assertStringNotContainsString('ckeditor5', $contents);
    }
}
