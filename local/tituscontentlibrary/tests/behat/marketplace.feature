@local_tituscontentlibrary
Feature: Titus Content Library marketplace
  In order to expand course offerings from the Titus catalogue
  As a Moodle manager
  I need to browse, search, filter, and add Titus content as courses

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email              |
      | manager1 | Manager   | One      | mgr1@example.com   |
    And the following "system role assigns" exist:
      | user     | role    | contextlevel |
      | manager1 | manager | System       |

  # ---------------------------------------------------------------------------
  # Admin settings
  # ---------------------------------------------------------------------------

  Scenario: Admin can access the plugin settings page
    Given I log in as "admin"
    When I navigate to "Plugins > Local plugins > Titus Content Library" in site administration
    Then I should see "Titus API configuration"
    And I should see "Titus API base URL"
    And I should see "Licence key"
    And I should see "Default course category"
    And I should see "Catalogue cache lifetime"

  # ---------------------------------------------------------------------------
  # Marketplace access
  # ---------------------------------------------------------------------------

  Scenario: Manager can access the marketplace page
    Given the Titus catalogue cache is primed with content items
    And I log in as "manager1"
    When I visit "/local/tituscontentlibrary/index.php"
    Then I should see "Titus Content Catalogue"

  Scenario: Admin can access the marketplace page
    Given the Titus catalogue cache is primed with content items
    And I log in as "admin"
    When I visit "/local/tituscontentlibrary/index.php"
    Then I should see "Titus Content Catalogue"
    And I should see "Leadership Foundations"

  # ---------------------------------------------------------------------------
  # Catalogue display (JS)
  # ---------------------------------------------------------------------------

  @javascript
  Scenario: Marketplace displays all catalogue tiles when cache is primed
    Given the Titus catalogue cache is primed with content items
    And I log in as "manager1"
    When I visit "/local/tituscontentlibrary/index.php"
    Then I should see "Titus Content Catalogue"
    And I should see "Leadership Foundations"
    And I should see "Advanced Leadership"
    And I should see "Leadership in Crisis"
    And I should see "Python for Beginners"
    And "[data-region=\"titus-tile\"]" "css_element" should be visible

  # ---------------------------------------------------------------------------
  # Search filter (JS)
  # ---------------------------------------------------------------------------

  @javascript
  Scenario: Search filters tiles by title keyword
    Given the Titus catalogue cache is primed with content items
    And I log in as "manager1"
    And I visit "/local/tituscontentlibrary/index.php"
    And I should see "Leadership Foundations"
    When I set the field "Search" to "Crisis"
    And I wait "1" seconds
    Then I should see "Leadership in Crisis"
    And I should not see "Python for Beginners"
    And I should not see "Leadership Foundations"

  @javascript
  Scenario: Clear search button restores all tiles
    Given the Titus catalogue cache is primed with content items
    And I log in as "manager1"
    And I visit "/local/tituscontentlibrary/index.php"
    And I set the field "Search" to "Python"
    And I wait "1" seconds
    And I should see "Python for Beginners"
    And I should not see "Leadership Foundations"
    When I click on "[data-action=\"clear-search\"]" "css_element"
    And I wait "1" seconds
    Then I should see "Leadership Foundations"
    And I should see "Python for Beginners"

  # ---------------------------------------------------------------------------
  # Category filter (JS)
  # ---------------------------------------------------------------------------

  @javascript
  Scenario: Category pill filters tiles to show only that category
    Given the Titus catalogue cache is primed with content items
    And I log in as "manager1"
    And I visit "/local/tituscontentlibrary/index.php"
    And I should see "Python for Beginners"
    When I click on "Leadership" "button"
    And I wait "1" seconds
    Then I should see "Leadership Foundations"
    And I should not see "Python for Beginners"

  @javascript
  Scenario: All category pill restores all tiles after filtering
    Given the Titus catalogue cache is primed with content items
    And I log in as "manager1"
    And I visit "/local/tituscontentlibrary/index.php"
    When I click on "Leadership" "button"
    And I wait "1" seconds
    And I should not see "Python for Beginners"
    When I click on "All" "button"
    And I wait "1" seconds
    Then I should see "Python for Beginners"
    And I should see "Leadership Foundations"

  # ---------------------------------------------------------------------------
  # Add to Moodle action (JS)
  # ---------------------------------------------------------------------------

  @javascript
  Scenario: Add button queues the content and tile transitions to Queued state
    Given the Titus catalogue cache is primed with content items
    And I log in as "manager1"
    And I visit "/local/tituscontentlibrary/index.php"
    When I click on "[data-content-id=\"titus-001\"] [data-action=\"add\"]" "css_element"
    Then I wait until the tile "titus-001" contains "Queued"

  @javascript
  Scenario: Adding content end-to-end creates a course with a SCORM activity
    # The mock-backed priming injects a real SCORM ZIP fixture so the drained
    # adhoc task produces a genuine course + SCORM, with no live API dependency.
    Given the Titus catalogue is primed with a downloadable SCORM package
    And I log in as "manager1"
    And I visit "/local/tituscontentlibrary/index.php"
    When I click on "[data-content-id=\"titus-001\"] [data-action=\"add\"]" "css_element"
    And I wait until the tile "titus-001" contains "Queued"
    And I run the adhoc task "\local_tituscontentlibrary\task\add_content_task"
    Then the Titus content "titus-001" should have created a course with a SCORM activity

  # ---------------------------------------------------------------------------
  # Accessibility
  # ---------------------------------------------------------------------------

  @javascript
  Scenario: Marketplace has an accessible main heading
    Given the Titus catalogue cache is primed with content items
    And I log in as "manager1"
    When I visit "/local/tituscontentlibrary/index.php"
    Then "#titus-marketplace-heading" "css_element" should be visible
    And I should see "Titus Content Catalogue" in the "#titus-marketplace-heading" "css_element"

  @javascript
  Scenario: Keyboard user can activate an Add button and see the tile transition to Queued
    Given the Titus catalogue cache is primed with content items
    And I log in as "manager1"
    And I visit "/local/tituscontentlibrary/index.php"
    And I should see "Leadership Foundations"
    When I click on "[data-content-id=\"titus-001\"] [data-action=\"add\"]" "css_element"
    Then I wait until the tile "titus-001" contains "Queued"
    And the "disabled" attribute of "[data-content-id=\"titus-001\"] [data-region=\"tile-action\"] button" "css_element" should contain "disabled"

  @javascript
  Scenario: Aria-live announcer region has correct accessibility attributes
    Given the Titus catalogue cache is primed with content items
    And I log in as "manager1"
    When I visit "/local/tituscontentlibrary/index.php"
    Then "[data-region=\"titus-announcer\"]" "css_element" should exist
    And the "role" attribute of "[data-region=\"titus-announcer\"]" "css_element" should contain "status"
    And the "aria-live" attribute of "[data-region=\"titus-announcer\"]" "css_element" should contain "polite"
