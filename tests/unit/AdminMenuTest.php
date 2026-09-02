<?php

defined('ABSPATH') || exit;

use MNEM\Admin\AdminMenu;
use MNEM\Admin\TableDiagnostics;
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

    public function test_register_menus_uses_updated_main_menu_title_and_removes_smtp_submenus()
    {
        $menu = new AdminMenu();
        $menu->register_menus();

        $this->assertNotEmpty($GLOBALS['mnem_menu_pages']);
        $this->assertSame('SMS Campaign & Network Emails', $GLOBALS['mnem_menu_pages'][0][1]);

        $submenu_slugs = array_map(static function ($submenu) {
            return $submenu[4];
        }, $GLOBALS['mnem_submenu_pages']);

        $this->assertNotContains('mnem-smtp-settings', $submenu_slugs);
        $this->assertNotContains('mnem-smtp-diagnostics', $submenu_slugs);
        $this->assertNotContains('mnem-error-logs', $submenu_slugs);
        $this->assertContains('mnem-invalid-phone-numbers', $submenu_slugs);
    }

    public function test_register_menus_sets_updated_visible_titles_for_submenus()
    {
        $menu = new AdminMenu();
        $menu->register_menus();
        $menu->register_logs_submenu();

        $submenu_titles_by_slug = array();
        foreach ($GLOBALS['mnem_submenu_pages'] as $submenu) {
            $submenu_titles_by_slug[$submenu[4]] = $submenu[2];
        }

        $this->assertSame('Email Campaigns', $submenu_titles_by_slug['mnem-campaigns']);
        $this->assertSame('Email Subscribers Lists', $submenu_titles_by_slug['mnem-subscriber-lists']);
        $this->assertSame('Email Event Rules', $submenu_titles_by_slug['mnem-user-event-rules']);
        $this->assertSame('Email Suppression', $submenu_titles_by_slug['mnem-suppression']);
        $this->assertSame('Add Bulk Email Subscribers', $submenu_titles_by_slug['mnem-subscriber-lists-bulk-add']);
        $this->assertSame('Logs', $submenu_titles_by_slug['mnem-logs']);
        $this->assertSame('Add Bulk SMS Subscribers', $submenu_titles_by_slug['mnem-sms-subscriber-lists-bulk-add']);
    }

    public function test_register_menus_groups_email_and_sms_submenus_with_separators_and_logs_last()
    {
        $menu = new AdminMenu();
        $diagnostics = new TableDiagnostics();

        $menu->register_menus();
        $diagnostics->register_submenu();
        $menu->register_logs_submenu();

        $dashboard_submenus = array_values(array_filter($GLOBALS['mnem_submenu_pages'], static function ($submenu) {
            return 'mnem-dashboard' === $submenu[0];
        }));

        $dashboard_submenu_slugs = array_map(static function ($submenu) {
            return $submenu[4];
        }, $dashboard_submenus);

        $this->assertSame(
            array(
                'mnem-dashboard',
                'mnem-settings',
                'mnem-separator-email',
                'mnem-campaigns',
                'mnem-subscriber-lists',
                'mnem-user-event-rules',
                'mnem-suppression',
                'mnem-subscriber-lists-bulk-add',
                'mnem-separator-sms',
                'mnem-sms-campaigns',
                'mnem-sms-subscriber-lists',
                'mnem-invalid-phone-numbers',
                'mnem-sms-subscriber-lists-bulk-add',
                'mnem-separator-space',
                'mnem-table-diagnosis',
                'mnem-logs',
            ),
            $dashboard_submenu_slugs
        );

        $this->assertSame(null, $dashboard_submenus[2][5]);
        $this->assertSame(null, $dashboard_submenus[8][5]);
        $this->assertSame(null, $dashboard_submenus[13][5]);
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

    public function test_render_queue_shows_force_delete_for_processing_items()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_var($query)
            {
                $this->queries[] = $query;
                if (strpos($query, 'SELECT COUNT(1) FROM wp_mnem_queue') !== false) {
                    return 1;
                }
                return 0;
            }

            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, 'SELECT id, blog_id, campaign_id, recipient_email, subject, status') !== false) {
                    return array(
                        array(
                            'id' => 9,
                            'blog_id' => 1,
                            'campaign_id' => 0,
                            'recipient_email' => 'stuck@example.com',
                            'subject' => 'Stuck',
                            'status' => 'processing',
                            'attempts' => 1,
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
                return array('processing');
            }

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array('scheduled_at' => '', 'attempts' => 0);
            }
        };

        $_GET = array(
            'page' => 'mnem-queue',
            'per_page' => 10,
            'paged' => 1,
        );

        $menu = new AdminMenu();
        ob_start();
        $menu->render_queue();
        $output = ob_get_clean();

        $this->assertStringContainsString('name="queue_ids[]" form="mnem-bulk-form" value="9"', $output);
        $this->assertStringNotContainsString('name="queue_ids[]" form="mnem-bulk-form" value="9" disabled="disabled"', $output);
        $this->assertStringContainsString('data-force-delete="1"', $output);
        $this->assertStringContainsString('>Force Delete</button>', $output);
    }

    public function test_render_subscriber_lists_applies_search_and_pagination_controls()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_var($query)
            {
                $this->queries[] = $query;
                if (strpos($query, "subscription_status = 'subscribed'") !== false) {
                    return 1;
                }
                if (strpos($query, "subscription_status = 'unsubscribed'") !== false) {
                    return 1;
                }
                return 0;
            }

            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, 'FROM wp_mnem_subscriber_lists') !== false) {
                    return array(
                        array(
                            'id' => 5,
                            'name' => 'Weekly Updates',
                            'description' => '',
                            'created_at' => '2026-08-17 10:00:00',
                        ),
                    );
                }

                if (strpos($query, "subscription_status = 'subscribed'") !== false) {
                    return array(
                        array(
                            'user_id' => 7,
                            'user_login' => 'alice',
                            'user_email' => 'alice@example.com',
                            'subscribed_at' => '2026-08-17 10:00:00',
                            'unsubscribed_at' => null,
                            'unsubscribed_reason' => '',
                        ),
                    );
                }

                if (strpos($query, "subscription_status = 'unsubscribed'") !== false) {
                    return array(
                        array(
                            'user_id' => 8,
                            'user_login' => 'alice-old',
                            'user_email' => 'alice-old@example.com',
                            'subscribed_at' => '2026-08-10 10:00:00',
                            'unsubscribed_at' => '2026-08-20 10:00:00',
                            'unsubscribed_reason' => 'Requested',
                        ),
                    );
                }

                return array();
            }

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, 'FROM wp_mnem_subscriber_lists WHERE id = 5') !== false) {
                    return array(
                        'id' => 5,
                        'name' => 'Weekly Updates',
                        'description' => '',
                        'created_at' => '2026-08-17 10:00:00',
                    );
                }
                return null;
            }
        };

        $_GET = array(
            'page' => 'mnem-subscriber-lists',
            'list_id' => 5,
            'subscriber_search' => 'Alice',
            'subscriber_per_page' => 100,
            'subscribed_paged' => 1,
            'unsubscribed_paged' => 1,
        );

        $menu = new AdminMenu();
        ob_start();
        $menu->render_subscriber_lists();
        $output = ob_get_clean();

        $queries = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString("LOWER(u.user_login) LIKE '%alice%'", $queries);
        $this->assertStringContainsString("LOWER(u.user_email) LIKE '%alice%'", $queries);
        $this->assertStringContainsString('LIMIT 100 OFFSET 0', $queries);
        $this->assertStringContainsString('Search &amp; Pagination', $output);
        $this->assertStringContainsString('Clear Search', $output);
        $this->assertStringContainsString('Showing 1-1 of 1 records', $output);
        $this->assertStringContainsString('Unsubscribe', $output);
    }

    public function test_render_sms_subscriber_lists_includes_bulk_add_and_invalid_numbers_links()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_var($query)
            {
                $this->queries[] = $query;
                return 1;
            }

            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, 'FROM wp_mnem_sms_subscriber_lists') !== false) {
                    return array(
                        array(
                            'id' => 5,
                            'name' => 'SMS Weekly Updates',
                            'description' => '',
                            'created_at' => '2026-08-17 10:00:00',
                        ),
                    );
                }

                if (strpos($query, "subscription_status = 'subscribed'") !== false) {
                    return array(
                        array(
                            'user_id' => 7,
                            'user_login' => 'alice',
                            'phone_number' => '+12345678901',
                            'subscribed_at' => '2026-08-17 10:00:00',
                        ),
                    );
                }

                return array();
            }

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    'id' => 5,
                    'name' => 'SMS Weekly Updates',
                    'description' => '',
                    'created_at' => '2026-08-17 10:00:00',
                );
            }
        };

        $_GET = array(
            'page' => 'mnem-sms-subscriber-lists',
            'list_id' => 5,
        );

        $menu = new AdminMenu();
        ob_start();
        $menu->render_sms_subscriber_lists();
        $output = ob_get_clean();

        $this->assertStringContainsString('mnem-sms-subscriber-lists-bulk-add', $output);
        $this->assertStringContainsString('mnem-invalid-phone-numbers', $output);
    }

    public function test_render_logs_sms_tab_populates_provider_status_dropdown()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_col($query)
            {
                $this->queries[] = $query;
                if (strpos($query, 'SELECT DISTINCT provider_status FROM wp_mnem_sms_queue') !== false) {
                    return array('bounced', 'delivered', 'failed');
                }
                return array();
            }
        };

        $_GET = array(
            'page' => 'mnem-logs',
            'tab' => 'sms',
        );

        $menu = new AdminMenu();
        ob_start();
        $menu->render_logs();
        $output = ob_get_clean();

        $queries = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString('SELECT DISTINCT provider_status FROM wp_mnem_sms_queue', $queries);
        $this->assertStringContainsString("provider_status IS NOT NULL AND provider_status <> ''", $queries);
        $this->assertStringContainsString('ORDER BY provider_status ASC', $queries);

        $this->assertStringContainsString('<option value="bounced">', $output);
        $this->assertStringContainsString('<option value="delivered">', $output);
        $this->assertStringContainsString('<option value="failed">', $output);
        $this->assertStringContainsString('Bounced', $output);
        $this->assertStringContainsString('Delivered', $output);
        $this->assertStringContainsString('Failed', $output);
        $this->assertStringContainsString('sms_status=pending', $output);
    }
}
