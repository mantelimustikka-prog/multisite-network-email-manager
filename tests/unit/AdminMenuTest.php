<?php

defined('ABSPATH') || exit;

use MNEM\Admin\AdminMenu;
use PHPUnit\Framework\TestCase;

class AdminMenuTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['mnem_menu_pages'] = array();
        $GLOBALS['mnem_submenu_pages'] = array();
        $_GET = array();
    }

    public function test_register_menus_uses_network_email_manager_and_removes_smtp_submenus()
    {
        $menu = new AdminMenu();
        $menu->register_menus();

        $this->assertNotEmpty($GLOBALS['mnem_menu_pages']);
        $this->assertSame('Network Email Manager', $GLOBALS['mnem_menu_pages'][0][1]);

        $submenu_slugs = array_map(static function ($submenu) {
            return $submenu[4];
        }, $GLOBALS['mnem_submenu_pages']);

        $this->assertNotContains('mnem-smtp-settings', $submenu_slugs);
        $this->assertNotContains('mnem-smtp-diagnostics', $submenu_slugs);
        $this->assertNotContains('mnem-error-logs', $submenu_slugs);
    }

    public function test_render_queue_applies_email_and_subject_search_filters()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_var($query)
            {
                $this->queries[] = $query;
                if (strpos($query, "SELECT COUNT(1) FROM wp_mnem_queue WHERE status = 'pending' AND LOWER(recipient_email) LIKE '%john@example.com%' AND LOWER(subject) LIKE '%password reset%'") !== false) {
                    return 2;
                }
                if (strpos($query, 'SELECT COUNT(1) FROM wp_mnem_queue') !== false) {
                    return 10;
                }
                return 0;
            }

            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, 'SELECT id, blog_id, campaign_id, recipient_email, subject, status') !== false) {
                    return array(
                        array(
                            'id' => 1,
                            'blog_id' => 1,
                            'campaign_id' => 0,
                            'recipient_email' => 'john@example.com',
                            'subject' => 'Password Reset',
                            'status' => 'pending',
                            'attempts' => 0,
                            'scheduled_at' => '2026-08-17 10:00:00',
                            'sent_at' => null,
                            'opened' => null,
                            'clicked' => null,
                            'opens_count' => 0,
                            'clicks_count' => 0,
                            'created_at' => '2026-08-17 09:00:00',
                            'provider_message_id' => '',
                            'provider_metadata' => '',
                        ),
                    );
                }
                return array();
            }

            public function get_col($query)
            {
                $this->queries[] = $query;
                return array('pending', 'sent');
            }

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array('scheduled_at' => '', 'attempts' => 0);
            }
        };

        $_GET = array(
            'page' => 'mnem-queue',
            'status_filter' => 'pending',
            'search_email' => 'John@Example.com',
            'search_subject' => 'Password Reset',
            'per_page' => 10,
            'paged' => 1,
        );

        $menu = new AdminMenu();
        ob_start();
        $menu->render_queue();
        $output = ob_get_clean();

        $queries = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString("LOWER(recipient_email) LIKE '%john@example.com%'", $queries);
        $this->assertStringContainsString("LOWER(subject) LIKE '%password reset%'", $queries);
        $this->assertStringContainsString("status = 'pending'", $queries);
        $this->assertStringContainsString('Searching for:', $output);
        $this->assertStringContainsString('Email: John@Example.com', $output);
        $this->assertStringContainsString('Subject: Password Reset', $output);
    }

    public function test_render_queue_pagination_preserves_search_parameters()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_var($query)
            {
                $this->queries[] = $query;
                if (strpos($query, 'SELECT COUNT(1) FROM wp_mnem_queue WHERE') !== false) {
                    return 25;
                }
                if (strpos($query, 'SELECT COUNT(1) FROM wp_mnem_queue') !== false) {
                    return 50;
                }
                return 0;
            }

            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, 'SELECT id, blog_id, campaign_id, recipient_email, subject, status') !== false) {
                    return array(
                        array(
                            'id' => 2,
                            'blog_id' => 1,
                            'campaign_id' => 0,
                            'recipient_email' => 'jane@example.com',
                            'subject' => 'Welcome',
                            'status' => 'pending',
                            'attempts' => 0,
                            'scheduled_at' => '2026-08-17 10:00:00',
                            'sent_at' => null,
                            'opened' => null,
                            'clicked' => null,
                            'opens_count' => 0,
                            'clicks_count' => 0,
                            'created_at' => '2026-08-17 09:00:00',
                            'provider_message_id' => '',
                            'provider_metadata' => '',
                        ),
                    );
                }
                return array();
            }

            public function get_col($query)
            {
                $this->queries[] = $query;
                return array('pending', 'sent');
            }

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array('scheduled_at' => '', 'attempts' => 0);
            }
        };

        $_GET = array(
            'page' => 'mnem-queue',
            'search_email' => 'jane@example.com',
            'search_subject' => 'Welcome',
            'per_page' => 10,
            'paged' => 2,
        );

        $menu = new AdminMenu();
        ob_start();
        $menu->render_queue();
        $output = ob_get_clean();

        $this->assertStringContainsString('search_email=jane%40example.com', $output);
        $this->assertStringContainsString('search_subject=Welcome', $output);
        $this->assertStringContainsString('Clear Search', $output);
    }
}
