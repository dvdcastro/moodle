@mod @mod_url @wip
Feature: Group names can be added as parameters in the URL resource

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | user1    | User      | 1        | user1@address.invalid |
      | user2    | User      | 2        | user2@address.invalid |
      | user3    | User      | 3        | user3@address.invalid |
      | user4    | User      | 4        | user4@address.invalid |
    And the following config values are set as admin:
      | displayoptions | 0,1,2,3,4,5,6 | url |
    And the following "courses" exist:
      | fullname | shortname | category | enablecompletion | showcompletionconditions |
      | Course 1 | C1        | 0        | 1                | 1                        |
    And the following "course enrolments" exist:
      | user   | course  | role            |
      | user1  | C1      | editingteacher  |
      | user2  | C1      | student         |
      | user3  | C1      | student         |
      | user4  | C1      | student         |
    And the following "groups" exist:
      | name              | description    | course | idnumber |
      | Group 1           | G1 description | C1     | G1       |
      | Group 2           | G2 description | C1     | G2       |
      | Group 3 & Friends | Tricksy group  | C1     | G3       |
    And the following "group members" exist:
      | user     | group |
      | user2    | G1    |
      | user3    | G1    |
      | user3    | G2    |
      | user4    | G2    |
      | user4    | G3    |
    Given the following "activity" exists:
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
    And I set the field "parameter_0" to "groups"
    And I set the field "variable_0" to "Group names"
    And I click on "Save and display" "button"

  Scenario Outline: Group names are correctly added to a URL
    Given I am on the "Music history" "url activity" page logged in as "<user>"
    Then I should see "Click https://moodle.org/?<groupnames> link to open resource"
    Examples:
      | user       | groupnames                                            |
      | user2      | groups[]=Group%201                                    |
      | user3      | groups[]=Group%201&groups[]=Group%202                 |
      | user4      | groups[]=Group%202&groups[]=Group%203%20%26%20Friends |
