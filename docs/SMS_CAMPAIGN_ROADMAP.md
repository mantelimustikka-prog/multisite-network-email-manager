# SMS Campaign Feature Roadmap

**Document Status:** Active Planning  
**Last Updated:** August 26, 2026  
**Target Release:** Phase 2 (Post SMS Core Stabilization)

---

## Overview

This roadmap outlines the complete SMS Campaign feature implementation, following the existing Email Campaign architecture. The SMS Campaign UI will allow administrators to compose and send SMS messages to SMS subscriber lists with scheduling, delivery tracking, and analytics.

---

## Phase 1: SMS Core Stabilization ✅ (CURRENT)

**Status:** In Progress  
**Duration:** Estimated 2-3 days  
**Blocker for Phase 2:** YES

### Phase 1.1: SMS Test Connection Button Fix
**Status:** In Implementation  
**Task:** https://github.com/mantelimustikka-prog/multisite-network-email-manager/tasks/b1298925-ac36-4796-a457-e2f51e885c1b

**What:**
- Add JavaScript event listener for SMS Test Connection button
- Implement AJAX endpoint `ajax_test_sms_connection()`
- Wire to SMS provider's `test_connection()` method
- Display success (green ✓) or error (red ✗) messages

**Why Critical:** Admins need to validate SMS provider connectivity before creating campaigns.

**Success Criteria:**
- ✅ Button click triggers "Testing..." state
- ✅ Backend calls SMS provider test
- ✅ Green success or red error message displays
- ✅ No console errors

---

### Phase 1.2: Multi-Country Phone Validation
**Status:** In Implementation  
**Task:** https://github.com/mantelimustikka-prog/multisite-network-email-manager/tasks/9a87dda0-1bd1-4b12-857d-545716e22871

**What:**
- Expand PhoneValidator to support any country (not just 6 hardcoded)
- Return rich metadata (country, format detection, ambiguity detection)
- Update SmsSettings with multi-country options
- Add country hints in UI forms
- Enhanced invalid phone tracking

**Why Critical:** SMS campaigns with multi-country subscriber lists fail silently with single-country validation.

**Success Criteria:**
- ✅ Swedish number `4677653819` without country → REJECTED as ambiguous
- ✅ Swedish number with explicit country SE → ACCEPTED as `+46467653819`
- ✅ E.164 format `+46467653819` → ACCEPTED immediately
- ✅ All existing tests pass
- ✅ Backward compatible (single-country mode default)

---

### Phase 1.3: SMS Bulk Add Phone Number Display
**Status:** Partially Complete  
**Related Issue:** Phone numbers not displayed in SMS bulk add UI

**What:**
- Replace "Email" column with "Phone Number" in Step 1 user selection table
- Show resolved phone numbers from user meta
- Display country detection in preview
- Flag ambiguous phone numbers for review

**Why Critical:** Admins can't verify they're adding correct subscribers before campaign creation.

**Success Criteria:**
- ✅ User selection table shows Phone Number column
- ✅ Phone numbers are resolved from user meta
- ✅ Step 3 Preview shows phone numbers (not emails)
- ✅ Ambiguous numbers flagged before commit

---

## Phase 2: SMS Campaign UI Implementation 📋 (NEXT)

**Status:** Planned  
**Duration:** Estimated 3-5 days  
**Dependencies:** Phase 1 (all items) completed  
**Blocks:** SMS campaign functionality

### Phase 2.1: Database Schema Enhancement

**New Tables:**
```sql
mnem_sms_campaigns
- id
- site_id
- name
- message_body
- sms_list_id
- status (draft, scheduled, sending, paused, completed, cancelled)
- total_recipients
- sent_count
- failed_count
- delivery_status_map (JSON - delivery tracking)
- scheduled_at
- started_at
- completed_at
- created_at
- updated_at
- created_by
```

**Enhanced Tables:**
```sql
mnem_queue
- Add: sms_campaign_id (nullable, for SMS tracking)
- Add: phone_number (for SMS delivery)
- Add: message_type (email|sms)
```

**Rationale:**
- Mirrors email campaign structure
- Supports campaign lifecycle (draft → scheduled → sending → completed)
- Tracks delivery metrics per campaign
- Links SMS sends to campaign for analytics

---

### Phase 2.2: SMS Campaign Management Page

**File:** `admin/views/sms-campaigns.php`

**Features:**

#### Campaign List View
- Table with columns:
  - Name
  - SMS List (recipient count)
  - Status (badge: draft/scheduled/sending/paused/completed)
  - Recipients / Sent / Failed
  - Scheduled Date
  - Actions (Edit/Preview/Send/Pause/Cancel/Delete)
- Filters:
  - Status filter
  - SMS list filter
  - Date range
- Bulk actions:
  - Delete campaigns
  - Change status

#### Campaign Create/Edit Form
- **Basic Info:**
  - Campaign Name
  - Description (optional)
  
- **Message Composition:**
  - SMS Message Body (text only, no HTML)
  - Character counter (SMS limits: ~160 chars per segment)
  - Segment count preview
  - Cost estimate (if available from provider)
  
- **Recipient Selection:**
  - SMS List selector (dropdown)
  - Preview recipient count
  - Filter preview (status: subscribed/unsubscribed/all)
  
- **Delivery Options:**
  - Send Mode:
    - Send Immediately
    - Schedule for Later (date/time picker)
    - Send in batches (rate limiting)
  - Rate Limiting:
    - SMS per hour
    - SMS per minute
  - Delivery Window:
    - Respect "No SMS Hours" setting
    - Timezone selector
  
- **Advanced Options:**
  - Test recipient (send preview to single number)
  - Short URL tracking (if provider supports)
  - Delivery notifications
  
- **Preview:**
  - Sample message preview
  - Recipient sample list
  - Cost/segment breakdown

---

### Phase 2.3: SMS Campaign Lifecycle Controller

**File:** `admin/class-network-admin.php` (new methods)

**Methods:**

```php
// CRUD Operations
public function handle_create_sms_campaign()
public function handle_update_sms_campaign()
public function handle_delete_sms_campaign()

// Campaign Actions
public function handle_send_sms_campaign()
public function handle_schedule_sms_campaign()
public function handle_pause_sms_campaign()
public function handle_resume_sms_campaign()
public function handle_cancel_sms_campaign()

// Preview & Testing
public function handle_send_sms_test()
public function ajax_preview_sms_recipients()

// Tracking
public function ajax_get_sms_campaign_stats()
```

---

### Phase 2.4: SMS Campaign Model Class

**File:** `includes/class-sms-campaigns.php`

**Methods:**

```php
class SmsCampaigns
{
    // CRUD
    public static function create($site_id, $data)
    public static function get($campaign_id)
    public static function update($campaign_id, $data)
    public static function delete($campaign_id)
    public static function get_list($site_id, $status, $per_page, $offset)
    
    // Lifecycle
    public static function send_now($campaign_id)
    public static function schedule($campaign_id, $scheduled_at)
    public static function pause($campaign_id)
    public static function resume($campaign_id)
    public static function cancel($campaign_id)
    
    // Delivery
    public static function queue_recipients($campaign_id)
    public static function get_delivery_stats($campaign_id)
    public static function get_failed_recipients($campaign_id)
    
    // Testing
    public static function send_test($campaign_id, $test_phone)
    
    // Helpers
    public static function validate_campaign_data($data)
    public static function get_recipient_count($sms_list_id)
    public static function calculate_segments($message_body)
}
```

---

### Phase 2.5: SMS Queue Integration

**Modifications to:** `includes/class-queue.php`

**What:**
- Support SMS message type in queue
- Phone number validation before queuing
- SMS-specific delivery handling
- Tracking updates from SMS providers (delivery, bounce, etc.)

**Implementation:**
```php
// Queue SMS campaign recipients
Queue::enqueue_sms_campaign($campaign_id, $sms_list_id, $message_body)

// Process SMS from queue (existing process_item works)
// Add SMS-specific status mapping
// Handle SMS provider responses
```

---

### Phase 2.6: Admin UI Components

**Updates to:** `admin/class-admin-menu.php`

**New Menu Item:**
```php
add_submenu_page(
    'mnem-dashboard', 
    'SMS Campaigns', 
    'SMS Campaigns', 
    'manage_network_options', 
    'mnem-sms-campaigns', 
    array($this, 'render_sms_campaigns')
);
```

**Updates to Dashboard:**
- SMS campaign stats widget
- Recent SMS campaigns
- Delivery performance chart

---

### Phase 2.7: Comprehensive Tests

**Files:** `tests/unit/SmsCampaignsTest.php`, `tests/unit/NetworkAdminTest.php`

**Test Cases:**
```php
// Creation & Validation
test_create_sms_campaign_with_valid_data()
test_create_sms_campaign_validates_name()
test_create_sms_campaign_validates_sms_list()
test_create_sms_campaign_validates_message_body()

// Lifecycle
test_send_campaign_immediately()
test_schedule_campaign_for_later()
test_pause_campaign_stops_sending()
test_resume_campaign_continues()
test_cancel_campaign_rolls_back()

// Queue Integration
test_queue_sms_campaign_recipients()
test_queue_respects_rate_limits()
test_queue_respects_no_sms_hours()

// Multi-Country
test_send_campaign_to_mixed_country_subscribers()
test_phone_validation_catches_invalid_numbers()

// Tracking
test_campaign_stats_update_correctly()
test_delivery_status_maps_to_queue_status()
```

---

## Phase 3: Advanced Features 🚀 (FUTURE)

**Status:** Post-Launch  
**Dependencies:** Phase 2 complete

### Planned Features:
- SMS Template Library
- Message Variables (subscriber name, custom fields)
- Delivery Analytics Dashboard
- SMS Response/Reply Handling
- Geo-targeted sending
- A/B Testing for SMS
- Integration with Customer Data Platform (CDP)
- SMS Compliance (GDPR, TCPA, etc.)

---

## Technical Architecture

### Data Flow: SMS Campaign Lifecycle

```
Admin Creates Campaign
        ↓
Validates Data (message, list, schedule)
        ↓
Saves to mnem_sms_campaigns table
        ↓
Admin Sends/Schedules
        ↓
Queue SMS to recipients
        ↓
Add rows to mnem_queue (message_type=sms)
        ↓
Cron/Async processes queue
        ↓
SMS Provider sends SMS
        ↓
Provider confirms delivery
        ↓
Update queue status
        ↓
Campaign stats updated
        ↓
Reporting/Analytics
```

### File Structure

```
admin/
├── class-network-admin.php          (SMS campaign handlers)
├── views/
│   ├── sms-campaigns.php            (Campaign list & edit UI)
│   └── sms-campaign-form.php        (Reusable form component)

includes/
├── class-sms-campaigns.php          (Campaign model & business logic)
└── interfaces/
    └── class-sms-provider-interface.php (Already exists)

tests/unit/
├── SmsCampaignsTest.php             (Campaign model tests)
└── NetworkAdminTest.php             (Campaign controller tests)

docs/
└── SMS_CAMPAIGN_ROADMAP.md          (This file)
```

---

## Comparison: Email vs SMS Campaigns

| Feature | Email | SMS | Notes |
|---------|-------|-----|-------|
| Compose | HTML editor | Text only | SMS = 160 chars/segment |
| Scheduling | Date/Time picker | ✅ Same | |
| Rate Limiting | Per hour/day | ✅ Same | SMS more strict |
| No Hours Window | Optional | ✅ Same | Respect user preferences |
| Multi-Country | N/A | ✅ NEW | SMS requires phone validation |
| Tracking | Opens/Clicks | ✅ Delivery only | Provider-dependent |
| Templates | Yes | ✅ Planned Phase 3 | SMS templates simpler |
| Validation | Email format | ✅ Phone validation | Country-aware |
| Queue | Same system | ✅ Integrated | Uses existing Queue table |

---

## Success Criteria

### Phase 1 (Core Stabilization)
- ✅ SMS Test Connection works reliably
- ✅ Multi-country validation prevents silent failures
- ✅ Bulk SMS add shows phone numbers clearly
- ✅ All existing tests pass
- ✅ No regression in SMS functionality

### Phase 2 (Campaign UI)
- ✅ Create, edit, delete SMS campaigns
- ✅ Send immediately or schedule for future
- ✅ Rate limiting works correctly
- ✅ Delivery tracking integrates with queue
- ✅ Admin stats dashboard shows SMS metrics
- ✅ Multi-country campaigns work end-to-end
- ✅ All new tests pass
- ✅ Feature parity with Email Campaigns (minus HTML)

---

## Risk Mitigation

### Risks:
1. **Phone Number Quality** - Garbage in = no delivery
   - Mitigation: Multi-country validation (Phase 1.2)
   
2. **Rate Limiting Issues** - SMS provider throttling
   - Mitigation: Configurable rate limits, queue backoff
   
3. **Cost Control** - Accidental mass sends
   - Mitigation: Cost preview, send confirmations
   
4. **Compliance** - TCPA, GDPR requirements
   - Mitigation: Opt-in tracking, audit logs (Phase 3)

---

## Dependencies

### External:
- SMS Provider API (Twilio, Clicksend, etc.)
- Phone validation library (libphonenumber standards)

### Internal:
- Queue system (existing)
- Subscriber Lists (existing)
- Phone validation (Phase 1.2)
- User permissions (existing)

---

## Rollout Plan

### Alpha (Internal Testing)
- Manual testing with small subscriber sets
- SMS provider testing with real credits
- Team review of UI/UX

### Beta (Limited Deploy)
- Deploy to staging environment
- Test with real SMS provider
- Gather admin feedback

### Release (Production)
- Full documentation
- Release notes
- Admin training materials

---

## Success Metrics (Post-Launch)

- Campaign creation success rate > 98%
- SMS delivery success rate > 95%
- Average campaign creation time < 5 minutes
- Admin satisfaction score > 4.5/5
- Zero unintended mass sends
- Correct multi-country delivery > 99%

---

## Timeline Summary

| Phase | Duration | Start | End | Status |
|-------|----------|-------|-----|--------|
| **Phase 1** | 2-3 days | Now | Aug 28 | 🔄 In Progress |
| **Phase 2** | 3-5 days | Aug 28 | Sep 2 | 📋 Planned |
| **Phase 3** | TBD | Sep 2+ | TBD | 🚀 Future |

---

## Contact & Questions

For questions about this roadmap:
- Create an issue in the repository
- Reference this document (docs/SMS_CAMPAIGN_ROADMAP.md)
- Tag @mantelimustikka-prog

---

## Document History

| Date | Version | Changes |
|------|---------|---------|
| 2026-08-26 | 1.0 | Initial roadmap created |

---

**Last Updated:** August 26, 2026  
**Next Review:** September 2, 2026
