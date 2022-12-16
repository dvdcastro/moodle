@mod @mod_url
Feature: Profile fields can be added as parameters in the URL resource

  Background:
    Given the following "custom profile fields" exist:
      | datatype | shortname | name            |
      | checkbox | muggle    | Muggleborn      |
      | text     | patronus  | Patronus animal |
    And the following "users" exist:
      | username | firstname | lastname | email                 | profile_field_muggle | profile_field_patronus |
      | user1    | User      | 1        | user1@address.invalid | 1                    | Shark                  |
      | user2    | User      | 2        | user2@address.invalid | 1                    | Seagul                 |
      | user3    | User      | 3        | user3@address.invalid | 0                    | Duck                   |
    And the following config values are set as admin:
      | displayoptions | 0,1,2,3,4,5,6 | url |
      | userprofilefields | 1 | url |
    And the following "courses" exist:
      | fullname | shortname | category | enablecompletion | showcompletionconditions |
      | Course 1 | C1        | 0        | 1                | 1                        |
    And the following "course enrolments" exist:
      | user   | course  | role            |
      | user1  | C1      | editingteacher  |
      | user2  | C1      | student         |
      | user3  | C1      | student         |
    And the following "activity" exists:
      | activity       | url                 |
      | course         | C1                  |
      | idnumber       | Music history       |
      | name           | Music history       |
      | intro          | URL description     |
      | externalurl    | https://moodle.org/ |
      | completion     | 2                   |
      | completionview | 1                   |
      | display        | 0                   |
    Given I am on the "Music history" "url activity" page logged in as "admin"
    And I follow "Settings"
    And I click on "URL variables" "link"
    And I set the field "parameter_0" to "muggle"
    And I set the field "variable_0" to "Muggleborn"
    And I set the field "parameter_1" to "patronus"
    And I set the field "variable_1" to "Patronus animal"
    Then I click on "Save and display" "button"

  Scenario Outline: Custom profile fields are correctly added to a URL
    Given I am on the "Music history" "url activity" page logged in as "<user>"
    Then I should see "Click https://moodle.org/?muggle=<muggle>&patronus=<patronus> link to open resource"
    Examples:
      | user       | muggle | patronus |
      | user2      | 1      | Seagul   |
      | user3      | 0      | Duck     |
