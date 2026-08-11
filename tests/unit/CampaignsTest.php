<?php

defined('ABSPATH') || exit;

use MNEM\Campaigns;
use PHPUnit\Framework\TestCase;

class CampaignsTest extends TestCase
{
    public function test_valid_statuses_contains_expected_values()
    {
        $this->assertContains('draft', Campaigns::VALID_STATUSES);
        $this->assertContains('scheduled', Campaigns::VALID_STATUSES);
        $this->assertContains('sending', Campaigns::VALID_STATUSES);
        $this->assertContains('sent', Campaigns::VALID_STATUSES);
        $this->assertContains('cancelled', Campaigns::VALID_STATUSES);
    }

    public function test_valid_transition_logic()
    {
        $this->assertTrue(Campaigns::is_valid_transition('draft', 'scheduled'));
        $this->assertTrue(Campaigns::is_valid_transition('scheduled', 'sending'));
        $this->assertFalse(Campaigns::is_valid_transition('draft', 'sent'));
        $this->assertFalse(Campaigns::is_valid_transition('sent', 'draft'));
    }
}
